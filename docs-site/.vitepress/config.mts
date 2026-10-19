import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Laravel Distributable',
  description: 'Design each Laravel module as a microservice, in one process or many.',
  rewrites: { 'README.md': 'index.md' },
  ignoreDeadLinks: [/LICENSE$/],
  cleanUrls: true,
  base: '/laravel-distributable/',
  themeConfig: {
    search: { provider: 'local' },
    outline: [2, 3],
    socialLinks: [{ icon: 'github', link: 'https://github.com/mk-josias/laravel-distributable' }],
    nav: [
      { text: 'Overview', link: '/' },
      { text: 'Starter', link: '/starter' },
      { text: 'Modules', link: '/docs/modules' },
      { text: 'Packagist', link: 'https://packagist.org/packages/mk-josias/laravel-distributable' },
    ],
    sidebar: [
      {
        text: 'Introduction',
        items: [
          { text: 'What it is', link: '/' },
          { text: 'The charter', link: '/docs/charter' },
        ],
      },
      {
        text: 'Try it',
        items: [
          { text: 'The starter', link: '/starter' },
          { text: 'Its three modules', link: '/docs/skeleton' },
          { text: 'Adding it to an existing application', link: '/docs/getting-started' },
        ],
      },
      {
        text: 'Write a module',
        items: [
          { text: 'Create a module', link: '/docs/modules' },
          { text: 'Its settings and services', link: '/docs/module-context' },
          { text: 'Its database', link: '/docs/databases' },
          { text: 'Migrations', link: '/docs/migrations' },
          { text: 'Testing a module', link: '/docs/tests' },
          { text: 'Adding code to a module', link: '/docs/adding-code' },
        ],
      },
      {
        text: 'Modules talking',
        items: [
          { text: 'Calling another module', link: '/docs/rpc' },
          { text: 'Publishing and handling events', link: '/docs/events' },
          { text: "Keeping a copy of another module's data", link: '/docs/shadows' },
          { text: 'Programs in other languages', link: '/docs/other-languages' },
        ],
      },
      {
        text: 'Starter features',
        items: [
          { text: 'Authentication', link: '/docs/authentication' },
          { text: 'Permissions', link: '/docs/permissions' },
          { text: 'Docker', link: '/docs/docker' },
        ],
      },
      {
        text: 'Running in production',
        items: [
          { text: 'One application or several', link: '/docs/applications' },
          { text: 'Moving a module out', link: '/docs/moving-a-module' },
          { text: 'Deploying', link: '/docs/deploying' },
          { text: 'Operating the event stream', link: '/docs/operating-streams' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'Configuration', link: '/docs/configuration' },
          { text: 'Commands', link: '/docs/commands' },
          { text: 'HTTP RPC driver', link: '/docs/rpc-http' },
          { text: 'Redis event stream driver', link: '/docs/stream-redis' },
          { text: 'Working with other packages', link: '/docs/other-packages' },
          { text: 'Writing a driver', link: '/docs/extending' },
        ],
      },
    ],
  },
})
