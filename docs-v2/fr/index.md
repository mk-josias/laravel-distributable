# Laravel Distributable

laravel-distributable apporte une fondation sur laquelle construire des modules distribuables : des
zones autonomes, chacune un domaine métier (paiements, messagerie) ou un domaine de support sur lequel les autres s’appuient
(identité, notifications), avec sa propre frontière, construites comme des microservices. Groupés
dans une application, répartis sur plusieurs, ou un par application : cela ne change rien pour le
paquet. Il fournit ce que ce modèle exige, un contexte séparé pour chaque module, et le code des
modules est le même dans tous les cas.

Chaque module s’exécute dans un contexte d’exécution qui lui est propre : ses propres connexions
(dont celle de la base de données), sa propre configuration et ses propres services (cache, mailer,
disques). Tant que son code s’exécute, tout ce qu’il lit vient de ce contexte.

Un module ne joint les autres que par des contrats, jamais par leur code : il envoie un message auquel
un autre module répond, ou il publie un événement que les autres traitent à leur rythme. Quand il a besoin des données d’un
autre module pour filtrer ou joindre avec les siennes, il en garde une copie ; le paquet la met à
jour à partir des événements de l’autre module, et le module ne fait que la lire.

Vous commencez avec tout dans une seule application. Quand un module doit monter en charge ou être
livré seul, vous le déployez dans un processus propre, qui ne contient que son code, et rien de ce qui
l’appelle ne change.

## Choisir son point d’entrée

- **Voir le paquet à l’œuvre :** [le starter](/fr/start/starter) est une application de trois
  modules, à lire, à lancer, puis à remplacer par les vôtres.
- **Partir de zéro :** [une nouvelle application](/fr/start/new-app).
- **Vous avez déjà une application Laravel :** [une application existante](/fr/start/existing-app).
- **Comprendre l’idée d’abord :** [ce qu’est un module](/fr/understand/module), puis
  [pourquoi](/fr/understand/why).

## Installer

```bash
composer require mk-josias/laravel-distributable
# le pilote d'événements : Redis Streams (ou le vôtre) ; DISTRIBUTABLE_STREAM_DRIVER=redis
composer require mk-josias/laravel-distributable-stream-redis
# les appels vers un module qui tourne dans un autre processus
composer require mk-josias/laravel-distributable-rpc-http
# config/distributable.php et apps/
php artisan modules:install
# apps/Iam, déclaré dans config/distributable.php
php artisan modules:make iam
```

Requiert PHP 8.4+ et Laravel 12 ou 13. Le pilote d’événements et le transport des appels sont des
paquets séparés ; rien d’autre n’est une dépendance d’exécution.

## Ce que le paquet apporte

- **Un contexte par module.** Les connexions, la configuration et les services suivent le module qui
  s’exécute. [Réglages](/fr/build/settings), [services](/fr/build/services).
- **Des frontières qui tiennent.** Un module qui nomme les classes, les connexions ou les tables d’un
  voisin fait échouer vos tests. [Les frontières](/fr/understand/boundaries).
- **Des appels par messages.** Le même message, que le module réponde ici ou ailleurs.
  [Appeler un autre module](/fr/talk/calls).
- **Des événements qui ne se perdent pas.** Envoyés une fois la transaction validée, gardés jusqu’à
  l’envoi en option, traités une seule fois. [Événements](/fr/talk/events).
- **Des copies des lignes d’un autre module,** tenues à jour par ses événements.
  [Copies](/fr/talk/copies).
- **N’importe quel courtier, n’importe quel langage.** Des pilotes pour le transport, un JSON simple
  sur le fil. [Programmes dans d’autres langages](/fr/talk/other-languages),
  [Écrire un pilote](/extend/driver).
- **Une image par module.** Le code des modules qu’un processus ne lance pas, et les paquets dont
  eux seuls ont besoin, sortent de l’image. [Déployer](/fr/run/deploying).

## Ce que le paquet vous laisse

Le paquet n’impose rien du code de l’application elle-même (formats de réponse, DTO, dépôts,
exceptions, authentification). Le starter montre un exemple abouti de chacun, avec les modules dont la
plupart des applications ont besoin (identité, notifications, analytics) : gardez-les, changez-les ou supprimez-les.
Une fonctionnalité est dans le paquet quand la rater casserait
l’une de ses garanties : le code d’un module qui s’exécute hors du contexte de son module utiliserait
la mauvaise connexion, donc le contexte fait partie du paquet ; un module sans dépôt ne casse rien,
donc les dépôts n’en font pas partie. Hors du périmètre : activer ou désactiver des modules à
l’exécution, un fichier de verrouillage par module (un processus charge une seule version d’une
bibliothèque), l’orchestration, et imposer un style de code.

## Licence

MIT.
