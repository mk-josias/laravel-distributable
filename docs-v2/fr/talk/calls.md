# Appeler un autre module

À la fin de cette page, un module demande un utilisateur à `iam` et obtient la réponse, que `iam`
s’exécute dans le même processus ou dans un autre.

Utilisez un appel quand vous avez besoin de la réponse tout de suite : lire des données que l’autre
module possède, ou lui faire faire quelque chose dont vous avez besoin du résultat. Quand vous n’avez
pas besoin du résultat, [annoncez plutôt un événement](/fr/talk/events). Un appel ne fait pas partie de
votre transaction : si votre code échoue ensuite, ce que l’autre module a fait reste fait.

## L’appel

```php
// foundation/Iam/Auth/RpcPrincipals.php, pour un module qui ne garde pas de copie des utilisateurs
$user = $this->iam->findUser((int) $identity->id);
```

`IamClient` appartient à `iam`. Un module qui l’appelle n’importe jamais une classe d’`iam` : il
connaît le client et les messages, qui vivent dans la [fondation](/fr/talk/foundation).

## Écrire le message

Un appel est un message. `iam` le met dans la fondation, avec les règles qui le valident, pour que
chaque appelant l’ait :

```php
// foundation/Iam/Rpc/FindUser.php
#[Rpc(module: 'iam')]
final readonly class FindUser implements Message
{
    public function __construct(public int $id) {}

    public static function rules(): array
    {
        return ['id' => ['required', 'integer']];
    }
}
```

Sur le fil, le message s’appelle par le nom de sa classe en kebab case (`find-user`). Changez-le avec
`name:` : `#[Rpc(module: 'iam', name: 'get-user')]`.

## Y répondre

Le handler vit dans `iam`, à côté de ses modèles, et répond avec ses propres données :

```php
// apps/Iam/app/Rpc/FindUserHandler.php
/** @implements Handler<FindUser> */
final class FindUserHandler implements Handler
{
    public function __construct(private UserRepository $users) {}

    public function handle(FindUser $message): ?UserData
    {
        $user = $this->users->retrieve($message->id);

        return $user === null ? null : new UserData((int) $user->getKey(), (string) $user->name);
    }
}
```

et le provider d’`iam` le liste :

```php
// apps/Iam/app/Providers/IamServiceProvider.php
protected array $rpcHandlers = [
    FindUser::class => FindUserHandler::class,
];
```

`iam` n’écrit aucune route : le paquet en sert une pour chaque message auquel il répond. Le handler
s’exécute avec les connexions et les réglages d’iam, d’où que vienne l’appel. Renvoyer `null` est une
réponse : l’appelant lit `null`.

## Le client

`iam` fournit aussi le client que les appelants utilisent, dans la fondation :

```php
// foundation/Iam/Services/IamClient.php
final class IamClient extends \Distributable\Services\Rpc\RpcClient
{
    public function findUser(int $id): ?UserData
    {
        return $this->readThrough(
            self::userKey($id),
            // une semaine : iam oublie la clé quand l'utilisateur change
            self::DEFAULT_TTL,
            // la réponse brute, celle qui est gardée dans le cache
            fn (): mixed => $this->send(new FindUser($id)),
            fn (array $raw): UserData => UserData::from($raw),
        );
    }
}
```

`send()` valide le message avec ses règles, puis le livre. Une charge utile que les règles refusent
lève une `ValidationException`.

## Où iam s’exécute

L’appelant ne change pas quand `iam` déménage. Ce qui décide, c’est la configuration du processus :

- `iam` s’exécute dans ce processus (`RUN_MODULES`) : le handler est appelé directement. Il n’y a ni
  HTTP, ni signature, rien sur le réseau.
- `iam` s’exécute ailleurs : l’appel va à son hôte, `declared.iam.host` :

```dotenv
# lu par declared.iam.host
IAM_HOST=http://iam.svc:8000
```

