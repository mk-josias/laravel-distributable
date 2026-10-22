# Travailler avec d’autres paquets

À la fin de cette page, vous savez où vont les données d’un paquet tiers et comment les garder dans le bon module.

Un paquet tiers fonctionne dans un module comme dans n’importe quelle application Laravel. Ce qui
change, c’est l’endroit où vont ses données, parce que le code d’un module s’exécute sur la base du
module.

## Où vont les données d’un paquet

Un paquet qui utilise la connexion par défaut de Laravel (une médiathèque, un journal d’activité,
des permissions) range ses lignes dans la base du module qui l’appelle : chaque module a donc les
siennes. Ses tables y sont parce que ses migrations s’exécutent dans la base de chaque module :
celles qu’il charge avec `loadMigrationsFrom()`, et celles que vous publiez avec `vendor:publish`
dans `database/migrations`. Quand un seul module utilise le paquet, publiez dans ce module :
`vendor:publish --module=iam` met dans `apps/Iam/` ce que le paquet publie sous `app/`,
`database/`, `config/` ou `resources/views`, ses migrations et son fichier de config compris.

Les jobs échoués et les lots de jobs sont enregistrés dans la base du module à qui appartient le
job. Horizon, `file` et DynamoDB ne changent pas.

Hors d’un module, il n’y a pas de connexion par défaut. Un paquet qui interroge la connexion par
défaut à cet endroit échoue bruyamment, tout comme un cache, une file ou un stockage de sessions
`database` qui ne nomme aucune connexion.

## Un paquet qui garde un seul stockage pour toute l’application

Il appartient à un module. Laravel Sanctum en est le cas habituel : `PersonalAccessToken::findToken()`
lit la connexion par défaut, donc un jeton émis dans `iam` n’est pas dans la base d’`analytics`.
Installez-le dans `iam` seulement, et laissez les autres modules valider les jetons par un message
auquel iam répond :

```php
// apps/Iam/app/Rpc/FindUserByTokenHandler.php : le modèle User utilise HasApiTokens
public function handle(FindUserByToken $message): ?array
{
    $user = $this->users->byToken($message->token);

    return $user === null ? null : ['id' => (int) $user->getKey(), 'name' => (string) $user->name];
}
```

Publiez la migration de Sanctum avec `vendor:publish --module=iam`, pour que
`personal_access_tokens` n’existe que dans la base d’iam. Il en va de même pour un paquet qui charge
ses données une fois par processus sous une seule clé de cache, comme spatie/laravel-permission :
gardez-le dans un module. Telescope et Pulse aussi : donnez-leur la connexion d’un module, dans leur
propre fichier de config.

## Les paquets qui servent leurs propres routes (panneaux d’administration, Livewire)

Une requête s’exécute dans le module qui contient le code de sa route : la classe de son contrôleur,
ou le fichier où sa closure est écrite. Filament, Nova ou Livewire servent des routes dont les
contrôleurs n’appartiennent à aucun module : leurs requêtes s’exécutent donc hors de tout module, et
toute requête en base qu’elles font échoue. Donnez un module à leurs routes en leur ajoutant le
middleware du module, pour un panneau Filament :

```php
->middleware([
    // le middleware propre au panneau
    \Distributable\Http\Middleware\SetModuleContext::class.':iam',
])
```

Un panneau d’administration qui gère les données de tous les modules traverserait toutes les
frontières. Donnez plutôt à chaque module son propre panneau (`/admin/iam` dans iam,
`/admin/analytics` dans analytics), chacun ne lisant que sa propre base. Un écran qui a besoin des
données d’un autre module les lit dans une copie locale ou par les messages de ce module, comme tout
code de module.

## Écouter les événements d’un paquet

Un module peut écouter les événements d’un paquet (`Login`, un événement de médiathèque) et écrire
quand même dans sa propre base, s’il déclare l’écouteur dans le `$listen` de son provider. Un
écouteur enregistré autrement (`Event::listen()` dans `boot()`, la découverte, un subscriber)
s’exécute avec les réglages de celui qui a déclenché l’événement.

```php
protected array $listen = [
    \Illuminate\Auth\Events\Login::class => [\Apps\Iam\Listeners\RecordLogin::class],
];
```

## Remplacer ce qu’un paquet enregistre

Le provider d’un module s’enregistre après tous les providers des paquets : un module peut donc
remplacer ce qu’un paquet enregistre dans le conteneur.

## Lancer la commande d’un paquet dans un module

Chaque commande Artisan accepte `--module`, sauf si elle en a déjà une à elle :

```bash
php artisan some:package-command --module=iam
```

Elle s’exécute alors sur la base de ce module, avec ses réglages. `db:seed --module=iam` lance le
`DatabaseSeeder` de l’application, puis celui d’iam.

## Factories

Le modèle d’un module trouve sa factory dans le module. Un paquet ou une application qui règle son
propre résolveur de factories remplace celui-ci, et peut se rabattre sur
`Distributable\Context\ModuleFactories::factoryName()` et `modelName()`.

## Une classe d’un module que ce processus n’exécute pas

`class_exists()` sur une classe d’un module que ce processus n’exécute pas lève `ModuleException` au
lieu de renvoyer `false`. Un paquet qui sonde des classes (découverte, morph maps) ne doit rencontrer
que les classes des modules que ce processus exécute.

## Le travail démarré dans un module

`defer()`, `Concurrency::run()` avec le pilote `process` et `Octane::concurrently()` gardent le
module dans lequel ils ont été démarrés. Un paquet qui réenregistre l’un d’eux perd cela, pour son
propre travail.
