# Les services d’un module

À la fin de cette page, vos modules ont chacun leur propre cache, leur propre mailer et leurs propres
disques, et vous savez donner la même chose à un service à vous.

## Le problème

Les réglages d’un module suivent le module, mais Laravel construit certains services une seule fois, à
partir des réglages qu’il trouve la première fois, puis les réutilise. Le cache, le mailer, le
système de fichiers et la plupart des clients de paquets fonctionnent ainsi. Sans plus,
`Cache::get()` dans `analytics` garderait le préfixe qu’`iam` a réglé, si `iam` a été le premier à
s’exécuter.

`module_services` dans `config/distributable.php` liste les services construits une fois pour chaque
module, à partir des propres réglages de ce module. La liste du paquet contient déjà ceux de
Laravel :

```php
'module_services' => [
    'db', 'cache', 'cache.store', 'queue', 'queue.connection', 'mail.manager', 'mailer',
    'filesystem', 'filesystem.disk', 'filesystem.cloud', 'log', 'redis',
    BroadcastManager::class, Broadcaster::class,
    'auth', 'auth.driver', 'validator', 'validation.presence',
],
```

La première fois qu’un module utilise un service listé, le paquet le construit pour ce module. À
chaque fois que ce module s’exécute de nouveau, c’est la même instance qui revient ; un autre module
a la sienne.

## Ce que l’application leur a appris

Un pilote donné à `extend()`, ou une règle de validation, est appris à l’instance que tient
l’application. L’instance propre à chaque module l’apprend aussi, quel que soit le moment où
l’application l’a appris, et construit le pilote avec les réglages du module :

```php
// app/Providers/AppServiceProvider.php
Storage::extend('dropbox', fn ($app, array $config) => new Filesystem(new DropboxAdapter($config)));
Validator::extend('even', fn (string $attribute, mixed $value): bool => $value % 2 === 0);
```

```php
// dans iam : le disque dropbox que règle le config/filesystems.php d'iam, et la règle
Storage::disk('dropbox')->put('avatar.png', $png);
Validator::make(['n' => 2], ['n' => 'even'])->passes();
```

Un pilote que le code d’un module apprend pendant qu’il s’exécute reste à ce module.

## Les noms à ajouter

Un service que la liste ne contient pas s’ajoute sous le nom avec lequel il est enregistré, celui que
son provider donne à `singleton()` :

- le service d’un paquet : le nom que son provider lui donne. Cherchez `singleton(` dans le service
  provider du paquet ; `app()->bound('nom')` vous dit dans Tinker si un nom existe ;
- un singleton à vous qui reçoit l’un d’eux dans son constructeur, comme `ReportBuilder` : le nom de
  sa classe.

Votre `config/distributable.php` remplace la liste du paquet : gardez ses noms à côté des vôtres.

## Un exemple : un disque par module

```php
// apps/Iam/config/filesystems.php : le dossier où vont les fichiers d'iam
return ['disks' => ['local' => ['root' => storage_path('app/iam')]]];
```

```php
// dans iam : storage/app/iam/avatar.png
Storage::disk('local')->put('avatar.png', $png);
```

Le dossier est celui que vous mettez dans le `config/filesystems.php` du module ; le paquet ne décide
de rien. Sans `'filesystem'` dans `module_services`, le premier module à s’exécuter fixerait le
dossier pour tous.

## Un service à vous

Un singleton qui reçoit le cache ou le mailer dans son constructeur garde l’instance qu’il a reçue
quand il a été construit. Construit dans `iam`, il continuerait à lire le cache d’`iam` quand
`analytics` l’utilise. Listez sa classe : il est alors construit pour chaque module, avec les
services de ce module.

```php
final class ReportBuilder
{
    public function __construct(private readonly \Illuminate\Contracts\Cache\Repository $cache) {}
}
```

```php
// config/distributable.php
'module_services' => [
    'db', 'cache', 'cache.store', 'queue', 'queue.connection', 'mail.manager', 'mailer',
    'filesystem', 'filesystem.disk', 'filesystem.cloud', 'log', 'redis',
    BroadcastManager::class, Broadcaster::class,
    'auth', 'auth.driver', 'validator', 'validation.presence',
    ReportBuilder::class,
],
```

## Les bindings

Un binding fait dans le provider de l’application ou dans celui d’un module est partagé par tout le
processus : il vaut pour chaque module qui s’y exécute. `module_services` change l’instance construite
à partir du binding, jamais la classe qu’il lie, et une instance déjà construite ne suit pas un
binding changé après coup.

Pour que deux modules se comportent différemment, liez une seule classe qui lit les réglages du
module quand elle est construite, et listez-la dans `module_services`.

## Ensuite

[Migrations](/fr/build/migrations).
