# Authentification

À la fin de cette page, vous savez qui vérifie le jeton d’un client, ce qu’un module lit à la place, et
comment brancher votre propre fournisseur d’identité.

Seule la passerelle vérifie le jeton du client. Un module ne lit que `X-Identity`, que la passerelle
ajoute une fois le jeton vérifié : les claims du jeton, signés avec `AUTH_GATEWAY_SECRET`.

```
client ── Bearer <token> ──► passerelle (gateway/, Traefik) ── X-Identity ──► module
                              vérifie le jeton                               vérifie la signature,
                              signe ses claims                               puis charge l'utilisateur
```

`X-Identity` vaut `{payload}.{hmac}` : le payload est l’ensemble des claims en JSON base64url, dont
`sub` et `exp`, et le hmac est `hex(hmac_sha256(AUTH_GATEWAY_SECRET, payload))`
(`Foundation\Iam\Auth\TrustedIdentity`). Il dure une minute.

Une route est protégée par le middleware `Foundation\Common\Http\Middleware\Authenticate` :

```php
Route::get('me', [AuthController::class, 'me'])->middleware(Authenticate::class);
```

Il répond à trois questions à la suite, les deux dernières par des classes que nomme `config/auth.php` :

```
requête ─► X-Identity ─► Identity ─► PrincipalResolver ─► Principal ─► PermissionSource ─► Policy
         TrustedIdentity  sub, claims  où est l'utilisateur ?          que peut-il faire ?
```

- **`PrincipalResolver`**
  - Clé de configuration : `auth.principal_resolver`
  - Fournis : `LocalPrincipals` : la ligne de l’utilisateur sur la connexion propre du module qui
    s’exécute, par `auth.principal` : `UserShadow` dans les modules qui en gardent une copie, `User`
    dans iam, qui le définit dans son `config/auth.php`. `ClaimsPrincipals` : l’utilisateur est
    construit à partir des claims, sans ligne. `RpcPrincipals` : l’utilisateur que renvoie iam pour
    l’identifiant
  - Pour ajouter la vôtre : nommez votre classe
- **`PermissionSource`**
  - Clé de configuration : `auth.permission_source`
  - Fournis : `RpcPermissions` : `IamClient::grants()`. `ClaimsPermissions` : le claim `permissions`
  - Pour ajouter la vôtre : nommez votre classe

Une requête est refusée (401, l’`AuthenticationException` de Laravel) quand son `X-Identity` est absent,
expiré ou non signé avec le secret. Avec `LocalPrincipals`, elle est aussi refusée tant que le module n’a
pas de copie de l’utilisateur : un utilisateur qui vient de s’inscrire atteint `analytics` une fois que
celui-ci a consommé `iam.user.registered`.

Dans un contrôleur qui étend `Foundation\Common\Http\Controllers\Controller`, `$this->principalId()`
renvoie l’identifiant de l’utilisateur authentifié.

## Comment la passerelle valide un jeton

`AUTH_TOKEN_VALIDATION_STRATEGY` dit comment la passerelle vérifie le jeton du client. iam la lit aussi,
pour émettre le jeton que cette stratégie vérifie (`POST /iam/api/v1/login` et `/refresh`) : les deux
doivent lire la même valeur.

- **`signature` (par défaut)**
  - Le jeton qu’iam émet : un JWT, `sub` = l’identifiant de l’utilisateur
  - La passerelle le vérifie : avec la clé publique d’iam, puis l’utilisateur en cache
  - Réglages : la paire de clés, dans `AUTH_JWT_KEYS_PATH` ou `AUTH_JWT_*_KEY`
- **`issuer`**
  - Le jeton qu’iam émet : un jeton d’accès personnel Sanctum ; iam en stocke l’empreinte, un par appareil
  - La passerelle le vérifie : en interrogeant iam, à chaque requête
  - Réglages : aucun

`AUTH_TOKEN_TTL` est la durée de vie d’un jeton, quelle que soit la stratégie. Avec `issuer`, une
déconnexion (`POST /iam/api/v1/logout`, ou `/logout-all` pour tous les appareils) arrête le jeton
aussitôt, donc il peut être long. Avec `signature`, la passerelle n’interroge jamais iam : une
déconnexion ne s’applique qu’à l’expiration du JWT, donc `AUTH_TOKEN_TTL` est aussi le délai maximal.
Gardez-le court dans ce cas, et réglez `AUTH_REFRESH_TOKEN_TTL` pour que le client le renouvelle sans
redemander le mot de passe : la connexion répond aussi un `refresh_token`, consommé une fois par
`POST /iam/api/v1/refresh`.

