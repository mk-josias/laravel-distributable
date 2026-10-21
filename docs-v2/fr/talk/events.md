# Publier et traiter des événements

À la fin de cette page, `iam` annonce `iam.user.registered` et `analytics` le traite, dans le même
processus ou dans deux.

Un événement annonce que quelque chose s’est produit dans un module : iam annonce qu’un utilisateur
s’est inscrit, analytics enregistre une inscription, notifications envoie un message de bienvenue.
iam n’attend pas les autres et ne sait pas qui écoute. Les événements circulent sur un flux même quand
les modules s’exécutent dans le même processus, donc ils se comportent de la même façon dans tous les
montages.

```
iam : emit(UserRegistered) ─► flux ─┬─► analytics : RecordSignup
                                    └─► notifications : SendWelcome
```

## Publier un événement

Le nom de l’événement est partagé avec les modules qui écoutent, dans la fondation d’iam :

```php
// foundation/Iam/Events/IamEvent.php
enum IamEvent: string
{
    case UserRegistered = 'iam.user.registered';
}
```

La classe de l’événement vit dans iam, et dit ce qu’elle est avec `#[Emits]`, comme un message le fait
avec `#[Rpc]` :

```php
// apps/Iam/app/Events/UserRegistered.php
#[Emits(IamEvent::UserRegistered)]
final class UserRegistered extends Event
{
    public function __construct(private readonly UserRegisteredPayload $user) {}

    public function payload(): array
    {
        return $this->user->toArray();
    }
}
```

`#[Emits]` prend le nom de l’événement (le cas de l’énumération, ou une chaîne), et deux valeurs
facultatives : `module:`, le module qui l’émet (le module auquel appartient la classe quand on l’omet ;
réglez-le pour une classe d’événement qui vit dans la fondation), et `stream:`, le flux sur lequel il
circule.

```php
// n'importe où dans iam
app(Bus::class)->emit(new UserRegistered(new UserRegisteredPayload(id: $user->id)));
```

L’événement part une fois validée la transaction dans laquelle il a été émis, et jamais si elle est
annulée.

## Le traiter dans un autre module

Le handler vit dans le module qui réagit :

```php
// apps/Analytics/app/Handlers/RecordSignup.php
final class RecordSignup implements Handler
{
    public function handle(string $name, array $payload): void
    {
        $user = UserRegisteredPayload::from($payload);

        Signup::query()->firstOrCreate(['user_id' => $user->id]);
    }
}
```

et il est listé dans le provider du module :

```php
// apps/Analytics/app/Providers/AnalyticsServiceProvider.php
protected array $streamHandlers = [
    IamEvent::UserRegistered->value => [
        RecordSignup::class,
    ],
];
```

`Signup::query()` utilise la connexion d’analytics : un handler s’exécute avec les réglages de son
module.

## Le traiter dans la file

Un handler qui implémente `ShouldQueue` s’exécute dans la file au lieu du consommateur, comme un
écouteur Laravel mis en file, et prend les mêmes options (`$tries`, `viaQueue()`, `shouldQueue()`…) :

```php
// apps/Notifications/app/Handlers/SendWelcome.php
final class SendWelcome implements Handler, ShouldQueue
{
    public int $tries = 3;

    public function handle(string $name, array $payload): void
    {
        // envoyer le mail
    }
}
```

Le worker l’exécute dans son module, sous la même garde qu’un handler exécuté tout de suite.

## Lancer le consommateur

```bash
php artisan stream:consume
```

Un consommateur sert chaque module que le processus exécute, chacun avec sa propre position sur le
flux : un module qui échoue ou prend du retard ne retient que lui-même, et un module ajouté plus tard
n’en dérange aucun autre. `--module=analytics` le limite à un module, `--stream=shadows` lit un autre
flux. Sur `SIGTERM` il termine l’événement qu’il tient, puis s’arrête (il faut l’extension `pcntl`).

Lancez un consommateur par ensemble de modules et par flux, jamais deux : deux casseraient l’ordre dans
lequel un module lit.

## Choisir le flux

Les événements circulent sur le flux `default`. Réglez son pilote dans `.env` :

```dotenv
# Redis Streams, de mk-josias/laravel-distributable-stream-redis
DISTRIBUTABLE_STREAM_DRIVER=redis
```

