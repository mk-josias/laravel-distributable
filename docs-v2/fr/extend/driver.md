# Écrire un pilote

À la fin de cette page, vous savez quoi implémenter pour faire passer les appels ou les événements par un autre protocole ou un autre broker.

Les appels et les événements passent par des pilotes installés à côté du paquet :
[`http`](/fr/reference/rpc-http) pour les appels, [`redis`](/fr/reference/stream-redis) pour les
événements. Cette page sert à écrire le vôtre, sur un autre broker ou un autre protocole.

## Un transport pour les appels

Enregistrez-le sous un nom, et nommez-le sur le module qu’il atteint :

```php
// config/distributable.php : seulement le nom du transport, et le module qui l'utilise
'declared' => ['payments' => ['host' => ['url' => 'grpc://payments.internal', 'transport' => 'grpc']]],
'rpc'      => ['transports' => ['grpc' => ['driver' => 'grpc']]],

// config/rpc-grpc.php : les réglages propres au pilote, comme config/rpc-http.php
return ['port' => env('DISTRIBUTABLE_RPC_PORT', 50051)];
```

```php
// dans un service provider ; GrpcTransport est à vous d'écrire
app(\Distributable\Services\Rpc\TransportManager::class)
    ->extend('grpc', fn ($app) => new GrpcTransport(config('rpc-grpc')));
```

Le pilote lit ses réglages dans son propre fichier, comme `rpc-http` et `stream-redis`.
`distributable.rpc.transports` ne fait que lui donner un nom.

Le côté appelant implémente `Contracts\Rpc\Transport::invoke(string $module, string $name,
int $version, array $payload)`. Le côté appelé passe les mêmes quatre valeurs à
`MessageRegistry::answer()`, comme le fait le point d’entrée HTTP.

Deux interfaces facultatives :

- `Contracts\Rpc\ServesRpc` : le transport répond dans un processus à lui, que `rpc:serve` démarre ;
- `Contracts\Rpc\ReportsRpcProblems` : le transport sait dire ce qui empêche ses appels de
  fonctionner, et `modules:doctor` le signale.

## Un transport pour les événements

```php
// dans un service provider
app(\Distributable\Services\Stream\TransportManager::class)
    ->extend('kafka', fn ($app, array $options, string $stream) => new KafkaTransport(config('stream-kafka')));
```

Un flux de `distributable.events.streams` l’utilise alors avec `'driver' => 'kafka'` ; ses brokers
et ses autres réglages sont dans le fichier propre au pilote, `config/stream-kafka.php`.

Il implémente `Contracts\Stream\Transport` :

- `publish()` écrit l’enveloppe comme le fait `Envelope::toJson()`, pour que les programmes dans
  d’autres langages et les autres pilotes lisent la même chose ;
- `consume()` boucle jusqu’à `stop()`, garde un curseur par consommateur, et passe au callback
  chaque enveloppe dont l’émetteur fait partie des canaux, avec le consommateur pour lequel elle a
  été lue. Quand le callback retourne, l’entrée est acquittée ; quand il lève une exception, elle ne
  l’est pas. Un consommateur qui échoue ne doit pas retenir les autres ;
- `stop()` laisse l’entrée en cours se terminer, puis sort de `consume()` : c’est ce qu’appelle un
  `SIGTERM`.

Il devrait tenir trois garanties, et dire dans son README lesquelles il tient :

- **l’ordre dans un flux, y compris après un échec.** Un consommateur qui lit une copie remplie par
  un événement antérieur (un mail de bienvenue qui lit la copie de l’utilisateur) en dépend. Sans
  elle, un événement peut doubler celui dont il dépend ;
- **au moins une fois** : une entrée non acquittée revient. Sans elle, un événement peut être perdu
  quand un handler ou un processus échoue ;
- **un curseur par consommateur** : un nouveau consommateur ne dérange aucun des autres. Sans elle,
  les consommateurs se volent leurs entrées.

Les commandes qui demandent davantage le demandent par une interface de
`Distributable\Contracts\Stream` :

- `TrimsStreams`, pour `stream:trim` ;
- `ArchivesStreams`, pour `stream:export`, qui lit les entrées que le flux garde ;
- `ReportsAcknowledgements`, pour `guard_cleanup => acknowledge`, qui supprime une marque de
  consommation une fois son entrée acquittée. Sans elle, les marques restent jusqu’à
  `stream:prune-consumptions` ;
- `RedeliversEnvelopes`, qui active la garde de consommation. Sans elle, la garde reste éteinte sauf
  si les événements sont publiés de façon fiable ;
- `SkipsEntries`, pour `stream:skip`.

Sans l’interface, la commande dit que le transport ne sait pas le faire.

## Un stockage d’archives

`stream:export` et `stream:import` écrivent dans un stockage : `file` est fourni, et
`distributable.events.archive` nomme celui par défaut. Un autre stockage implémente
`Contracts\Stream\ArchiveStore` (`write($target, $rows)`, `read($source)`) et s’enregistre par son
nom :

```php
app(\Distributable\Services\Stream\ArchiveStores::class)
    ->extend('s3', fn ($app) => new S3ArchiveStore($app->make('filesystem')->disk('s3')));
```
