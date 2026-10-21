# Les frontières

À la fin de cette page, vous connaissez les sept règles auxquelles le paquet tient les modules, et
les trois vérifications qui vous disent quand l’une est enfreinte.

Chaque module est un microservice dès sa première ligne de code, et le paquet l’y tient, même quand
il partage un processus avec les autres. Chaque règle dit ce qu’elle garantit, parce qu’une règle
dont plus personne ne voit la raison finit par être abandonnée.

## Les sept règles

1. **Chaque module est conçu comme un microservice.** Ses routes, ses jobs, ses commandes, ses
   écouteurs, sa configuration et ses données lui appartiennent, pour qu’il puisse partir seul le
   jour venu. Son [provider](/fr/build/layout) les charge depuis son dossier, et il a ses propres
   connexions ([réglages](/fr/build/settings)).
2. **Un module ne connaît des autres que ce qu’ils montrent.** Ce qu’un module accepte de montrer
   (les messages auxquels il répond, la forme des données qu’il échange, les noms de ses événements)
   vit dans la [fondation](/fr/talk/foundation). Rien de ce qu’un module utilise ne disparaît quand un
   voisin passe dans un autre processus. La fondation n’utilise aucun module, et tout module peut
   l’utiliser.
3. **Demander par un message, annoncer par un événement.** Aucun module n’écrit dans les données d’un
   autre. Un module en interroge un autre avec un [message](/fr/talk/calls), annonce avec un
   [événement](/fr/talk/events), et lit les lignes dont il a besoin dans une [copie](/fr/talk/copies).
4. **Un module ne choisit ni ses connexions ni ses réglages.** Son code s’exécute toujours avec ses
   propres connexions, réglages et services ([réglages](/fr/build/settings),
   [services](/fr/build/services)). Hors de tout module, il n’y a pas de connexion par défaut : il n’a
   donc rien à détourner pour atteindre celles d’un voisin.
5. **Le même code, quelle que soit la topologie.** Que deux modules s’exécutent ensemble ou séparément,
   ni l’appelant ni l’appelé ne change. Un appel vers un module que le processus exécute est un appel
   de méthode direct ; tout autre sort sur le transport du module. Les événements passent par le flux
   dans les deux cas.
6. **Le déploiement est de la configuration.** Quels modules s’exécutent dans quel processus est un
   réglage, pas une décision écrite dans le code : `RUN_MODULES`, et `host` pour un module qui
   s’exécute ailleurs. [Où s’exécutent les modules](/fr/run/where).
7. **Les frontières se vérifient avant le déploiement.** Ce que des applications séparées
   empêchaient, une vérification doit le détecter : un import interdit, ou la table d’un voisin
   nommée dans le code, fait échouer vos tests.

## Vérifier les frontières

Trois vérifications rapportent les mêmes infractions, à des moments différents.

### Dans vos tests

`Distributable\Testing\Boundaries` lit les fichiers PHP de chaque module, de la fondation et des
dossiers `app/`, `routes/` et `config/` de l’application. Il n’a besoin d’aucune configuration :

```php
// tests/Architecture/BoundariesTest.php
it('keeps the modules apart', function () {
    expect(app(\Distributable\Testing\Boundaries::class)->violations())->toBe([]);
});
```

`violations()` renvoie un `{fichier} : {nom}` par référence qui franchit une frontière :

- un module ne peut jamais nommer l’espace de noms d’un autre module ;
- un module ne peut jamais nommer la connexion d’un autre module, ni une table que les migrations de
  cet autre module créent. Un nom que le module donne aussi à l’une de ses propres connexions, comme
  `owner`, est le sien, comme le nom d’une copie qu’il garde : les deux sont permis ;
- la fondation et l’application ne peuvent jamais nommer l’espace de noms d’un module, hors des
  dossiers propres aux modules.

Il lit les noms de classe qualifiés et les littéraux de chaîne simples : un nom de table ou de
connexion assemblé à l’exécution n’est pas vu.

### Avant un déploiement

```bash
php artisan modules:doctor
```

Lancez-le au déploiement, ou en CI avec l’environnement de production. Il liste chaque problème et
sort avec un code non nul s’il y en a au moins un :

- un dossier de `apps/` que `declared` ne liste pas :
  `[apps/Notifications] is not declared in distributable.declared.`
- un module local sans son `bootstrap/providers.php`, ou dont la classe de provider n’existe pas ;
- deux copies tenues dans une même table :
  `[analytics] keeps two copies in table [users]: … Give each its own $table.`
- un message RPC qui nomme un module non déclaré ;
- un module qui s’exécute ailleurs et sert des messages, sans hôte :
  `[iam] runs elsewhere and serves RPC messages, but distributable.declared.iam.host is not set.`
- des messages RPC déclarés alors que le secret est vide :
  `rpc-http.secret is empty: set DISTRIBUTABLE_RPC_SECRET or APP_KEY.`
- tout ce que `Boundaries` rapporte :
  `Boundary crossed: apps/Analytics/app/Models/Report.php: Apps\Iam\Models\User`
- deux modules locaux qui donnent à une même clé de configuration des valeurs différentes :
  `Modules [analytics, iam] set config [iam.flag] to different values: in one process, the module registered last wins.`

### À l’exécution

Un processus ne charge que les classes des modules de son `RUN_MODULES`. Utiliser une classe d’un
autre module lève une `ModuleException`, même si son fichier est sur le disque :

```
[Apps\Iam\Repositories\UserRepository] belongs to module [iam], which this process does not run
(RUN_MODULES): go through its foundation messages or an event.
```

Cela détecte à l’exécution un appel que `Boundaries` aurait détecté dans vos tests. Un processus sait
tout de même que les autres modules existent, parce que `config/distributable.php` les déclare : il
écoute leurs événements et les appelle par les clients de la fondation.

## Rien d’inventé

Aucune de ces règles n’est nouvelle : ce sont des patrons de microservices éprouvés, appliqués aux
modules et en place par défaut, sans code supplémentaire dans le code métier.

- **Contexte borné :** un module est un domaine, avec son code, ses réglages et ses routes.
- **Une base de données par service, et au-delà :** un module n’atteint que ses propres connexions,
  réglages et services.
- **Noyau partagé :** la fondation tient ce que les modules partagent, et rien de plus.
- **Boîte d’envoi transactionnelle** (en option) : un événement est gardé dans la même transaction que
  les données jusqu’à son envoi, donc il ne se perd pas quand l’envoi échoue.
- **Consommateur idempotent :** un événement traité est marqué, et une seconde livraison ne fait rien.
- **Transfert d’état par événements :** une copie des lignes d’un autre module, tenue à jour par ses
  événements.
- **Versionnage des événements :** chaque événement porte une version, et les plus anciens sont
  convertis à la lecture.
- **Ports et adaptateurs :** un appel est le même message en local ou sur le réseau, et le flux comme
  le transport peuvent être remplacés.

## Ensuite

[Essayez le starter](/fr/start/starter), ou [démarrez une nouvelle application](/fr/start/new-app).
