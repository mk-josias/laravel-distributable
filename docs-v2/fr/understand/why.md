# Pourquoi

À la fin de cette page, vous savez quel problème le paquet résout et ce qu’il vous permet de changer
plus tard sans réécrire.

Le paquet fait du module l’unité et du déploiement un réglage. Chaque module est construit comme un
service indépendant, avec ses propres données et ses propres contrats, sans tables partagées et sans
appels directs, qu’il s’exécute avec d’autres dans un seul processus ou seul dans sa propre
application.

## Ce que le paquet garde séparé

Les microservices donnent à chaque partie d’un système ses propres données, ses propres contrats et
son propre déploiement, et cette indépendance est ce qui permet à une partie d’évoluer, de monter en
charge ou de déménager sans les autres. Les outils de modules existants organisent le code, avec des
routes, des providers et des migrations par module, mais exécutent tout dans le même contexte : un
module peut utiliser les connexions, la configuration ou les services d’un voisin, et rien ne dit
non.

Le paquet l’interdit. Le code d’un module s’exécute dans un contexte qui lui est propre, avec
seulement ses connexions, sa configuration et ses services ; `modules:doctor` fait échouer vos tests
quand un module importe une classe d’un voisin ou nomme ses tables. Il reste la façon dont les
modules sont censés se parler : des messages, des événements et des copies en lecture seule.

## Ce qui justifie d’exécuter un module séparément

Ce qui justifie d’exécuter un module séparément est rarement le même pour tous les modules. C’est un
besoin d’exécution : le module doit monter en charge seul, être déployé à son propre rythme, ou
continuer à fonctionner quand un autre est en panne. Chacun suppose une condition : monter en charge séparément
suppose un module dont la charge est nettement différente, déployer séparément un module qui change bien
plus souvent que le reste. Là où la condition manque, exécuter séparément ajoute seulement un serveur à
faire tourner, à déployer et à surveiller.

Donner à un module sa propre application est une autre décision : elle répond à des besoins
d’équipes et de langages, et coûte des contrats versionnés entre dépôts et la même plomberie écrite
dans chacun. Le paquet la permet sans l’exiger. Quelques modules tournent séparément ; la plupart restent
ensemble, dans un seul processus, avec des appels entre eux qui n’en sortent jamais.

## Ce que vous obtenez

- **Le coût suit la charge.** Regroupez les modules dans le moins de processus que le trafic permet ;
  donnez-lui son propre processus quand il mérite ses propres serveurs.
- **Extraire est un déploiement, pas une réécriture.** Un module possède déjà ses données et ne parle
  que par messages et événements. Le déplacer vers son propre processus ou sa propre application
  change un réglage ; le code qui l’appelle, lui annonce quelque chose ou copie ses lignes reste le
  même.
- **Le même comportement dans toutes les topologies.** Les événements passent toujours par un flux,
  et un appel est le même message que le module réponde ici ou ailleurs.
- **Des frontières que l’on peut tester.** Un dépôt, un langage, une suite de tests, et
  `modules:doctor` fait échouer les tests quand un module franchit une frontière.

Quand des modules partagent des connexions et s’appellent directement, en sortir un est un projet de
migration. Ici, c’est un réglage.

## Ensuite

[Les frontières auxquelles le paquet tient les modules](/fr/understand/boundaries).
