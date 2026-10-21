# Les mots employés

- **Module :** une zone de l’application avec sa propre frontière, un domaine métier (commandes,
  facturation) ou un domaine de support sur lequel les autres s’appuient (identité, notifications) :
  son code, ses routes, son contexte. `iam`, `analytics`, `notifications`.
- **Contexte :** ce que le code d’un module lit pendant qu’il s’exécute : ses connexions, sa
  configuration et ses services (cache, mailer, disques). [Réglages](/fr/build/settings),
  [services](/fr/build/services).
- **Fondation :** les classes que tout module peut charger, là où les modules s’accordent sur ce
  qu’ils échangent. [Ce que les modules partagent](/fr/talk/foundation).
- **Message :** un appel d’un module à un autre, une classe `readonly` de la fondation avec les règles
  qui la valident. [Appels](/fr/talk/calls).
- **Handler :** la classe qu’un module écrit pour répondre à un message (`$rpcHandlers`) ou réagir à
  un événement (`$streamHandlers`).
- **Événement :** un fait qu’un module annonce ; sa classe de charge utile est dans la fondation.
  [Événements](/fr/talk/events).
- **Copie :** une table en lecture seule des lignes d’un autre module, tenue à jour par les
  événements de ce module. [Copies](/fr/talk/copies).
- **Flux :** là où les événements circulent entre modules, dans un processus ou plusieurs.
  [Exploiter le flux d’événements](/fr/run/streams).
- **Transport :** la façon dont les appels atteignent un module qui s’exécute ailleurs (`http` est
  fourni). [HTTP RPC](/reference/rpc-http).
- **Pilote :** un paquet qui fournit un transport, un flux ou un stockage d’archive.
  [Écrire un pilote](/extend/driver).
- **Enveloppe :** le JSON dans lequel un événement voyage. [Sur le fil](/fr/talk/events#sur-le-fil).
