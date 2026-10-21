# Ce que les modules partagent

À la fin de cette page, vous savez ce qui va dans la fondation, ce qui n’y va pas, et où le mettre.

Un module n’utilise jamais les classes d’un autre module, parce que cet import casserait dès que les
modules sont déployés séparément. Ce qu’un module partage avec les autres va dans la fondation :

- ses messages RPC et son client ;
- les noms et les charges utiles de ses événements ;
- la forme des copies que les autres gardent de ses lignes ;
- ses énumérations de permissions.

Tout module peut utiliser la fondation, et la fondation n’utilise aucun module.

```
apps/Analytics ──► foundation/Iam ◄── apps/Iam
       └──────── jamais ─────────────┘
```

L’application possède la fondation : faites correspondre `Foundation\` dans `composer.json` et listez
son provider dans `bootstrap/providers.php`. Le paquet ne charge ni l’un ni l’autre. Il lit seulement
`distributable.foundation.path` pour vérifier la règle (voir [Les frontières](/fr/understand/boundaries)).

```json
// composer.json
"autoload": { "psr-4": { "Foundation\\": "foundation/" } }
```

## Ce qu’on y met

- `foundation/{Module}/` est ce que ce module publie pour les autres, comme
  `Foundation\Iam\Rpc\FindUser`, `Foundation\Iam\Services\IamClient`,
  `Foundation\Iam\Events\UserRegisteredPayload`, `Foundation\Iam\Shadows\UserShadow` ou
  `Foundation\Analytics\Enums\AnalyticsPermission`.
- `foundation/Common/` n’est pas un module et n’est pas déclaré. Il contient les classes de base que
  tout module utilise, regroupées par préoccupation technique (`Auth`, `Http`, `Database`,
  `Exceptions`, `Validation`…), comme `Foundation\Common\Http\Controllers\Controller`. Le paquet ne
  dépend pas de `Common` : ces classes sont à vous, à changer ou à supprimer. Le starter dit à quel
  point chaque pièce est voulue dans [Où sont les fichiers du starter](/fr/starter/map#foundation-common).

L’endroit où se trouve le code d’une préoccupation suit qui en a besoin. Ce que tout module utilise va
dans `Common`. Ce que seuls les voisins d’un module doivent savoir de lui va dans le dossier de ce
module : la façon dont les jetons d’iam et l’identité de la passerelle sont vérifiés est
`Foundation\Iam\Auth\JwtTokens` et `TrustedIdentity`.

Une autre organisation convient : le paquet demande seulement que ce que les modules partagent soit
des classes que toute application peut charger.

## Ensuite

[Appeler un autre module](/fr/talk/calls).
