# Une nouvelle application

À la fin de cette page, vous avez une application Laravel avec un module `payments` qui répond à une
route, sur sa propre connexion.

## Installer le paquet

```bash
composer require mk-josias/laravel-distributable
# le pilote d'événements : Redis Streams (ou le vôtre) ; DISTRIBUTABLE_STREAM_DRIVER=redis
composer require mk-josias/laravel-distributable-stream-redis
# les appels vers un module qui tourne dans un autre processus
composer require mk-josias/laravel-distributable-rpc-http
# config/distributable.php et le dossier des modules
php artisan modules:install
```

Les deux pilotes comptent quand des modules échangent des événements, ou tournent dans des
processus séparés. Un module seul n’en a pas encore besoin.

## Créer le premier module

```bash
php artisan modules:make payments
```

```
apps/Payments/
├── app/
│   ├── Models/
│   └── Providers/PaymentsServiceProvider.php
├── bootstrap/providers.php
├── config/database.php
├── database/migrations/
├── routes/api.php
└── tests/Feature/
```

Il est aussi déclaré dans `config/distributable.php`. Tout dans le module est du Laravel ordinaire,
organisé comme une application : un modèle dans `app/Models`, une route dans `routes/api.php`, une
migration dans `database/migrations`. Le paquet les trouve à partir du dossier.

## Lui donner sa connexion

`config/database.php` nomme la connexion sur laquelle s’exécute le code du module. Modifiez-le pour
qu’il pointe vers la vôtre :

```php
// apps/Payments/config/database.php
return [
    'default' => 'payments',
    'connections' => [
        'payments' => ['driver' => 'pgsql', 'database' => 'payments', 'username' => 'payments_app'],
    ],
];
```

## Écrire une route et un modèle

```bash
php artisan make:model Payment -mf --module=payments
php artisan make:controller PaymentController --module=payments
php artisan migrate --module=payments
```

`routes/api.php` est servie sous `/payments/api/…`. Le modèle n’a pas besoin de connexion : tant que le
code de `payments` s’exécute, la connexion par défaut de Laravel est `payments`.

## Le vérifier

```bash
php artisan modules:list
php artisan modules:doctor
```

## Ensuite

- **Ajouter un deuxième module et les faire se parler :** [Appel, événement ou copie ?](/fr/talk/choosing).
- **Comprendre ce que vous venez de mettre en place :** [Créer un module](/fr/build/create),
  [Les réglages d’un module](/fr/build/settings).
