# Où s’exécutent les modules

À la fin de cette page, vous savez dire quels modules un processus exécute, où répond un module qui
s’exécute ailleurs, et ce sur quoi plusieurs applications doivent s’accorder.

Un module s’écrit une fois. Qu’il soit dans un processus avec d’autres modules ou seul, dans une
application avec d’autres modules ou dans sa propre application, c’est un choix de l’endroit où il
s’exécute et de l’endroit où vit son code : le code qui l’appelle, lui annonce quelque chose ou copie
ses lignes est le même. Une application contient un module ou plusieurs, et `config/distributable.php`
dit lesquels. Le [starter](/fr/start/starter) est une application de trois modules ; les
[exemples](https://github.com/mk-josias/laravel-distributable/tree/main/examples) sont trois
applications d’un module chacune.

## Choisir les modules qu’un processus exécute

```dotenv
# tous les modules (la valeur par défaut)
RUN_MODULES=*
# ceux-ci seulement
RUN_MODULES=iam,analytics
```

Un module est déclaré dans chaque processus, donc chaque processus sait que les autres existent : il
écoute leurs événements et les appelle par les clients de la fondation. `RUN_MODULES` dit lesquels il
démarre. Par exemple, dans un `docker-compose.mesh.yml` où `analytics` appelle `iam` dans un autre
conteneur :

```yaml
  analytics:
    environment:
      RUN_MODULES: analytics
      # lu par declared.iam.host
      IAM_HOST: "http://iam.svc:8000"
  iam:
    environment:
      RUN_MODULES: iam
    networks:
      default:
        # le nom qu'analytics appelle
        aliases: [iam.svc]
```

`Distributable\Services\ModuleRegistry` vous donne les listes : `all()` renvoie chaque module déclaré
et `local()` ceux que ce processus exécute. `get($name)` lève une exception sur un nom inconnu, et
`forClass($class)` renvoie le module auquel une classe appartient, d’après son espace de noms.

Avec `MODULES_STATUS_ROUTE=/`, un processus répond quels modules il exécute :

```json
{ "message": "Hello from laravel-distributable", "modules": ["iam", "analytics"], "status": "ok" }
```

En production, `php artisan optimize` lance `modules:cache`, qui écrit ce que disent les dossiers des
modules (espaces de noms, connexions et sources de copies) dans `bootstrap/cache/distributable.php`, et
l’application lit ce fichier à la place des dossiers. `optimize:clear` (ou `modules:clear`) le
supprime. Le cache ne décide jamais quels modules existent : c’est `distributable.declared` qui le
fait.

## Où vit le code d’un module

Dans une application de plusieurs modules, chaque module a un dossier, `apps/Iam` sous `Apps\Iam\`
(`modules.path` et `modules.namespace`). Dans une application d’un seul module, le module est
l’application : `app/` reste la racine de son code, et il le déclare avec ses propres `path` et
`namespace`. Un module organisé autrement donne ses deux valeurs de la même façon.

```php
// config/distributable.php, dans une application de plusieurs modules
'declared' => [
    'iam' => [],
    'analytics' => [],
],

// config/distributable.php, dans notifications : une application d'un seul module
'declared' => [
    'notifications' => ['path' => '.', 'namespace' => 'App'],
    // un module qu'il appelle et n'exécute pas : seulement où il répond
    'iam' => ['host' => env('IAM_HOST', 'http://127.0.0.1:8001')],
],
'runs' => env('RUN_MODULES', 'notifications'),
```

## Ce que `declared` peut dire d’un module

Un module est un nom dans `distributable.declared`. Son tableau ne contient que ce que son dossier ne
peut pas dire, et peut être vide (`'analytics' => []`).

```php
// config/distributable.php (php artisan vendor:publish --tag=distributable-config)
'declared' => [
    'iam'       => ['host' => env('IAM_HOST')],
    'analytics' => [],
],
'modules'    => ['path' => 'apps', 'namespace' => 'Apps'],
'foundation' => ['path' => 'foundation', 'namespace' => 'Foundation'],
```

- **`declared.iam.host` :** l’URL d’`iam` quand il s’exécute dans un autre processus : les appels vers
  lui y vont, comme le fait `IAM_HOST=http://iam.internal:8000`. Ce peut aussi être
  `['url' => …, 'transport' => …]` pour nommer un autre transport. `modules:doctor` signale un module
  qui s’exécute ailleurs et sert un contrat sans lui
- **`declared.{nom}.path`, `declared.{nom}.namespace` :** son propre dossier et l’espace de noms de son
  `app/`, quand ce n’est pas `apps/{Nom}` sous `Apps\{Nom}` :
  `'iam' => ['path' => 'apps/Identity', 'namespace' => 'Apps\Identity']`
- **`declared.{nom}.foundation` :** son dossier sous `foundation/` quand ce n’est pas son nom en
  StudlyCase : `'iam' => ['foundation' => 'Identity']`
- **`modules`, `foundation` :** le dossier des modules et celui de la fondation (`path`), et leur espace
  de noms racine (`namespace`)

Pour le module `Iam` avec les valeurs par défaut, son nom est `iam` (le nom de son dossier en
snake_case), son espace de noms `Apps\Iam` chargé depuis `apps/Iam/app`, son provider
`Apps\Iam\Providers\IamServiceProvider`, et il a une connexion parce que
`apps/Iam/config/database.php` existe.

## Partager la fondation entre applications

Deux modules n’importent jamais les classes l’un de l’autre, dans une application ou dans deux. Dans
deux, ils ne le pourraient pas : chacun est déployé seul. Pourtant ils doivent s’accorder sur ce
qu’ils échangent : le message que l’un envoie et auquel l’autre répond, la charge utile d’un
événement, la forme d’une copie. Cet accord vit dans un endroit que les deux peuvent atteindre, la
fondation ([Ce que les modules partagent](/fr/talk/foundation)).

Dans une application, la fondation est un dossier. Entre applications, c’est un paquet Composer, que
chacune requiert ; les classes sont les mêmes.

```json
// composer.json, dans notifications
"require": { "example/foundation": "*" }
```

```json
// composer.json, dans la fondation
"autoload": { "psr-4": { "Foundation\\": "src/" } },
"extra": { "laravel": { "providers": ["Foundation\\FoundationServiceProvider"] } }
```

Laravel découvre le provider dans chaque application qui requiert le paquet. Le paquet n’exige rien de
l’organisation de la fondation : ce que les modules partagent doit seulement être des classes que
chaque application peut charger. Les exemples utilisent un seul paquet pour leurs trois applications,
avec un dossier par module (`Iam/`, `Notifications/`) ; l’organisation de la vôtre vous appartient.

## Ce dont une application a besoin pour appeler un module qui s’exécute ailleurs

- **le même `DISTRIBUTABLE_RPC_SECRET` (ou `APP_KEY`) que les autres :** le transport signe un appel et
  l’application appelée rejette tout appel qu’elle ne peut pas vérifier
- **le flux d’événements, partagé :** les événements y circulent entre les applications
- **`declared.{module}.host` pour chaque module qu’elle appelle :** ses appels vont à cet hôte

## Ensuite

[Sortir un module](/fr/run/moving), ou [déployer les processus](/fr/run/deploying).
