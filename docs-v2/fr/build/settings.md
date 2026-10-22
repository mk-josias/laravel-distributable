# Les réglages d’un module

À la fin de cette page, vous savez dans quel module s’exécute chaque morceau de code, et comment
donner à un module sa propre configuration et ses propres connexions.

Un module a son propre contexte : sa configuration, ses connexions, ses services et ses chemins
(`app_path()`, `database_path()`). Une seule règle décide lequel s’applique : le code s’exécute dans
le contexte du module qui le contient. Une requête, un job, une commande, un événement ou un appel
d’un autre module entre dans le module dont le dossier ou l’espace de noms contient son code. Le
contexte précédent revient ensuite.

Le reste de la page en découle. Le code d’un module reste du Laravel ordinaire (`User::query()`,
`DB::transaction()`, `Cache::get()`) sans nommer aucune connexion, parce que pendant qu’il
s’exécute, la connexion par défaut de Laravel est la sienne.

![analytics appelle iam par son client : le même DB::transaction() atterrit sur la connexion d'iam, puis les réglages d'analytics reviennent ; hors de tout module, toute requête lève une exception](/images/module-context.png)

## Quand le code d’un module s’exécute

Vous ne basculez rien vous-même. Le code s’exécute dans un module quand c’est :

- une requête sur une route dont le code est celui du module : une closure écrite dans le dossier
  du module, ou un contrôleur de son espace de noms, où que la route soit déclarée ;
- un job, une commande, un handler d’événement du module ;
- un écouteur Laravel que le module déclare dans `$listen`, une tâche qu’il planifie dans
  `schedule()` ;
- un appel qu’un autre module lui envoie, depuis ce processus ou un autre ;
- une closure en file, `defer()`, `Concurrency::run()` ou `Octane::concurrently()` démarrés dans le
  module.

Quand analytics appelle `IamClient::findUser()` et qu’iam s’exécute dans le même processus, le
handler d’iam s’exécute avec les réglages d’iam, et ceux d’analytics reviennent quand la méthode
retourne.

Une closure envoyée à un autre processus (tâches Octane, `Concurrency`, closures en file) est
reconstruite à partir de sa ligne dans le fichier : gardez une seule closure par ligne, sinon le
worker risque de reconstruire la mauvaise.

### Le travail qui s’exécute plus tard

Pour une closure à vous qui s’exécute plus tard ou ailleurs, `ModuleContext::bind()` emporte avec
elle le module courant :

```php
$task = app(\Distributable\Context\ModuleContext::class)->bind(fn () => Report::rebuild());
```

`ModuleContext::current()` renvoie le module qui s’exécute maintenant, ou `null` hors de tout module.

## Hors d’un module

Hors d’un module il n’y a pas de connexion par défaut : une closure de route dans `routes/web.php`, la
route d’un autre paquet, ou Tinker sans `--module` lève `ModuleException` (« No module runs here ») à
sa première requête. Passez plutôt par le client du module, qui fonctionne encore quand le module
s’exécute ailleurs :

```php
// jamais Apps\Iam\Models\User::find($id)
app(\Foundation\Iam\Services\IamClient::class)->findUser($id);
```

Il n’y a pas de clés étrangères entre modules. Référencez les lignes d’un autre module par leur id,
ou gardez-en une [copie](/fr/talk/copies).

Un processus qui n’exécute qu’un module n’a pas de dehors : tout ce qui y démarre s’exécute dans ce
module. C’est le cas d’une application qui est son propre module (`'path' => '.'`), et d’un
conteneur démarré avec `RUN_MODULES=iam`. Là, `php artisan migrate`, `tinker` et le
`routes/web.php` de l’application n’ont pas besoin de `--module`.

## Atteindre un autre module

Un module ne peut pas exécuter son code avec les réglages d’un autre module : `within()`, appelé
depuis le code d’un autre module ou depuis la fondation, lève une `ModuleException`. La voie vers un
autre module, ce sont ses messages, voir [Appeler un autre module](/fr/talk/calls).

## Donner à un module ses propres réglages

Le `config/` de l’application contient les valeurs par défaut que tous les modules partagent, et le
`config/` d’un module contient ce qui lui est propre. Tant que le code d’un module s’exécute, il voit
la configuration de l’application avec ses propres fichiers par-dessus :

- chaque valeur que fixe un fichier du module remplace celle de l’application : une chaîne, un
  nombre, une liste (`['a', 'b']`) ou `[]`. Tout autre tableau est parcouru clé par clé ;
- une clé que l’application ne règle pas n’existe que pendant que ce module s’exécute ;
- ce que le code du module change avec `config()->set()` reste à lui : la valeur revient la
  prochaine fois que le module s’exécute, et l’application ne la voit jamais ;
- hors du module, les valeurs de l’application reviennent. Deux modules dans un même processus
  gardent chacun les leurs.

