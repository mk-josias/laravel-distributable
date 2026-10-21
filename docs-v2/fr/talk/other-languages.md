# Programmes dans d’autres langages

À la fin de cette page, un programme en Node ou en Go émet des événements, les consomme et appelle des
modules comme un module de l’application.

Un programme écrit dans un autre langage (une passerelle Node, un worker Go) peut participer : il émet
et consomme des événements sur le flux, et appelle les modules en RPC. Le paquet ne livre pas de
client pour lui ; voici ce que ce client doit parler. Pour les modules, c’est un module de plus.

Déclarez-le dans `config/distributable.php` sous `declared`, avec son `host`, comme n’importe quel
autre module. Un consommateur acquitte sans la lire toute entrée dont l’`emitter` n’est pas un module
déclaré.

Tout ce qui quitte un processus est du JSON : l’enveloppe sur le flux, le corps et la réponse d’un
appel RPC, et les réponses mises en cache. Rien n’est sérialisé dans un format PHP.

Ce que fait le programme, selon ce qu’il veut :

- **Annoncer quelque chose :** [écrire une enveloppe dans le flux](#evenements-sur-redis).
- **Réagir aux événements d’un module :** [lire le flux](#evenements-sur-redis) avec son propre groupe
  de consommateurs.
- **Demander quelque chose à un module :** [appeler sa route RPC](#rpc-en-http), signée.
- **Être interrogé par un module :** [répondre sur cette même route](#repondre-a-un-module).
- **Posséder des données dont les autres gardent une copie :**
  [annoncer chaque changement](#etre-la-source-d-une-copie).
- **Lire ou oublier une réponse en cache :** [lire ou supprimer sa clé](#reponses-en-cache).

Dans une application distributable, le programme prend aussi ces places :

- **Déclaré** dans `config/distributable.php` sous `declared`, avec son `host`.
- **Appelé par un module :** ses messages et son client se trouvent dans `foundation/{Nom}/`, comme
  ceux d’un module.
- **Source d’une copie :** la forme de la copie se trouve dans `foundation/{Nom}/Shadows/`, avec le
  programme comme `module:` de son `#[Shadow]`.

## Événements sur Redis

- **Flux :** la `key` du flux, `distributable:events` pour le flux `default`, derrière le préfixe de la
  connexion Redis. Avec le `REDIS_PREFIX=database:` du starter, la clé que Redis contient est
  `database:distributable:events` ; réglez `REDIS_PREFIX=` pour avoir la clé nue.
- **Entrée :** `XADD {key} * envelope {json}` : un seul champ, `envelope`, qui contient
  [l’enveloppe](/fr/talk/events#sur-le-fil) en JSON.
- **`emitter` :** le nom du programme dans `declared`.
- **`id` :** un UUID, unique par événement : la garde de consommation s’appuie dessus.
- **Consommer :** un groupe de consommateurs par module consommateur, nommé d’après lui, créé à `0` ;
  acquittez avec `XACK` une fois l’événement traité.

## RPC en HTTP

```
POST {host}/{module}/rpc/{version}/{message}
Content-Type: application/json
X-Rpc-Timestamp: 1790000000            secondes unix, à moins de signature_ttl de l'horloge du serveur
X-Rpc-Nonce:     <a fresh UUID>        accepté une seule fois
X-Rpc-Context:   {"trace_id":"…"}      les clés de Context propagées, en JSON ; {} s'il n'y en a pas
X-Rpc-Signature: hex(hmac_sha256(secret,
                     timestamp + "\n" + nonce + "\n" + path + "\n" + body + "\n" + context))

{"payload": {"id": 42}}
```

`path` est `/{module}/rpc/{version}/{message}`, avec sa barre oblique initiale, ou ce que règle
`rpc-http.path` (`DISTRIBUTABLE_RPC_PATH`) quand les processus l’ont changé. `message` est le nom de la
classe du message en kebab case (`find-user`) sauf si son `#[Rpc]` dit autre chose, et `version` est sa
version. `body` et `context` sont signés tels qu’envoyés, octet pour octet. `payload` contient les
propriétés du message par nom. `secret` est `rpc-http.secret`. Le statut de la réponse dit ce qui s’est
passé :

- **200 :** la valeur de retour, en JSON.
- **404 :** le handler a renvoyé `null`.
- **400 :** le module ne répond à aucun message ou version de ce nom : `{"message": "…"}`.
- **403 :** la signature est absente, périmée, fausse ou déjà utilisée.
- **422 :** les règles du message refusent la charge utile : `{"errors": {"field": ["…"]}}`.

## Réponses en cache

Un `RpcClient` garde une réponse sous sa clé, derrière le préfixe de la connexion Redis
(`REDIS_PREFIX`) et celui du cache (`CACHE_PREFIX`), en JSON, dans la base de la connexion Redis `cache`
de Laravel (`REDIS_CACHE_DB`, 1 par défaut, alors que le flux est en 0) :

```
GET database:cache:iam:user:42   →   {"id":42,"name":"Ada"}
DEL database:cache:iam:user:42       quand le module possède l'utilisateur et qu'il change
```

avec `REDIS_PREFIX=database:` et `CACHE_PREFIX=cache:`, les valeurs par défaut du starter. Celles de
Laravel sont `{nom de l'app}-database-` et `{nom de l'app}-cache-` : chaque processus a alors besoin du
même `APP_NAME`, ou des deux mêmes préfixes réglés.

La clé qui suit les préfixes est celle que le client du propriétaire passe à `readThrough()`.

## Répondre à un module

Un module Laravel appelle le programme exactement comme il appelle un autre module : par un message et
son client, avec le `host` du programme dans `declared`. Le programme reçoit la requête ci-dessus sur
`POST /{nom}/rpc/{version}/{message}`, vérifie la signature, et répond la valeur de retour en JSON, ou
un 404 pour `null`. Tout autre statut est une erreur pour l’appelant.

Pour l’appeler par autre chose que ce format HTTP (gRPC, une API REST existante), donnez à son hôte un
transport à vous : voir [Écrire un pilote](/extend/driver).

## Être la source d’une copie

Un module Laravel peut garder une [copie](/fr/talk/copies) de lignes qu’un programme possède, avec le
programme comme `module:` du `#[Shadow]` de la copie. Le programme annonce alors chaque changement par un
événement `distributable.shadow.changed` :

```json
{
  "id": "0191f3c2-8a41-7c2e-9b55-3f1c7d0a4e88",
  "emitter": "identity",
  "name": "distributable.shadow.changed",
  "payload": { "source": "users", "key": 42, "attributes": { "name": "Ada" } },
  "headers": {},
  "emitted_at": "2026-09-30T13:22:41.512000Z",
  "recipients": [],
  "stream": "default",
  "version": 1
}
```

`source` est le `sourceTable()` de la copie, `key` l’id de la ligne (un entier ou une chaîne),
`attributes` les colonnes de la copie. Une ligne supprimée est annoncée avec un `deleted_at` parmi les
attributs. Chaque module qui garde une copie l’écrit sur sa propre connexion quand il consomme
l’événement.

## Ensuite

[Où s’exécutent les modules](/fr/run/where).
