# Sortir un module

À la fin de cette page, un module qui était avec d’autres s’exécute dans sa propre application, et les
autres l’appellent toujours.

Sortir un module change l’endroit où vit son code et dit aux autres où le trouver. Ce qu’il fait ne
change pas : les messages auxquels il répond, les événements qu’il émet et les copies de ses lignes
restent dans la fondation, et rien de ce qui l’appelle n’est réécrit. Voir
[Où s’exécutent les modules](/fr/run/where) pour les deux organisations.

## Ce qui bouge

| Dans l’application de plusieurs modules | Dans sa propre application |
|---|---|
| `apps/Iam/app/`, sous `Apps\Iam\` | `app/`, sous `App\` |
| `apps/Iam/config/` | `config/` |
| `apps/Iam/routes/` | `routes/`, chargées par `bootstrap/app.php` |
| `apps/Iam/database/` | `database/` |
| `apps/Iam/tests/` | `tests/` |
| `apps/Iam/composer.json` | le `require` du `composer.json` de l’application |

La seule ligne de code qui change est l’espace de noms : `Apps\Iam\` devient `App\`. La nouvelle
application requiert la fondation, le paquet et les pilotes que le module utilise.

## Ce que déclare la nouvelle application

```php
// config/distributable.php
'declared' => [
    'iam' => ['path' => '.', 'namespace' => 'App'],
    // chaque module qu'elle appelle : où il répond
    'analytics' => ['host' => env('ANALYTICS_HOST')],
],

'runs' => env('RUN_MODULES', 'iam'),
```

Son `config/` et ses `routes/` sont ceux de l’application, voir
[Où vit le code d’un module](/fr/run/where#ou-vit-le-code-d-un-module).

## Ce que changent les autres

Chaque application qui continue d’appeler le module abandonne son code et déclare où il répond :

```bash
# supprime apps/Iam et sa déclaration ; ses données restent telles quelles
php artisan modules:delete iam --force
```

```php
// config/distributable.php, dans les applications qui appellent iam
'iam' => ['host' => env('IAM_HOST')],
```

Toutes les applications partagent le flux d’événements et le même `DISTRIBUTABLE_RPC_SECRET`, voir
[Où s’exécutent les modules](/fr/run/where#ce-dont-une-application-a-besoin-pour-appeler-un-module-qui-s-execute-ailleurs).
Lancez `modules:doctor` dans chacune : il signale un module qui sert des messages sans hôte.

## Ensuite

[Déployer les processus](/fr/run/deploying).
