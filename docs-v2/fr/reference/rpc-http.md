# RPC en HTTP

Le pilote RPC `http` de [laravel-distributable](https://github.com/mk-josias/laravel-distributable/tree/main/packages/core) : des appels signés entre modules.

```bash
composer require mk-josias/laravel-distributable-rpc-http
```

Son provider est découvert. Il enregistre le pilote du transport `http` fourni, et sert
`POST {module}/rpc/{version}/{message}` pour chaque module que ce processus exécute. Ses réglages
sont dans `config/rpc-http.php` (`php artisan vendor:publish --tag=rpc-http-config`). Il déclare le
transport `http` et le prend par défaut quand `distributable.rpc.transport` n’en nomme aucun. Le
secret est partagé par l’appelant et l’appelé :

```php
return [
    // signe chaque appel ; se rabat sur APP_KEY
    'secret' => env('DISTRIBUTABLE_RPC_SECRET', env('APP_KEY', '')),
    // le même dans chaque processus
    'path' => env('DISTRIBUTABLE_RPC_PATH', '{module}/rpc/{version}/{message}'),
    // le groupe de ces routes, qui vérifie la signature
    'middleware_group' => 'rpc',
    // le port sur lequel le RPC répond, dans un processus à lui (`php artisan rpc:serve`) ;
    // null : là où l'application est servie
    'port' => env('DISTRIBUTABLE_RPC_PORT'),
    // secondes pendant lesquelles une signature reste valide
    'signature_ttl' => 30,
];
```

- **Signé :** un HMAC de l’horodatage, d’un nonce, du chemin, du corps et du contexte transmis
- **Frais :** un appel plus vieux que `signature_ttl` est refusé
- **Une fois :** chaque nonce n’est accepté qu’une fois, grâce au cache
- **Réponses :** 200 la valeur de retour en JSON · 404 `null` · 400 message ou version inconnus ·
  403 mauvaise signature · 422 une charge utile que ses règles refusent

Ce qu’un programme écrit dans un autre langage doit envoyer est dans
[Programmes dans d’autres langages](/fr/talk/other-languages).
