# Ce qu’est un module

À la fin de cette page, vous savez lire le module `iam` du starter et dire ce qu’il possède,
comment `analytics` l’atteint, ce que les deux partagent, et où chacun s’exécute.

Un module est une zone autonome de votre application, avec sa propre frontière : un domaine métier
(paiements, messagerie), ou un domaine de support sur lequel les autres s’appuient (identité,
notifications). Il est construit comme un microservice : il possède son code, ses données et ses
contrats, et peut être déployé seul. Le paquet ajoute ce qui rend cette frontière réelle : tant que le code d’un module
s’exécute, il s’exécute dans un contexte qui lui est propre.

## Ce qu’il possède

`iam` possède les utilisateurs, les jetons et les permissions. Tout ce qui les sert est dans son
dossier, organisé comme une application Laravel :

```
apps/Iam/
├── app/
│   ├── Actions/RegisterUser.php        les cas d'usage
│   ├── Models/User.php                 ses modèles
│   ├── Http/Controllers/Api/AuthController.php
│   ├── Rpc/FindUserHandler.php         les messages auxquels il répond
│   ├── Events/UserRegistered.php       les événements qu'il annonce
│   ├── Console/SyncPermissions.php     ses commandes
│   └── Providers/IamServiceProvider.php
├── config/database.php                 ses connexions
├── database/migrations/                ses tables
├── routes/api.php                      servies sous /iam/api/…
└── tests/Feature/
```

Ses routes, ses jobs, ses commandes, ses écouteurs, ses migrations et ses tests lui appartiennent.
Le paquet les trouve à partir du dossier, donc il n’y a rien à enregistrer.
[Où vont les fichiers](/fr/build/layout).

## Son propre contexte

`iam` déclare ses connexions dans `apps/Iam/config/database.php`, et `default` nomme celle sur
laquelle son code s’exécute :

```php
return [
    'default' => 'iam',
    'connections' => [
        'iam' => ['driver' => 'sqlite', 'database' => env('IAM_DATABASE', database_path('iam.sqlite'))],
    ],
];
```

`analytics` et `notifications` ont leur propre fichier avec leur propre `default`. Un modèle d’`iam`
ne nomme aucune connexion, et lit pourtant celle d’`iam` :

```php
// dans iam : la connexion d'iam. Dans analytics, la même ligne lit la connexion d'analytics.
User::query()->find($id);
```

La connexion est la première chose qu’un module règle. Sa configuration (`config/cache.php`, par
exemple) et ses services (cache, mailer, disques) suivent la même règle : les valeurs propres d’un
module s’appliquent tant que son code s’exécute, et celles de l’application reviennent ensuite.
[Les réglages d’un module](/fr/build/settings), [les services d’un module](/fr/build/services).

## Comment un autre module l’atteint

`analytics` n’importe jamais `Apps\Iam\…`. Il a trois façons d’entrer.

Un appel, pour lire un enregistrement tout de suite. Le message est partagé ; `iam` y répond.
`RpcPrincipals` est la classe qui demande, pour un module qui ne garde pas de copie des
utilisateurs :

```php
// foundation/Iam/Auth/RpcPrincipals.php, utilisée par un module qui ne garde pas de copie des utilisateurs
$user = $this->iam->findUser((int) $identity->id);
```

```php
// apps/Iam/app/Providers/IamServiceProvider.php
protected array $rpcHandlers = [
    FindUser::class => FindUserHandler::class,
];
```

Un événement, pour apprendre que quelque chose s’est produit. `iam` l’annonce ; `analytics` le
traite :

```php
// apps/Analytics/app/Providers/AnalyticsServiceProvider.php
protected array $streamHandlers = [
    IamEvent::UserRegistered->value => [RecordSignup::class],
];
```

Une copie, pour lister et joindre sur les utilisateurs d’`iam` à côté de ses propres lignes :

```php
// le même fichier
protected array $shadows = [UserShadow::class];
```

[Lequel utiliser](/fr/talk/choosing).

## Ce qu’ils partagent

`analytics` peut nommer le message `FindUser` et le client `IamClient` parce qu’ils ne sont pas dans
`apps/Iam` : ils sont dans la fondation, le code que tout module peut charger.

```
foundation/Iam/
├── Rpc/FindUser.php                    le message, avec les règles qui le valident
├── Services/IamClient.php              comment n'importe quel module appelle iam
├── Data/UserData.php                   ce que contient la réponse
├── Events/IamEvent.php                 les noms des événements qu'iam annonce
├── Events/UserRegisteredPayload.php    la charge utile de l'un d'eux
└── Shadows/UserShadow.php              la forme de la copie que les autres gardent des utilisateurs d'iam
```

`iam` possède ces fichiers, et tous les autres modules les lisent. Rien d’autre n’est partagé.
[Ce que les modules partagent](/fr/talk/foundation).

## Où il s’exécute

Le starter déclare ses trois modules dans `config/distributable.php`, et `iam` a un `host` :

```php
'declared' => [
    'iam' => ['host' => env('IAM_HOST')],
    'analytics' => [],
    'notifications' => [],
],
'runs' => env('RUN_MODULES', '*'),
```

`RUN_MODULES` dit lesquels un processus démarre. Avec la valeur par défaut, un processus lance les
trois, et `IamClient::findUser()` est un appel de méthode. Dans `docker-compose.mesh.yml`, `iam` a un
conteneur avec `RUN_MODULES: iam`, `analytics` en a un autre avec `RUN_MODULES: analytics` et
`IAM_HOST` qui pointe vers `iam` : le même `findUser()` passe par le réseau.

```
RUN_MODULES=*                          RUN_MODULES=iam         RUN_MODULES=analytics
un processus                           un processus            un processus
iam, analytics, notifications          iam                     analytics
findUser() est un appel de méthode     ◄──── findUser() passe par le réseau ─────
les événements passent toujours par le flux
```

Le code des modules est le même dans les deux cas. [Où s’exécutent les modules](/fr/run/where).

## Ensuite

[Pourquoi le paquet existe](/fr/understand/why), ou [essayez le starter](/fr/start/starter).
