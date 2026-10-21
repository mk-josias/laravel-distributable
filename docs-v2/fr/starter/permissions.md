# Permissions

À la fin de cette page, un module déclare ses permissions et une policy les vérifie.

Les rôles et les permissions appartiennent à `iam`, qui les garde avec `spatie/laravel-permission` dans
ses tables `iam_*`. Tout autre module les demande par `IamClient::grants($userId)`. Cet appel passe par
RPC et la réponse est mise en cache.

- Un module déclare ses permissions dans une enum de la fondation, par exemple
  `Foundation\Analytics\Enums\AnalyticsPermission`, et la liste dans `auth.permissions`.
  `php artisan iam:sync-permissions` crée alors les permissions déclarées et vide chaque droit en cache.
  Avec `--prune`, il supprime aussi les permissions qu’aucune enum ne déclare, ainsi que ce que chaque
  rôle et chaque utilisateur en détient : une enum absente de la liste perd ses droits. `composer setup`,
  `create-project` et le conteneur dont `SYNC_PERMISSIONS` vaut `true` (celui d’iam) le lancent avec
  `--prune`.
- Une policy étend `Foundation\Common\Policies\Policy` et vérifie
  `$this->allows($user, AnalyticsPermission::ReadDatasets)`. Un contrôleur appelle
  `$this->authorize('viewAny', Dataset::class)`, qui répond 403 quand l’utilisateur n’a pas la
  permission.
- Ne changez les permissions que par les actions d’iam, jamais en appelant spatie directement, pour que
  le cache reste juste. Chaque action vide le cache une fois sa transaction validée :

  - `GrantRole::grant()` et `revoke()` vident les droits en cache de cet utilisateur
  - `SetRolePermissions::execute()` vide les droits en cache de tous les utilisateurs
