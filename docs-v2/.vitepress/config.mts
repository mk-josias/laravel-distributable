import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Laravel Distributable',
  description: 'The foundation for distributed Laravel modules: grouped in one application or spread over several.',
  ignoreDeadLinks: [/LICENSE$/],
  cleanUrls: true,
  base: '/laravel-distributable/',
  locales: {
    root: { label: 'English', lang: 'en-US' },
    fr: {
      label: 'Français',
      lang: 'fr-FR',
      link: '/fr/',
      description: 'La fondation de modules Laravel distribués : groupés dans une application ou répartis sur plusieurs.',
      themeConfig: {
        outline: { label: 'Sur cette page' },
        docFooter: { prev: 'Page précédente', next: 'Page suivante' },
        sidebarMenuLabel: 'Menu',
        returnToTopLabel: 'Retour en haut',
        langMenuLabel: 'Changer de langue',
        darkModeSwitchLabel: 'Thème',
        nav: [
          { text: 'Comprendre', link: '/fr/understand/module' },
          { text: 'Démarrer', link: '/fr/start/starter' },
          { text: 'Construire', link: '/fr/build/create' },
          { text: 'Dialoguer', link: '/fr/talk/choosing' },
          { text: 'Exécuter', link: '/fr/run/where' },
        ],
        sidebar: [
          {
            text: '1. Comprendre',
            items: [
              { text: 'Ce qu’est un module', link: '/fr/understand/module' },
              { text: 'Pourquoi', link: '/fr/understand/why' },
              { text: 'Les frontières', link: '/fr/understand/boundaries' },
              { text: 'Les mots employés', link: '/fr/understand/words' },
            ],
          },
          {
            text: '2. Démarrer',
            items: [
              { text: 'Essayer le starter', link: '/fr/start/starter' },
              { text: 'Une nouvelle application', link: '/fr/start/new-app' },
              { text: 'Une application existante', link: '/fr/start/existing-app' },
            ],
          },
          {
            text: '3. Construire un module',
            items: [
              { text: 'Créer un module', link: '/fr/build/create' },
              { text: 'Où vont les fichiers', link: '/fr/build/layout' },
              { text: 'Les réglages d’un module', link: '/fr/build/settings' },
              { text: 'Les services d’un module', link: '/fr/build/services' },
              { text: 'Migrations', link: '/fr/build/migrations' },
              { text: 'Tester un module', link: '/fr/build/testing' },
            ],
          },
          {
            text: '4. Faire parler les modules',
            items: [
              { text: 'Appel, événement ou copie ?', link: '/fr/talk/choosing' },
              { text: 'Ce que les modules partagent', link: '/fr/talk/foundation' },
              { text: 'Appeler un autre module', link: '/fr/talk/calls' },
              { text: 'Publier et traiter des événements', link: '/fr/talk/events' },
              { text: 'Garder une copie des données d’un autre module', link: '/fr/talk/copies' },
              { text: 'Programmes dans d’autres langages', link: '/fr/talk/other-languages' },
            ],
          },
          {
            text: '5. Exécuter',
            items: [
              { text: 'Où s’exécutent les modules', link: '/fr/run/where' },
              { text: 'Sortir un module', link: '/fr/run/moving' },
              { text: 'Déployer', link: '/fr/run/deploying' },
              { text: 'Exploiter le flux d’événements', link: '/fr/run/streams' },
            ],
          },
          {
            text: '6. Patrons du starter',
            items: [
              { text: 'Où sont les fichiers', link: '/fr/starter/map' },
              { text: 'Authentification', link: '/fr/starter/authentication' },
              { text: 'Permissions', link: '/fr/starter/permissions' },
              { text: 'Docker', link: '/fr/starter/docker' },
              { text: 'Tests', link: '/fr/starter/tests' },
              { text: 'Ajouter du code', link: '/fr/starter/adding-code' },
            ],
          },
        ],
      },
    },
  },
  themeConfig: {
    search: { provider: 'local' },
    outline: [2, 3],
    socialLinks: [{ icon: 'github', link: 'https://github.com/mk-josias/laravel-distributable' }],
    nav: [
      { text: 'Understand', link: '/understand/module' },
      { text: 'Start', link: '/start/starter' },
      { text: 'Build', link: '/build/create' },
      { text: 'Talk', link: '/talk/choosing' },
      { text: 'Run', link: '/run/where' },
      { text: 'Reference', link: '/reference/commands' },
    ],
    sidebar: [
      {
        text: '1. Understand',
        items: [
          { text: 'What a module is', link: '/understand/module' },
          { text: 'Why', link: '/understand/why' },
          { text: 'The boundaries', link: '/understand/boundaries' },
          { text: 'The words used', link: '/understand/words' },
        ],
      },
      {
        text: '2. Start',
        items: [
          { text: 'Try the starter', link: '/start/starter' },
          { text: 'A new application', link: '/start/new-app' },
          { text: 'An existing application', link: '/start/existing-app' },
        ],
      },
      {
        text: '3. Build a module',
        items: [
          { text: 'Create a module', link: '/build/create' },
          { text: 'Where things go', link: '/build/layout' },
          { text: "A module's settings", link: '/build/settings' },
          { text: "A module's services", link: '/build/services' },
          { text: 'Migrations', link: '/build/migrations' },
          { text: 'Testing a module', link: '/build/testing' },
        ],
      },
      {
        text: '4. Make modules talk',
        items: [
          { text: 'Call, event or copy?', link: '/talk/choosing' },
          { text: 'What modules share', link: '/talk/foundation' },
          { text: 'Calling another module', link: '/talk/calls' },
          { text: 'Publishing and handling events', link: '/talk/events' },
          { text: "Keeping a copy of another module's data", link: '/talk/copies' },
          { text: 'Programs in other languages', link: '/talk/other-languages' },
        ],
      },
      {
        text: '5. Run it',
        items: [
          { text: 'Where modules run', link: '/run/where' },
          { text: 'Moving a module out', link: '/run/moving' },
          { text: 'Deploying', link: '/run/deploying' },
          { text: 'Operating the event stream', link: '/run/streams' },
        ],
      },
      {
        text: "6. The starter's patterns",
        items: [
          { text: 'Authentication', link: '/starter/authentication' },
          { text: 'Permissions', link: '/starter/permissions' },
          { text: 'Docker', link: '/starter/docker' },
          { text: 'Tests', link: '/starter/tests' },
          { text: 'Adding code', link: '/starter/adding-code' },
          { text: 'Where its files are', link: '/starter/map' },
        ],
      },
      {
        text: '7. Extend',
        items: [
          { text: 'Working with other packages', link: '/extend/packages' },
          { text: 'Writing a driver', link: '/extend/driver' },
        ],
      },
      {
        text: '8. Reference',
        items: [
          { text: 'Commands', link: '/reference/commands' },
          { text: 'Configuration', link: '/reference/configuration' },
          { text: 'HTTP RPC driver', link: '/reference/rpc-http' },
          { text: 'Redis event stream driver', link: '/reference/stream-redis' },
        ],
      },
    ],
  },
})
