# Garder une copie des données d’un autre module

À la fin de cette page, `analytics` liste ses inscriptions avec le nom de leurs utilisateurs en une
seule requête, sans appeler `iam` pour chaque ligne.

Un module a parfois besoin des lignes d’un autre module à côté des siennes, pour joindre, filtrer ou
trier dessus. Appeler l’autre module une fois par ligne serait lent, et atteindre ses tables est
interdit. Le module garde donc une copie parmi ses propres tables, tenue à jour par des événements.
Seul le propriétaire écrit les données.

## Copie ou appel

- Un **appel** (par un message) pour vérifier une valeur ou lire un enregistrement : il est toujours à
  jour, et coûte un appel sur le transport quand le propriétaire s’exécute ailleurs.
- Une **copie** pour les listes, les filtres et les tris : une requête locale, jointures comprises.
  Elle est à jour une fois que le consommateur a traité les événements.

Dans le starter, chaque module demande à iam les permissions d’un utilisateur par son message
(`Grants`), et analytics lit l’utilisateur d’une inscription dans la copie (`Signup::user()` est un
`belongsTo` sur `UserShadow`).

## Déclarer la copie

Trois pièces, une à chaque endroit.

Le propriétaire marque les champs qui quittent son module. Seuls ceux-là, et les dates de la ligne,
sont copiés : le `password` et les jetons d’iam ne le sont jamais.

```php
// apps/Iam/app/Models/User.php
#[ShadowFields('name', 'email')]
final class User extends Model implements Principal, Shadowed
{
    use ShadowSource;

    protected $table = 'iam_users';
}
```

Le propriétaire publie une fois la forme de la copie dans la fondation :

```php
// foundation/Iam/Shadows/UserShadow.php
#[Shadow(module: 'iam', table: 'iam_users', migrations: [__DIR__.'/../database/shadows/users'])]
class UserShadow extends ShadowModel implements Principal
{
}
```

Chaque module qui garde la copie la liste :

```php
// apps/Analytics/app/Providers/AnalyticsServiceProvider.php
protected array $shadows = [UserShadow::class];
```

C’est tout. `iam` annonce chaque utilisateur enregistré ou supprimé, et le consommateur d’analytics
l’écrit dans la table `iam_users` d’analytics :

```
iam : User enregistré ou supprimé
  └─ les champs #[ShadowFields] partent dans un événement
       └─ flux
            ├─ consommateur d'analytics ─► la copie, dans les tables d'analytics
            └─ consommateur de notifications ─► la copie, dans les tables de notifications
```

`UserShadow` est un modèle ordinaire qui utilise la connexion du module qui l’exécute : la même classe
est la copie d’analytics dans analytics et celle de notifications dans notifications.

Un module peut lister sa propre sous-classe qui ajoute des relations, des casts ou un `beforeSync()`.
Chaque classe listée est une table : deux formes d’une même source demandent deux `$table`, chacune
avec ses `migrations:`. `modules:doctor` signale deux copies dans une même table.

## Créer sa table

Le propriétaire publie la migration, et `migrations:` dans `#[Shadow]` dit où elle est : des fichiers
ou des dossiers, comme le `loadMigrationsFrom()` de Laravel. `php artisan migrate --module=*` l’exécute
dans chaque module qui garde la copie. Voir
[Migrations](/fr/build/migrations#ecrire-une-migration).

## La lire

Dans le starter, analytics et notifications gardent une copie des utilisateurs d’iam.
`config/auth.php` règle `auth.principal` sur `UserShadow` : avec le résolveur par défaut, un module
charge l’utilisateur authentifié d’une requête à partir de sa propre copie.

Une copie refuse ce qui la ferait s’écarter de sa source :

- elle rejette toute écriture qui ne vient pas des annonces du propriétaire ;
- une ligne supprimée à la source devient une suppression logique dans la copie ;
- `beforeSync()` dérive ce dont la copie a besoin et que la source n’a jamais annoncé ;
- `shouldBeShadowed()` ne garde que certaines lignes. Une ligne pour laquelle elle renvoie `false` n’est
  jamais écrite, et quitte la copie si elle s’y trouvait :

```php
// analytics ne garde que les administrateurs
protected static function shouldBeShadowed(array $attributes): bool
{
    return (bool) $attributes['is_admin'];
}
```

Une annonce qui arrive en retard, après une plus récente, est ignorée quand la ligne source et la copie
portent toutes deux une date : la plus récente de `updated_at` et `deleted_at`, à la seconde. Une source
sans ces colonnes n’annonce aucune date, et la copie garde ce qui est arrivé en dernier. Une telle copie
peut être écrasée par une annonce plus ancienne ; `shadows:announce` la répare.

## Remplir une copie créée tard

Une copie créée alors que la source avait déjà des lignes est vide. Remplissez-la :

```bash
# sur le module qui garde : demander aux propriétaires de renvoyer leurs lignes
php artisan shadows:want [--keepers=analytics] [--sources=iam_users]
# sur le propriétaire : renvoyer chaque ligne
php artisan shadows:announce iam_users [--keepers=analytics]
```

Les deux adressent l’événement aux modules qui gardent la copie, donc seuls ceux-là mettent la leur à
jour.

## Mettre une source sur son propre flux

Par défaut une ligne circule sur le flux par défaut, avec les autres événements. Un modèle source peut
nommer un autre flux avec `#[ShadowStream]` :

```php
// iam : les lignes de User vont sur le flux `shadows`, les autres événements restent sur le flux par défaut
#[ShadowFields('name', 'email')]
#[ShadowStream('shadows')]
final class User extends Model implements Principal, Shadowed
```

```php
// config/distributable.php, dans iam et dans chaque module qui garde la copie
'events' => ['streams' => [
    'default' => ['driver' => 'redis'],
    'shadows' => ['driver' => 'redis'],
]],
```

```bash
# sur celui qui garde : un consommateur par flux
php artisan stream:consume --stream=shadows
```

Celui qui garde la copie doit lire ce flux, sinon sa copie reste vide.

L’ordre n’est gardé qu’à l’intérieur d’un flux. Un handler qui lit une copie qu’un événement antérieur
devait remplir (un mail de bienvenue qui lit la copie de l’utilisateur) a besoin que la source de cette
copie et son propre événement soient sur le même flux : sur deux flux, l’événement peut dépasser la
copie.

## Un programme dans un autre langage comme source

Un programme peut posséder les lignes : voir
[Programmes dans d’autres langages](/fr/talk/other-languages#etre-la-source-d-une-copie).

## Ensuite

[Programmes dans d’autres langages](/fr/talk/other-languages), ou [où s’exécutent les
modules](/fr/run/where).
