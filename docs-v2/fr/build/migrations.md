# Migrations

À la fin de cette page, `php artisan migrate --module='*'` a créé les tables de chaque module sur sa
propre connexion, et vous savez où va chaque sorte de migration.

Chaque module a sa propre connexion, donc `migrate` s’exécute dans un module : ceux que nomme
`--module`, ou chaque module que le processus exécute avec `--module=*`. Sans l’option, il
s’exécute dans aucun module et lève une exception, comme toute requête à cet endroit : aucune
connexion n’appartient à aucun module. Dans un terminal, écrivez `--module='*'` : sans guillemets,
zsh lit `*` comme des noms de fichiers et s’arrête sur `no matches found`.

## Ce qui s’exécute sur la connexion d’un module

Quatre sources alimentent les tables d’un module :

- **L’application**
  - Où elle vit : `database/migrations`
  - Dans le starter : `failed_jobs` et `job_batches`
  - Pourquoi elle est sur chaque module : un job échoué ou un lot est enregistré sur la connexion du
    module qui possède la classe du job, donc chaque module a besoin de la table
- **Les paquets**
  - Où elle vit : les chemins qu’un paquet enregistre
  - Dans le starter : le paquet : `event_consumptions`, sauf si la garde est coupée ;
    `event_publications` et sa colonne `version`, seulement quand
    `DISTRIBUTABLE_EVENTS_RELIABLE_PUBLISH` est actif
  - Pourquoi elle est sur chaque module : la ligne de publication est écrite sur la connexion du
    module émetteur, dans sa transaction ; la marque de consommation est écrite sur la connexion du
    module consommateur
- **Le module**
  - Où elle vit : `{module}/database/migrations`, et les dossiers que son provider charge avec
    `loadMigrationsFrom()`
  - Dans le starter : iam : ses utilisateurs, ses tables de permissions et ses jetons d’accès
  - Pourquoi : les tables propres du module ; aucun autre module ne peut les nommer
- **Les copies**
  - Où elle vit : les fichiers ou dossiers que nomme le `migrations()` de la copie
  - Dans le starter : `iam_users`, copiée dans analytics et notifications
  - Pourquoi : le propriétaire publie la migration, chaque module qui garde la copie l’exécute

Laravel trie toutes les migrations d’une exécution par nom de fichier, quelle que soit leur source. Les
quatre sources ci-dessus disent d’où elles viennent, pas dans quel ordre elles s’exécutent.

## Le starter, module par module

- **`iam` :** 4 fichiers. Ceux de l’application (`failed_jobs` et `job_batches`), puis les siens :
  `iam_users`, les tables de permissions et `iam_access_tokens`. Il ne garde aucune copie.
- **`analytics` :** 5 fichiers. Ceux de l’application, puis ses propres `analytics_signups`,
  `analytics_mails` et `analytics_datasets`, puis la copie d’`iam_users`.
- **`notifications` :** 3 fichiers. Ceux de l’application, sa propre `notifications_inbox`, puis la
  copie d’`iam_users`.

Avec les réglages du starter, le paquet n’ajoute aucune table : les événements ne sont pas publiés de
façon fiable et la garde est coupée. `DISTRIBUTABLE_EVENTS_RELIABLE_PUBLISH=true` ajoute deux
migrations (`event_publications` et sa colonne `version`), et retirer
`DISTRIBUTABLE_STREAM_GUARD=false` ajoute `event_consumptions`. La copie existe dans `analytics` et
`notifications` parce que leurs providers listent `UserShadow` dans `$shadows` ; iam possède la table
et ne garde aucune copie.

## Les exécuter

```bash
# chaque module que ce processus exécute, une passe chacun
php artisan migrate --module='*'

# ces modules seulement ; l'option peut être répétée
php artisan migrate --module=analytics

# ce qui a été exécuté, et ce qui reste en attente, pour chaque module
php artisan migrate:status --module='*'
```

Ce sont les commandes de Laravel, comme `migrate:rollback`, `migrate:reset`, `migrate:refresh` et
`migrate:fresh` : elles prennent `--module` comme toute commande. `--seed` alimente chaque module avec
le `DatabaseSeeder` de l’application, puis celui du module.

- **La connexion :** le `default` du module. `--database` nomme une autre connexion, dans chaque
  module nommé : `migrate --module='*' --database=owner` migre chaque module sur la connexion `owner`
  qu’il déclare (voir [Les réglages d’un module](/fr/build/settings#la-connexion))
- **L’historique :** chaque connexion garde sa propre table `migrations`
- **`--path` :** seulement ces dossiers, dans chaque module nommé
- **Les tests :** `RefreshDatabase` lance `migrate:fresh` avant que le test entre dans son module :
  voir [Tester un module](/fr/build/testing#migrer-les-bases)

## Écrire une migration

Une migration des tables propres au module va dans le module :

```bash
php artisan make:migration add_archived_at_to_notifications_inbox --module=notifications
```

Elle atterrit dans `apps/Notifications/database/migrations`.

La migration d’une copie est publiée par le module qui possède la table. La copie dit où sont ses
migrations dans `#[Shadow(migrations: …)]`, à côté du `module:` et du `table:` : des fichiers ou des
dossiers, comme le `loadMigrationsFrom()` de Laravel. Un dossier apporte toutes les migrations qu’il
contient, quel que soit leur nom de fichier.

```php
// foundation/Iam/Shadows/UserShadow.php
#[Shadow(module: 'iam', table: 'iam_users', migrations: [__DIR__.'/../database/shadows/users'])]
class UserShadow extends ShadowModel
{
}
```

La migration est une migration Laravel ordinaire de la table de la copie :

```php
// foundation/Iam/database/shadows/users/2026_01_01_000100_create_iam_users_shadow_table.php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create((new UserShadow)->getTable(), function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((new UserShadow)->getTable());
    }
};
```

La table source est écrite une seule fois, dans le `sourceTable()` de la copie : la renommer ne
déplace ni le dossier ni la migration. Un module dont la sous-classe de la copie règle son propre
`$table` surcharge aussi `migrations()`, et nomme une migration de son propre `database/migrations`.
`modules:doctor` signale un chemin que nomme une copie gardée et qui ne contient aucune migration.

## Limites

- Un module sans connexion n’a pas de migrations. `modules:doctor` signale un module qui en a.
  `--module=*`, dans un processus qui en exécute un, lève une exception dans ce module, comme toute
  requête à cet endroit : nommez les autres.
- Le `database/migrations` de l’application s’exécute sur chaque module. Gardez ce dossier à ce dont
  chaque module a besoin.
- Un module ne peut pas nommer une table que les migrations d’un autre module créent. `Boundaries` le
  signale. La table d’une copie que le module garde est la sienne.
- Les pilotes `database` du cache, de la file et de la session ont besoin d’un `connection` qui nomme
  celle d’un module : hors d’un module, la connexion par défaut est `no_module`.

## Ensuite

[Tester un module](/fr/build/testing).
