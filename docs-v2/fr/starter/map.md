# Où sont les fichiers du starter

À la fin de cette page, vous trouvez dans le starter le fichier qui fait ce que vous cherchez.

## Le module `iam`

- `app/Models/User.php` : l’utilisateur, stocké dans la table `iam_users`. Le champ `name` est copié
  dans les modules qui gardent une copie.
- `app/Actions/RegisterUser.php` : crée l’utilisateur et émet `iam.user.registered`.
- `app/Actions/LoginUser.php` : vérifie l’email et le mot de passe, et émet la session : le même refus
  pour un email inconnu et un mauvais mot de passe.
- `app/Actions/IssueToken.php` : émet le jeton de l’utilisateur : un JWT, ou un jeton opaque avec
  `AUTH_TOKEN_VALIDATION_STRATEGY=issuer`.
- `app/Dto/` : ce que reçoit chaque action, construit par le `toDto()` de la requête.
- `app/Repositories/UserRepository.php` : l’unique chemin d’écriture d’un utilisateur : il oublie les
  réponses en cache et réécrit la copie de la passerelle une fois le changement validé.
- `app/Http/Controllers/Api/AuthController.php` : `POST /iam/api/v1/register`, `login`, `refresh`,
  `logout`, `logout-all`, et `GET /iam/api/v1/me`.
- `app/Rpc/*Handler.php` : répondent aux messages `FindUser`, `FindUserByToken` et `Grants` avec les
  données propres d’iam. Déclarés dans `IamServiceProvider::$rpcHandlers`, ils servent tous les
  appelants, dans ce processus ou dans un autre.
- `config/database.php` : la connexion `iam`, et le rôle `owner` qui la migre.
- `config/auth.php` : nomme `User` comme l’utilisateur authentifié des routes d’iam.

## La fondation

`foundation/Iam/` contient ce que les autres modules ont le droit d’utiliser d’`iam` :

- `Rpc/FindUser.php`, `FindUserByToken.php`, `Grants.php` : les messages auxquels iam répond, avec les
  règles qui les valident.
- `Services/IamClient.php` : tout module appelle `iam` par lui : dans ce processus quand `iam` s’exécute
  ici, sur le transport du module sinon (HTTP avec `rpc-http`).
- `Auth/TrustedIdentity.php` : lit et signe `X-Identity`, la seule chose qu’un module authentifie (voir
  [Authentification](/fr/starter/authentication)).
- `Auth/JwtTokens.php` : vérifie un JWT qu’iam a émis, pour le handler de `FindUserByToken`.
- `Auth/RpcPrincipals.php` : l’utilisateur de la requête tel qu’iam répond à `findUser()`, pour un
  module qui ne garde aucune copie.
- `Events/IamEvent.php`, `Events/UserRegisteredPayload.php` : les noms des événements qu’iam publie, et
  la charge utile typée de chacun : iam la construit, un consommateur la lit avec
  `UserRegisteredPayload::from($payload)`. La classe de charge utile déclare aussi sa version
  (`#[Payload]`) et comment remonter une plus ancienne (`upcast()`), si bien qu’un handler ne reçoit
  jamais que la forme courante.
- `Shadows/UserShadow.php` : la copie des utilisateurs d’iam, utilisée telle quelle par chaque module qui
  la liste dans `$shadows`, et leur utilisateur authentifié (`auth.principal`).
- `database/shadows/` : la migration qui crée la table de la copie dans le module qui la garde.

Le paquet exécute chaque handler dans le contexte d’iam, donc `Apps\Iam\Rpc\FindUserHandler` interroge la
connexion d’iam quel que soit le module qui l’appelle.

## Le module `analytics`

- `app/Handlers/RecordSignup.php` : traite `iam.user.registered` et stocke une inscription.
- `app/Providers/AnalyticsServiceProvider.php` : `$shadows = [UserShadow::class]` : analytics garde les
  utilisateurs d’iam dans sa propre table `iam_users`.
- `app/Console/ComputeDatasets.php` : `analytics:compute` reconstruit `analytics_datasets`, une ligne par
  heure, à partir des inscriptions. `AnalyticsServiceProvider` le planifie chaque heure.
- `app/Enums/DatasetMeasure.php`, `MeasureNature.php` : les mesures, et comment une période les replie :
  un flux (`signups_count`) s’additionne, un état (`users_total`) garde la dernière valeur.
- `app/Http/Controllers/Api/MetricController.php`, `app/Repositories/DatasetRepository.php` : `GET
  /analytics/api/v1/metrics?group=hour\|day\|week\|month\|none&measures[]=…&filter[from]=…&filter[to]=…`
  replie les lignes horaires sur le regroupement ; `measure()` replie une mesure sur une période.
- `app/Enums/DatasetGroup.php`, `app/Dto/`, `app/Database/MonthlyPartitions.php` : les regroupements, ce
  que reçoit la lecture (construit par le `toDto()` de la requête), et les partitions mensuelles de la
  table des datasets.
- `app/Providers/AnalyticsServiceProvider.php` : déclare le handler dans `$streamHandlers` et planifie
  `analytics:compute`.

Une lecture ne parcourt jamais les inscriptions : elle filtre et replie les lignes précalculées, donc son
coût dépend de la période, pas du volume.

## Le module `notifications`

- `app/Handlers/SendWelcome.php` : traite `iam.user.registered` et stocke un message de bienvenue pour le
  nouvel utilisateur, une seule fois quel que soit le nombre de fois où l’événement arrive.
