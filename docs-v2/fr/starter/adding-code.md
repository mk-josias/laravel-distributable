# Ajouter du code à un module

À la fin de cette page, vous avez ajouté un module, une classe ou une bibliothèque au starter.

Les commandes `make:*` de Laravel acceptent `--module` :

```bash
# un nouveau module, déclaré dans config/distributable.php
php artisan modules:make <module>
# apps/Notifications/app/Models, sa migration et sa factory
php artisan make:model Preference -mf --module=notifications
php artisan make:controller PreferenceController --module=notifications
```

Le `composer.json` racine liste la fondation sous `autoload.psr-4`, et le `composer.json` propre à chaque
module ses classes. L’application n’a pas besoin de ces entrées : le paquet charge lui-même les modules.
Elles servent à votre IDE pour résoudre les classes, par la fusion décrite plus bas ; un nouveau module a
besoin des siennes, comme dans `apps/Iam/composer.json`.

Une bibliothèque qu’un seul module utilise va dans le `composer.json` de ce module, comme
`spatie/laravel-permission` dans `apps/Iam/composer.json`. Le `composer.json` racine fusionne
`apps/*/composer.json` (`wikimedia/composer-merge-plugin`), donc il reste un seul `composer.lock` et un
seul `vendor/` :

```bash
# la déclare dans le module
composer require league/csv --working-dir=apps/Analytics --no-update
# la verrouille et l'installe
composer update league/csv
```

Ce que tous les modules utilisent (`foundation/`) reste dans le fichier racine. Une image construite pour
un module laisse de côté les paquets que seuls les autres modules ont déclarés : l’image d’analytics n’a
pas `spatie/laravel-permission`.