Le côté conteneur, avec l’alias réseau qui fait résoudre `iam.svc`, est dans
[Déployer](/fr/run/deploying#deployer-un-module-seul).

## Garder une réponse

Mettez en cache ce qui est lu souvent et change rarement (un utilisateur, un produit) : une longue
durée de vie, et le module qui possède les données oublie la réponse quand il écrit. Ne mettez pas en
cache ce qui doit être exact à chaque appel, comme un jeton qui peut être révoqué. Les données sur
lesquelles joindre, filtrer ou trier en local vont dans une [copie](/fr/talk/copies), pas dans ce
cache.

`readThrough()`, utilisé par `findUser()` ci-dessus, garde la réponse : l’appel suivant ne sort pas du
processus. Le propriétaire écrit les méthodes qui oublient :

```php
// foundation/Iam/Services/IamClient.php, appelée par iam quand l'utilisateur change
public function forgetUser(int $id): void
{
    $this->forget(self::userKey($id));
}

// pas en cache : un jeton révoqué doit cesser de fonctionner tout de suite
public function findUserByToken(string $token): ?UserData
{
    $raw = $this->send(new FindUserByToken($token));

    return is_array($raw) ? UserData::from($raw) : null;
}
```

- `readThrough($key, $ttl, $fetch, $map, $tags)` lit le cache. En cas d’absence, il appelle `$fetch` et
  garde la réponse brute. `$map` transforme la réponse brute en ce que vous renvoyez, à chaque
  lecture, donc une transformation changée par un déploiement s’applique aux réponses mises en cache
  avant elle. Une réponse que `$map` ne sait plus lire est journalisée, abandonnée et renvoyée comme
  `null`.
- `readThroughUntil($key, $fetch, $map, $ttlOf)` est identique, avec une durée de vie lue dans la
  réponse transformée, par exemple un jeton gardé jusqu’à son expiration. Une durée de zéro ou moins
  renvoie `null`.
- `forget($key, $tags)` abandonne une réponse.

Les réponses vivent dans le magasin de cache que nomme `distributable.rpc.cache`. L’appelant garde une
réponse et le propriétaire l’oublie, donc tous les processus utilisent le même magasin, avec les mêmes
`CACHE_PREFIX` et `REDIS_PREFIX`. Une réponse est gardée en JSON : sur Redis, la clé
`{REDIS_PREFIX}{CACHE_PREFIX}iam:user:42` contient `{"id":42,"name":"Ada"}`, donc un programme dans un
autre langage peut la lire ou la supprimer.

## Changer un message

Un message a une version, 1 sauf si `#[Rpc(module: 'iam', version: 2)]` dit autre chose. Une nouvelle
version est une nouvelle classe. `iam` enregistre les deux dans `$rpcHandlers` et sert les deux le
temps que les appelants migrent :

```php
protected array $rpcHandlers = [
    FindUser::class => FindUserHandler::class,
    FindUserV2::class => FindUserV2Handler::class,
];
```

Une version qu’`iam` n’enregistre pas est refusée par un 400.

## Ce qui peut échouer

- **Un module ne peut pas utiliser les classes d’un autre.** Il utilise `IamClient` et les messages,
  jamais les modèles ni les handlers d’iam. `modules:doctor` et vos tests signalent la traversée.
- **Un module ne peut pas exécuter son code comme un autre.** `ModuleContext::within()`, appelé depuis
  le code d’un autre module ou depuis la fondation, lève `ModuleException`.
- **Un message non enregistré est refusé.** En HTTP, un message ou une version que le module ne liste
  pas dans `$rpcHandlers` répond 400.
- **Le chemin reste privé.** Une passerelle transmet les requêtes des clients aux routes d’API d’un
  module, jamais à `{module}/rpc/…`.

## En HTTP

Le transport `http` vient avec [`mk-josias/laravel-distributable-rpc-http`](/reference/rpc-http). Il
sert `POST {module}/rpc/{version}/{message}` pour chaque module que le processus exécute, avec le corps
`{"payload": {…}}`. Chaque appel est signé avec un secret partagé par tous les processus :
`rpc-http.secret`, qui lit `DISTRIBUTABLE_RPC_SECRET` et vaut `APP_KEY` par défaut. Quand les processus
ont leurs propres clés, réglez le même `DISTRIBUTABLE_RPC_SECRET` des deux côtés, sinon chaque appel
reçoit un 403.

Pour un autre protocole, voir [Écrire un pilote](/extend/driver).

## Ensuite

[Publier et traiter des événements](/fr/talk/events).
