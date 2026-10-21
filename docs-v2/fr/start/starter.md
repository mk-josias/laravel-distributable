# Essayer le starter

À la fin de cette page, vous avez sur votre machine une application de trois modules qui tourne, vous
avez enregistré un utilisateur, et vous avez vu un événement aller d’un module aux autres.

Le starter est une application Laravel configurée avec laravel-distributable et prête pour la
production : trois modules (identité, notifications, analytics), une authentification par une
passerelle, des permissions, des images Docker pour un seul conteneur ou un conteneur par module, et
des tests de chaque module seul. Partez de lui, puis remplacez ses modules par les vôtres.

## Ce qu’il montre

- **Trois modules, chacun avec son propre contexte.** `iam` possède les utilisateurs, `analytics`
  enregistre les inscriptions et calcule des jeux de données, `notifications` tient une boîte de
  réception personnelle. [Construire un module](/fr/build/create).
- **Les trois façons dont les modules se joignent.** Chaque module demande à `iam` les permissions
  d’un utilisateur par un message ; `iam` annonce `iam.user.registered`, que `analytics` et
  `notifications` traitent ; les deux gardent une copie en lecture seule des utilisateurs.
  [Faire parler les modules](/fr/talk/choosing).
- **L’authentification à la passerelle.** Seul Traefik vérifie le jeton du client ; les modules lisent
  l’identité qu’il signe. [Authentification](/fr/starter/authentication).
- **Des permissions tenues par `iam`,** demandées par un message et mises en cache.
  [Permissions](/fr/starter/permissions).
- **Docker sous deux formes :** tous les modules dans un conteneur, ou un conteneur par module, avec
  le même code. [Docker](/fr/starter/docker).
- **Des tests par module,** chacun lançant son module seul. [Tests](/fr/starter/tests).

## L’installer et le lancer

```bash
composer create-project mk-josias/laravel-distributable-starter my-app
cd my-app
php artisan serve
```

Requiert PHP 8.4+, et Redis pour le flux d’événements. `create-project` pose deux questions, par
`php artisan skeleton:setup` :

- **Quels modules d’exemple gardez-vous ?** Tous par défaut. Il peut retirer `analytics` ou
  `notifications` : ses dossiers, sa déclaration, ses tests, son entrée `auth.permissions` et son
  service Docker. `iam` reste toujours : l’authentification, les permissions et les copies des
  utilisateurs en dépendent.
- **Comment la passerelle valide-t-elle un jeton ?** `signature` par défaut. Cela règle
  `AUTH_TOKEN_VALIDATION_STRATEGY` dans `.env` et un `AUTH_GATEWAY_SECRET` aléatoire. Voir
  [Authentification](/fr/starter/authentication).

Sans terminal (`--no-interaction`), il garde tout et `signature`. Il s’exécute une fois, puis se
supprime lui-même ainsi que sa ligne dans `composer.json`, pour ne jamais pouvoir retirer un module
devenu le vôtre.

`composer create-project` exécute toutes les étapes sauf `serve`. Pour les refaire à la main :

```bash
cp .env.example .env
php artisan key:generate
# la paire RSA avec laquelle iam signe les jetons, dans storage/jwt-*.key
php artisan auth:jwt-keys
# migre chaque module sur la connexion `owner` qu'il déclare
php artisan migrate --module=* --database=owner
# les permissions listées dans auth.permissions
php artisan iam:sync-permissions --prune
```

Les modules utilisent par défaut des fichiers sqlite (`database/iam.sqlite`,
`database/analytics.sqlite`, `database/notifications.sqlite`) ; `migrate` les crée.

## Enregistrer un utilisateur, et regarder l’événement

Enregistrez un utilisateur dans `iam` :

```bash
curl -X POST http://127.0.0.1:8000/iam/api/v1/register \
    -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d '{"name":"<name>","email":"<email>","password":"<password, 8 characters or more>"}'
```

```json
{"success":true,"data":{"id":1,"name":"<name>"}}
```

`iam` annonce l’inscription par un événement. Lancez le consommateur ; il sert `analytics` et
`notifications`, chacun avec son propre curseur :

```bash
php artisan stream:consume
```

`analytics` a maintenant une inscription et `notifications` un message de bienvenue, et chacun garde
sa propre copie de l’utilisateur.

Connectez-vous pour obtenir un jeton. Il dure une heure (`AUTH_TOKEN_TTL`) ; une session par appareil
(`X-Device-Id`, sinon le user agent), qu’une nouvelle connexion de cet appareil remplace :

```bash
curl -X POST http://127.0.0.1:8000/iam/api/v1/login \
    -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d '{"email":"<email>","password":"<password>"}'
```

```json
{"success":true,"data":{"token":"<token>","expires_at":1790003600}}
```

`register` accepte 6 requêtes par minute depuis une même adresse. `login` répond par le même 401 à un
mauvais mot de passe et à un email inconnu, et par 429 après 5 échecs pour un même email depuis une
même adresse, pendant une minute ; une connexion réussie les efface.

Un module ne lit que l’`X-Identity` qu’une passerelle envoie, jamais le jeton. Sans passerelle devant,
signez-le vous-même avec le secret de la passerelle, puis envoyez-le :

```bash
# l'X-Identity de l'utilisateur 1, valable une minute
php artisan tinker --execute='echo Foundation\Iam\Auth\TrustedIdentity::sign(
    ["sub" => 1], time() + 60, config("auth.gateway.secret"));'
curl http://127.0.0.1:8000/analytics/api/v1/metrics \
    -H 'Accept: application/json' -H 'X-Identity: <X-Identity>'
curl http://127.0.0.1:8000/notifications/api/v1/notifications \
    -H 'Accept: application/json' -H 'X-Identity: <X-Identity>'
```

L’`X-Identity` dure une minute. Les montages [Docker](/fr/starter/docker) mettent Traefik devant, qui le
fait à chaque requête : là, envoyez le jeton lui-même.

`php artisan analytics:compute` construit les jeux de données horaires (le planificateur le lance
chaque heure), et `GET /analytics/api/v1/metrics?group=day` les lit. `analytics_datasets` est
partitionnée par mois sur PostgreSQL, MySQL et MariaDB : `php artisan analytics:prune --keep=12`
supprime d’un bloc les mois expirés. Sur SQLite c’est une table ordinaire, et l’élagage supprime les
lignes.

## Pour aller plus loin

- **Comment les modules se joignent :** [Appel, événement ou copie ?](/fr/talk/choosing).
- **Où est chaque fichier :** [Où sont les fichiers du starter](/fr/starter/map).
- **Le lancer en conteneurs :** [Docker](/fr/starter/docker), puis [Déployer](/fr/run/deploying).
- **Ajouter un module ou une bibliothèque :** [Ajouter du code](/fr/starter/adding-code).

## Ensuite

[Construire un module à vous](/fr/build/create).