`local` remet chaque événement aux handlers des modules que le processus exécute, une fois la
transaction validée, sans courtier ni consommateur à lancer ; un module qu’un autre processus exécute
ne le reçoit jamais. `array` garde les événements en mémoire, pour les tests ; `null` les abandonne.
Un flux à vous est une
entrée de `distributable.events.streams`, et un événement le choisit avec
`#[Emits(..., stream: 'audit')]`. L’ordre n’est gardé qu’à l’intérieur d’un flux. Pour un autre
courtier, voir [Écrire un pilote](/extend/driver).

## Ne pas perdre un événement

Par défaut, un événement émis pendant que le processus meurt, ou pendant que le flux est en panne,
entre la validation et l’envoi, est perdu. Pour le garder :

```dotenv
DISTRIBUTABLE_EVENTS_RELIABLE_PUBLISH=true
```

L’événement est alors aussi écrit sur la connexion du module, dans la même transaction que les
données, et supprimé une fois envoyé. Un événement qui n’a pas pu partir attend
`php artisan stream:republish`. Le starter le laisse désactivé.

## Traiter un événement une seule fois

Un flux peut livrer un événement deux fois, après un plantage ou une nouvelle tentative. Quand il le
peut, chaque handler est gardé : une seconde livraison du même événement au même handler ne fait rien.
La garde écrit une ligne dans la table `event_consumptions` du module consommateur ; le starter la
coupe avec `DISTRIBUTABLE_STREAM_GUARD=false`. Nettoyer cette table et le flux est dans
[Exploiter le flux d’événements](/fr/run/streams).

## Changer la forme d’une charge utile

Ajouter un champ facultatif garde la version. En renommer ou en retirer un la fait monter. La classe de
charge utile se trouve dans la fondation d’iam. `#[Payload]` nomme son événement et sa version, et
`upcast()` remonte une charge utile plus ancienne d’une version à la fois, pour qu’un handler ne
reçoive jamais que la forme courante :

```php
// foundation/Iam/Events/UserRegisteredPayload.php
#[Payload(IamEvent::UserRegistered, version: 2)]
final class UserRegisteredPayload extends Dto implements Versioned
{
    public static function upcast(int $from, array $payload): array
    {
        // la version 1 l'appelait `name`
        return $from === 1 ? ['id' => $payload['id'], 'full_name' => $payload['name']] : $payload;
    }
}
```

La classe de charge utile est listée dans `FoundationServiceProvider::$payloads`. Un module déployé avant
la nouvelle version reçoit un événement qu’il ne sait pas lire : il lève `ModuleException` et n’exécute
aucun handler. Avec le pilote `redis` qui bloque en cas d’échec, son flux attend que ce module soit
déployé.

## Envoyer un événement à certains modules seulement

```php
public function recipients(): array
{
    return ['analytics'];
}
```

Seul analytics le traite ; les autres modules l’ignorent.

## Transmettre le contexte

```php
// config/distributable.php
'events' => ['propagate' => ['trace_id', 'locale']],
```

Ces clés du `Context` de Laravel voyagent avec l’événement et sont restaurées autour de chaque
handler. Les appels entre modules les transportent aussi.

## Tester un handler

```php
$this->receive('analytics', 'iam.user.registered', ['id' => 42]);
```

`receive()`, de `Distributable\Testing\ModuleAware`, remet l’événement aux handlers d’analytics, sans
flux et sans iam.

## Sur le fil

Tous les pilotes transportent le même JSON, qu’un programme dans un autre langage peut lire ou écrire :

```json
{
  "id":         "0191f3c2-8a41-7c2e-9b55-3f1c7d0a4e88",
  "emitter":    "iam",
  "name":       "iam.user.registered",
  "payload":    { "id": 42 },
  "headers":    { "trace_id": "4bf92f35" },
  "emitted_at": "2026-09-30T13:22:41.512000Z",
  "recipients": [],
  "stream":     "default",
  "version":    1
}
```

Voir [Programmes dans d’autres langages](/fr/talk/other-languages).

## Ensuite

[Garder une copie des données d’un autre module](/fr/talk/copies), qui repose sur les événements.