- `app/Models/Notification.php` : ce qu’une personne a appris, dans `notifications_inbox`. Elle est
  adressée à son destinataire, donc chaque requête se limite à l’utilisateur authentifié : aucune policy
  à écrire.
- `app/Enums/NotificationType.php` : de quoi parle une notification ; elle produit le titre à partir de
  `lang/en/messages.php` et de la copie du destinataire.
- `app/Http/Controllers/Api/NotificationController.php` : `GET
  /notifications/api/v1/notifications?filter[unread]=1&sort=-created_at&paginate=20` et `PATCH
  /notifications/api/v1/notifications/{id}/read`. Les deux se limitent au destinataire : la notification
  de quelqu’un d’autre est un 404.
- `app/Actions/MarkNotificationRead.php` : marque comme lue une notification du destinataire.
- `app/Dto/`, `app/Http/Requests/` : ce que reçoivent la boîte de réception et l’action, construit par le
  `toDto()` de la requête.
- `app/Repositories/NotificationRepository.php` : l’exemple d’`EloquentRepository` : il déclare les filtres
  et tris qu’une requête peut utiliser (tout autre est un 400), limite la boîte à son destinataire, et
  est l’unique endroit où une notification est marquée lue.
- `app/Providers/NotificationsServiceProvider.php` : `$shadows = [UserShadow::class]` : sa copie des
  utilisateurs d’iam, dans `iam_users`, l’utilisateur authentifié de ses routes.
- `app/Observers/NotificationObserver.php` : une fois la ligne validée : la pousse en direct, puis met en
  file un job par canal que nomme son type.
- `app/Events/NotificationPushed.php` : la poussée en direct, sur `private-user.{id}` (Reverb), dans la
  forme que renvoie la boîte de réception ; un client qui était hors ligne la retrouve dans la boîte.
- `app/Enums/Channel.php`, `app/Jobs/SendMail.php` : les canaux au-delà de la boîte de réception.
  `SendMail` lit l’adresse dans la copie des utilisateurs d’iam du module (`UserShadow`), donc un email
  part même quand iam est en panne. Un nouveau canal (SMS, push) est un cas et un job.
- `routes/api.php` : aussi `POST /notifications/api/v1/broadcasting/auth` : Echo rejoint
  `private-user.{id}` avec le jeton d’API ; seul cet utilisateur peut le rejoindre
  (`Foundation\Common\Broadcasting\PrivateUserChannel`).

```
iam ─ user.registered ─► consommateur ─► SendWelcome ─► notifications_inbox
                                                      ├─► NotificationPushed ─► Reverb :8080 ─► navigateur
                                                      └─► SendMail (file) ─► worker ─► email
```

## `foundation/Common`

`Foundation\Common\` contient les classes de base que tout module utilise. Comme le reste de la
fondation, n’importe quel module peut l’utiliser. Ces classes sont à vous : le paquet n’en dépend pas,
vous pouvez donc les changer ou les supprimer.

Chaque élément ci-dessous finit par une étiquette qui dit ce que vous pouvez en faire :

- **Mécanisme :** les modules comptent sur son fonctionnement ainsi, d’un processus à l’autre. Ne le
  remplacez que par quelque chose qui garde la même garantie.
- **Par défaut :** un choix raisonnable qui fonctionne tel que livré. Changez-le librement.
- **Proposition :** une façon de structurer le code, montrée pour que vous la jugiez.
- **Goût :** un choix de style. Rien n’en dépend.

- `Http/Controllers/Controller`, `Http/Requests/ApiRequest`, `Http/Resources/Resource`,
  `Enums/ApiErrorCode` : contrôleur et requête de base, et le format de réponse : `{success, data}` ou
  `{success, code, message}`. *(Goût)*
- `Http/Middleware/Authenticate`, `Contracts/Auth/TokenValidator`, `PrincipalResolver`,
  `PermissionSource`, `Principal`, `Auth/Identity` : l’authentification par jeton des routes des modules,
  en trois étapes remplaçables : voir [Authentification](/fr/starter/authentication). *(Mécanisme)*
- `Auth/LocalPrincipals`, `ClaimsPrincipals`, `ClaimsPermissions`, `Traits/IsPrincipal`,
  `HasPrincipal` : les réponses livrées à ces étapes, et les traits qu’utilisent un modèle d’utilisateur
  et un contrôleur. *(Par défaut)*
- `Policies/Policy` : vérifie une permission auprès du `PermissionSource`. *(Mécanisme)*
- `Contracts/Actions/Action`, `Operation` : une classe par cas d’usage. *(Proposition)*
- `Contracts/Database/Repository`, `Repositories/EloquentRepository`, `Traits/Searchable` : des requêtes
  avec filtres, tris et inclusions, et un endroit unique pour les écritures. *(Proposition)*
- `Dto/Dto` : entrées et charges utiles d’événements typées, construites sur spatie/laravel-data.
  *(Par défaut)*
- `Validation/ReferenceRule` : un champ qui contient l’identifiant d’un enregistrement d’un autre module :
  vérifie qu’il existe par le client de ce module, puis applique les contraintes qu’une sous-classe ajoute
  à `$checks`. *(Mécanisme)*
- `Exceptions/DomainException`, `IntegrityException` : erreurs métier (400) et erreurs système (500).
  *(Goût)*

`bootstrap/app.php` rend les exceptions qui implémentent `RendersApiEnvelope` dans le format de réponse
ci-dessus.