La passerelle interroge iam par RPC, avec le message `FindUserByToken` : pour un jeton valide de l’une ou
l’autre sorte, iam répond l’utilisateur et l’écrit pour que la passerelle le lise la fois suivante ; pour
tout autre, rien. La passerelle signe elle-même l’`X-Identity`.

## La passerelle

`gateway/` contient la passerelle du starter : Traefik, sa configuration et son plugin. Les deux fichiers
compose la lancent devant les modules : voir
[`gateway/README.md`](https://github.com/mk-josias/laravel-distributable-starter/blob/main/gateway/README.md).

```
client ─► Traefik ─► plugin d'authentification (gateway/plugins/auth)
                       signature  1. le JWT, vérifié avec la clé publique d'iam
                                  2. GET gateway:user:{id}, dans gateway-cache
                                       présent → ses claims signés ici, iam n'est pas appelé
                                       absent  → iam par RPC (FindUserByToken), écrit la clé
                       issuer     iam par RPC (FindUserByToken), à chaque fois
                     ─► le module, avec X-Identity
iam enregistre ou supprime un utilisateur ─► forgetUser() ─► la clé disparaît, la requête suivante interroge iam
```

Une passerelle à vous, dans n’importe quel langage, fait la même chose : elle transmet
`POST /iam/api/v1/register`, `/login` et `/refresh` tels quels, et ajoute `X-Identity` à toutes les
autres requêtes. Elle doit aussi :

- supprimer tout `X-Identity` que le client a envoyé ;
- ne jamais transmettre `/*/rpc/*` : ces routes acceptent tout appelant qui détient le secret RPC ;
- être le seul chemin d’entrée : quiconque atteint un module avec un `X-Identity` valide est cet
  utilisateur.

Pour que la passerelle émette des événements ou appelle elle-même les modules, voir
[Programmes dans d’autres langages](/fr/talk/other-languages).

## Un fournisseur d’identité hors de l’application

Les modules peuvent être le cœur d’un système plus vaste dont les utilisateurs sont gérés ailleurs (un
service Node, Keycloak, Auth0). Rien d’iam n’est alors nécessaire : la passerelle vérifie le jeton du
fournisseur et signe ses claims, qui disent qui est l’utilisateur et ce qu’il peut faire.

```yaml
# gateway/traefik/dynamic/middlewares.yml : la clé du fournisseur, et aucune recherche dans iam
publicKey: "-----BEGIN PUBLIC KEY-----…"
checkUser: false
```

```dotenv
# les modules : sans guillemets, une barre oblique inverse entre guillemets ne se lit pas
AUTH_PRINCIPAL_RESOLVER=Foundation\Common\Auth\ClaimsPrincipals
AUTH_PERMISSION_SOURCE=Foundation\Common\Auth\ClaimsPermissions
```

- **`sub` :** l’identifiant de l’utilisateur, un entier ou une chaîne (`usr_7f3a`)
- **`permissions` (`auth.permissions_claim`) :** la liste que vérifient les policies, comme
  `analytics.datasets.read`
- **tout autre claim :** l’utilisateur est un `ClaimsPrincipal` : `$principal->claim('email')`

`apps/Analytics/tests/Feature/ExternalIdentityTest.php` l’exécute. Pour garder malgré tout les noms des
utilisateurs en local, le fournisseur peut alimenter les copies des modules : voir
[Être la source d’une copie](/fr/talk/other-languages#etre-la-source-d-une-copie).

## Sans authentification

Le paquet n’a aucune authentification : tout vit dans ce starter, dans `foundation/Common/Auth`,
`foundation/Common/Contracts/Auth`, `foundation/Common/Http/Middleware/Authenticate.php`,
`foundation/Common/Policies` et `foundation/Iam/Auth`. Ceux-ci les utilisent :

- **`foundation/FoundationServiceProvider.php` :** les trois liaisons de `register()`
- **`apps/*/routes/api.php` :** le middleware `Authenticate`
- **`foundation/Common/Http/Controllers/Controller.php`, `Http/Requests/ApiRequest.php` :** `use
  HasPrincipal`
- **`apps/Iam/app/Models/User.php`, `foundation/Iam/Shadows/UserShadow.php` :** `IsPrincipal` et
  `implements Principal`
- **`apps/Analytics/app/Policies/DatasetPolicy.php` :** la policy, et `authorize()` dans
  `MetricController`
- **`tests/ModuleTestCase.php`, `apps/*/tests/Feature`, `tests/Integration` :** les tests qui envoient
  un jeton
