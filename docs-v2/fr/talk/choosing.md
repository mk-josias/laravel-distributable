# Appel, événement ou copie ?

À la fin de cette page, vous savez laquelle des trois voies utiliser quand un module a besoin de
quelque chose d’un autre, et ce que chacune vous coûte.

Un module n’atteint jamais les classes, les tables ni les connexions d’un autre module. Il a trois
voies, et le choix dépend de ce dont il a besoin.

```
besoin de la réponse tout de suite, pour continuer        → un appel
annoncer un fait ; ceux que ça concerne réagissent après → un événement
lire les lignes d'un autre module à côté des siennes     → une copie (tenue à jour par des événements)
```

## Un appel

**À utiliser** pour vérifier une valeur ou lire un enregistrement, quand votre code ne peut pas
continuer sans la réponse. Dans le starter, chaque module demande à `iam` ce qu’un utilisateur peut
faire par le message `Grants` (`RpcPermissions` appelle `IamClient::grants()`).

- Il est toujours à jour.
- Il coûte un appel sur le transport quand l’autre module s’exécute ailleurs, et rien d’autre qu’un
  appel de méthode quand il s’exécute ici.
- Il ne fait pas partie de votre transaction : si votre code échoue ensuite, ce que l’autre module a
  fait reste fait.

[Appeler un autre module](/fr/talk/calls).

## Un événement

**À utiliser** pour annoncer que quelque chose s’est produit, quand vous n’avez pas besoin d’un
résultat et ne savez pas qui réagit. Dans le starter, `iam` annonce `iam.user.registered` ;
`analytics` enregistre une inscription et `notifications` stocke un message de bienvenue.

- L’annonceur n’attend pas et ne sait pas qui traite l’événement.
- Il part une fois votre transaction validée, et jamais si elle est annulée.
- Les événements circulent sur un flux même quand les modules s’exécutent dans le même processus, donc
  ils se comportent de la même façon dans tous les montages.

[Publier et traiter des événements](/fr/talk/events).

## Une copie

**À utiliser** pour lister, filtrer, trier ou joindre sur les lignes d’un autre module. Dans le
starter, `analytics` lit l’utilisateur de chaque inscription dans sa copie des utilisateurs d’`iam`
(`Signup::user()` est un `belongsTo` sur `UserShadow`).

- C’est une table locale, donc la requête joint comme n’importe quelle autre.
- Elle est à jour une fois que le consommateur a traité les événements qui l’ont mise à jour.
- Seul le propriétaire écrit les données : le module qui garde la copie la lit.

Une copie est faite d’événements : le propriétaire annonce chaque ligne qu’il enregistre ou supprime,
et le paquet l’écrit dans chaque module qui garde la copie.
[Garder une copie](/fr/talk/copies).

## Quand deux conviennent

- **Vérifier un enregistrement qui change souvent, ou qui doit être exact :** un appel.
- **Afficher une liste avec les colonnes d’un autre module :** une copie.
- **Déclencher un travail dans un autre module :** un événement. Si vous devez savoir qu’il a
  réussi, un appel.

## Ensuite

Avant d’écrire un message, un événement ou une copie, lisez
[ce que les modules partagent](/fr/talk/foundation) : les trois y gardent leurs classes.
