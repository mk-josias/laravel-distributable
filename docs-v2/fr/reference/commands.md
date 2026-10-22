# Commandes

Chaque commande que le paquet ajoute ou modifie, avec ses options.

`--module=<nom>` est sur chaque commande Artisan. Un générateur écrit dans le module nommé ; toute
autre commande s’exécute dans son contexte, une fois par module quand l’option est répétée ou vaut
`*`. Voir [Lancer une commande dans un module](/fr/build/create#lancer-une-commande-dans-un-module).

## Modules

- `modules:install` : publie `config/distributable.php` et crée le dossier des modules.
- `modules:make <nom>` : crée un module et le déclare.
- `modules:delete <nom> [--force]` : supprime son dossier et sa déclaration ; sa base reste telle
  quelle.
- `modules:list` : liste les modules et l’endroit où chacun s’exécute.
- `modules:doctor` : vérifie que chaque module peut s’exécuter : déclaration, provider, connexions,
  hôtes distants, frontières. Voir [Vérifier les frontières](/fr/understand/boundaries#verifier-les-frontieres).
- `modules:cache` et `modules:clear` : mettent en cache, ou retirent, ce que disent les dossiers des
  modules : espaces de noms, bases et sources de copies.
- `modules:purge [--force]` : supprime le dossier de chaque module que ce processus n’exécute pas.
  Voir [Une image par module](/fr/run/deploying#une-image-par-module).
- `modules:unused-packages` : liste les paquets Composer que seuls ces modules demandent. Voir
  [Les bibliothèques d’un module](/fr/run/deploying#les-bibliotheques-d-un-module).

## Événements

- `stream:consume [--stream=]` : consomme les événements et exécute les handlers des modules qui
  s’exécutent ici.
- `stream:republish` : envoie les événements gardés en base qui n’ont pas pu partir après leur
  commit. Voir [Ne pas perdre un événement](/fr/talk/events#ne-pas-perdre-un-evenement).
- `stream:trim`, `stream:prune-consumptions`, `stream:skip`, `stream:export` et `stream:import`
  tiennent le flux et ses marques en ordre : [Exploiter le flux d’événements](/fr/run/streams).

## Copies

- `shadows:announce <source> [--keepers=*]` : annonce chaque ligne d’une table source aux modules
  qui en gardent une copie.
- `shadows:want [--keepers=*] [--sources=*]` : demande aux propriétaires des tables sources copiées
  ici d’annoncer leurs lignes. Voir [Garder une copie](/fr/talk/copies).

## Appels

- `rpc:serve [--transport=]` : répond aux appels d’un transport dans un processus à lui. Voir
  [Appeler un autre module](/fr/talk/calls).

## Les commandes de Laravel

`make:*`, `migrate` et les autres commandes de Laravel sont les mêmes, rendues conscientes des
modules. Chacune accepte `--module` : un module, l’option répétée, ou `*` pour chaque module que le
processus exécute. Voir [Générer du code dans un module](/fr/build/create#generer-du-code-dans-un-module)
et [Migrations](/fr/build/migrations).
