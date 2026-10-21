# Exploiter le flux d’événements

À la fin de cette page, vous savez ce qui grossit dans un flux en marche, ce qui le nettoie, et
comment l’archiver.

Un flux d’événements garde ce qu’on lui donne. Rien ne retire les vieux événements ni les vieilles
marques de consommation tant que vous ne le demandez pas. Cette page dit ce qui grossit, ce qui
nettoie, et quand.

## Ce qui grossit, et ce qui nettoie

- **le flux**
  - Où : Redis, un flux par flux configuré (`distributable:events` par défaut)
  - Nettoyé par : `stream:trim`
  - Sûr quand : chaque module qui le lit a acquitté les entrées
- **les marques de consommation**
  - Où : `event_consumptions`, sur la connexion du module consommateur
  - Nettoyées par : `stream:prune-consumptions`, ou `guard_cleanup => acknowledge`
  - Sûr quand : l’événement marqué ne peut plus être livré de nouveau

`event_publications` ne grossit pas : avec `DISTRIBUTABLE_EVENTS_RELIABLE_PUBLISH` actif, une ligne est
supprimée une fois son événement envoyé, donc la table ne contient que les événements qui n’ont pas pu
partir.

Rien n’est élagué à l’écriture d’un événement, donc un consommateur arrêté, ou un module ajouté plus
tard, ne manque rien.

## Les commandes

```bash
# le flux : supprimer les entrées que chaque consommateur a acquittées
php artisan stream:trim [--stream=default]

# les marques de consommation : supprimer celles de plus de 7 jours, dans chaque module local
php artisan stream:prune-consumptions --days=7

# le flux : copier ce qu'il garde dans une archive (rien n'est supprimé)
php artisan stream:export storage/events.jsonl --acknowledged

# le flux : republier les entrées d'une archive, pour reconstruire un flux vidé
php artisan stream:import storage/events.jsonl

# les événements encore stockés parce qu'ils n'ont pas pu partir après leur validation
php artisan stream:republish

# une entrée qui ne peut jamais réussir retient son module tant que on_failure vaut block : supprimer la plus ancienne
php artisan stream:skip --module=analytics
```

- **`stream:trim` :** `--stream`
- **`stream:prune-consumptions` :** `--days` (7 par défaut) : il doit durer plus longtemps qu’un
  événement ne peut rester dans le flux
- **`stream:export` :** `--store`, `--emitter`, `--stream`, `--since`, `--until`, `--where`,
  `--acknowledged`, `--batch`
- **`stream:import` :** `--store`, `--emitter`, `--stream`, `--since`, `--until`, `--where`
- **`stream:republish` :** `--module`
- **`stream:skip` :** `--module`, `--stream`, `--until` (une date : chaque entrée jusqu’à elle, entrées
  non encore lues comprises)

`export` et `import` lisent et écrivent le flux lui-même, donc ils n’ont besoin d’aucun réglage ; le
transport doit pouvoir rendre ses entrées (`redis` le fait). `republish` travaille sur les lignes de
`event_publications`, donc il n’a quelque chose à envoyer qu’avec `DISTRIBUTABLE_EVENTS_RELIABLE_PUBLISH`
actif (voir [Événements](/fr/talk/events#ne-pas-perdre-un-evenement)).

## Archiver le flux

`stream:export` et `stream:import` travaillent sur le flux lui-même, pas sur la table : le transport
garde ce qui a été publié, donc ils n’ont besoin d’aucun réglage.

```bash
# copier ce que le flux garde dans une archive
php artisan stream:export storage/events.jsonl [--store=file] [--emitter=*] [--stream=default] \
    [--since=2026-08-01] [--until=2026-09-01] \
    [--where=name=iam.user.registered] [--where=payload.status=paid] \
    [--acknowledged] [--batch=1000]
# republier les entrées d'une archive sur un flux, par exemple un flux qui a été vidé
php artisan stream:import storage/events.jsonl [--store=file] [--emitter=*] [--stream=default] \
    [--since=2026-08-01] [--until=2026-09-01] [--where=payload.status=paid]
```

Chaque module écrit sur le même flux, donc un export les prend tous ; `--emitter` ne garde que les
entrées qu’un module a émises. Rien n’est supprimé : `stream:trim` supprime ce que chaque consommateur a
lu. `--acknowledged` s’arrête sous ce que chaque groupe de consommateurs a acquitté. Le transport doit
implémenter `ArchivesStreams` (`redis` le fait). `import` publie de nouveau les entrées : la garde de
consommation transforme en non-opération ce qu’un consommateur a déjà appliqué, sauf si `guard_cleanup`
vaut `acknowledge`, qui supprime les marques, si bien qu’un événement importé est appliqué de nouveau.

Par défaut, le stockage d’archive est `file` (`DISTRIBUTABLE_STREAM_ARCHIVE`) : il écrit des lignes
JSON, une enveloppe par ligne. Pour archiver ailleurs (S3, par exemple), voir
[Écrire un pilote](/extend/driver#an-archive-store).

## Les marques de consommation

Par défaut une marque reste jusqu’à ce que `stream:prune-consumptions` la supprime. Avec
`'guard_cleanup' => 'acknowledge'` dans `config/distributable.php` (transport `redis`), une marque est
supprimée dès que son entrée est acquittée, puisqu’elle ne peut plus être livrée de nouveau, et la
table reste presque vide. Le prix : une entrée importée ou renvoyée n’est plus reconnue, et ses handlers
s’exécutent de nouveau.

## Ce que retire `stream:trim`

Sur le transport `redis`, il retire chaque entrée plus ancienne que la plus ancienne entrée encore non
acquittée par un groupe quelconque, ou plus ancienne que ce qu’un groupe s’est déjà vu livrer quand il
n’a rien en attente. Il ne fait rien tant qu’aucun groupe ne lit le flux.

Chaque consommateur est un groupe nommé d’après son module, et un groupe ne disparaît jamais tout seul.
Un module retiré, renommé ou jamais démarré garde son groupe, et le flux n’est élagué pas plus loin que
ce que ce groupe a lu. Regardez les groupes :

```bash
# les groupes du flux, avec la dernière entrée livrée à chacun
redis-cli XINFO GROUPS distributable:events
# les entrées qu'un groupe a reçues et non acquittées
redis-cli XPENDING distributable:events analytics
```

Un groupe qu’aucun module ne lit plus se supprime avec Redis, pas avec une commande du paquet :

```bash
redis-cli XGROUP DESTROY distributable:events analytics
```

## Planifier

Le starter planifie `stream:republish` chaque minute quand les événements sont publiés de façon fiable
(`DISTRIBUTABLE_EVENTS_RELIABLE_PUBLISH=true`, dans son `bootstrap/app.php`). Pour lancer chaque jour les
deux qui sont des nettoyages, ajoutez-les au provider d’un module, dont le `schedule()` ne s’exécute
que là où ce module s’exécute :

```php
// apps/Notifications/app/Providers/NotificationsServiceProvider.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('stream:trim')->daily();
    $schedule->command('stream:prune-consumptions --days=7')->daily();
}
```

Seul un conteneur avec `WITH_SCHEDULER=true` exécute le planning, et un par ensemble de modules : deux
exécuteraient chaque tâche deux fois.

## Ensuite

[Programmes dans d’autres langages](/fr/talk/other-languages), ou retour à
[l’accueil](/fr/).