```php
// apps/Iam/config/cache.php : tant que le code d'iam s'exécute, config('cache.prefix') vaut 'iam'
return ['prefix' => 'iam'];
```

Un fichier nommé dans `shared` (`config/distributable.php`) fait exception : le processus le lit en
entier, donc les valeurs de chaque module s’ajoutent à celles de l’application, pour tous les
modules. Par défaut, il contient `distributable`, où un module déclare ses flux. Quand deux modules
d’un même processus règlent différemment une clé d’un tel fichier, c’est le module enregistré en
dernier qui l’emporte, et `modules:doctor` le signale.

Un service que Laravel construit une fois à partir de la configuration, comme le cache, garde les
valeurs avec lesquelles il a été construit : [Les services d’un module](/fr/build/services) donne à
chaque module le sien. Une fois `config:cache` lancé, le cache contient les fichiers de chaque module
et ils ne sont plus lus.

## La connexion

Le premier réglage que font la plupart des modules est la connexion sur laquelle leur code
s’exécute. Un module déclare ses connexions dans son propre `config/database.php`, et `default` nomme
celle sur laquelle son code s’exécute :

```php
// apps/Iam/config/database.php
'default' => 'iam',
'connections' => [
    // lit et écrit des lignes
    'iam' => [
        'driver' => 'pgsql', 'database' => 'iam',
        'username' => env('IAM_USERNAME'), 'password' => env('IAM_PASSWORD'),
    ],
    // crée et modifie des tables
    'owner' => [
        'driver' => 'pgsql', 'database' => 'iam',
        'username' => env('IAM_OWNER'), 'password' => env('IAM_OWNER_PASSWORD'),
    ],
],
```

Une connexion qu’un module déclare n’existe que pendant que son code s’exécute. Deux modules peuvent
donner le même nom à une connexion et atteindre chacun sa propre base : chaque module du starter
nomme `owner` le rôle qui possède ses tables, et migre avec `--database=owner` (voir
[Migrations](/fr/build/migrations#les-executer)). Les connexions peuvent aussi se trouver dans le
`config/database.php` racine, à côté des autres : chaque module les voit alors, et s’exécute sur
celle que nomme son `default`.

Elles peuvent pointer vers des bases séparées sur un même serveur, des schémas séparés d’une même base,
des serveurs séparés, ou des fichiers sqlite en test : le code est le même. Plusieurs modules peuvent
partager une même base ; donnez alors à leurs tables des noms distincts (`protected $table =
'iam_users'`). Sans `default`, le module n’a aucune connexion réglée : ses modèles doivent nommer les
leurs.

Les modèles sont des modèles Eloquent ordinaires, `User extends Authenticatable` compris. `DB::`,
`DB::transaction()` et `Schema::` utilisent la connexion du module, sans rien nommer :

```php
// apps/Iam/app/Actions/RegisterUser.php
final class RegisterUser
{
    public function execute(string $name): User
    {
        // la connexion d'iam
        return DB::transaction(fn () => User::query()->create(['name' => $name]));
    }
}
```

### Une deuxième connexion

Un module qui a besoin d’un entrepôt à côté de sa propre connexion le déclare, et le nomme là où il
l’utilise :

```php
// apps/Analytics/config/database.php
'default' => 'analytics',
'connections' => [
    'analytics' => ['driver' => 'pgsql', 'database' => 'analytics'],
    'warehouse' => ['driver' => 'pgsql', 'database' => 'warehouse'],
],
```

```php
// apps/Analytics/app/Models/Report.php
protected $connection = 'warehouse';
```

Une migration qui règle `$connection = 'warehouse'` s’exécute là.

## Jobs échoués et lots

Un job échoué est enregistré sur la connexion sur laquelle il a tourné : celle de son module, qu’il
garde même si ce module a été renommé ou retiré depuis. Un lot est enregistré sur la connexion du
module de son premier job, ou du module qui s’exécute quand le job n’en a pas. Aucun des deux n’est
jamais enregistré sur la connexion d’un autre module : sans connexion où aller, le paquet lève une
exception. Il n’existe pas de base de l’application : `queue.failed.database` et
`queue.batching.database` ne sont lues que dans le `config/queue.php` d’un module, pour envoyer ses
jobs échoués ou ses lots vers une autre connexion que ce module déclare. Sans elles, un module
utilise sa propre connexion.

```php
// apps/Analytics/config/queue.php
return ['failed' => ['database' => 'warehouse']];
```
`queue:failed`, `queue:retry` et `Bus::findBatch()` lisent les connexions des modules que ce
processus exécute. Cela demande le stockage en base de Laravel (le failer `database-uuids` et les
lots en base) ; les tables viennent du `database/migrations` de l’application, qui s’exécute sur la
connexion de chaque module.

## Ensuite

[Les services d’un module](/fr/build/services) : le cache, le mailer et les disques suivent aussi le
module.
