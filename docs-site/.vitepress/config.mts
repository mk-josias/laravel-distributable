import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Laravel Distributable Modules',
  description: 'Design each Laravel module as a microservice, in one process or many.',
  rewrites: { 'README.md': 'index.md' },
  ignoreDeadLinks: [/LICENSE$/],
  cleanUrls: true,
  base: '/laravel-distributable-modules/',
  themeConfig: {
    search: { provider: 'local' },
    outline: [2, 3],
    socialLinks: [{ icon: 'github', link: 'https://github.com/mk-josias/laravel-distributable-modules' }],
    nav: [
      { text: 'Overview', link: '/' },
      { text: 'Starter', link: '/starter' },
      { text: 'Modules', link: '/docs/modules' },
      { text: 'Engine', link: 'https://mk-josias.github.io/laravel-microservices/' },
      { text: 'Packagist', link: 'https://packagist.org/packages/mk-josias/laravel-distributable-modules' },
    ],
    sidebar: [
      {
        text: 'Philosophy',
        items: [
          { text: 'Overview', link: '/' },
          { text: 'Why', link: '/#why' },
          { text: 'How it works', link: '/#how-it-works' },
          { text: 'The charter (7 rules)', link: '/docs/charter' },
          { text: 'What the package includes', link: '/#what-the-package-includes' },
          { text: 'Out of scope', link: '/#out-of-scope' },
        ],
      },
      {
        text: 'Start',
        items: [
          { text: 'Adding it to a Laravel application', link: '/docs/getting-started' },
          { text: 'The starter', link: '/starter' },
          { text: 'Running it', link: '/starter#running-it' },
          { text: 'What is in the skeleton', link: '/starter#what-is-in-the-skeleton' },
          { text: 'Authentication', link: '/starter#authentication' },
          { text: 'Permissions', link: '/starter#permissions' },
          { text: 'Running a module in its own process', link: '/starter#running-a-module-in-its-own-process' },
          { text: 'Docker', link: '/starter#docker' },
          { text: 'Tests', link: '/starter#tests' },
          { text: 'Adding code to a module', link: '/starter#adding-code-to-a-module' },
        ],
      },
      {
        text: 'Modules',
        items: [
          { text: 'Modules', link: '/docs/modules' },
          { text: 'The module context', link: '/docs/module-context' },
          { text: 'What the foundation holds', link: '/docs/modules#what-the-foundation-holds' },
          { text: "Each module's data", link: '/docs/databases' },
          { text: 'Migrations', link: '/docs/migrations' },
          { text: 'Working with other packages', link: '/docs/other-packages' },
          { text: 'Extending', link: '/docs/extending' },
          { text: 'Configuration', link: '/docs/configuration' },
        ],
      },
      {
        text: 'Deploy',
        items: [
          { text: 'Deploying modules', link: '/docs/deploying' },
          { text: 'Placing a module', link: '/docs/deploying#placing-a-module' },
          { text: 'One image per module', link: '/docs/deploying#one-image-per-module' },
          { text: 'What a container does', link: '/docs/deploying#what-a-container-does' },
          { text: 'Deploying a module on its own', link: '/docs/deploying#deploying-a-module-on-its-own' },
          { text: 'Checking a deployment', link: '/docs/deploying#checking-a-deployment' },
        ],
      },
      {
        text: 'How modules talk',
        items: [
          { text: 'Calls (RPC)', link: '/docs/rpc' },
          { text: 'Rules of RPC between modules', link: '/docs/rpc#rules-of-rpc-between-modules' },
          { text: 'Events', link: '/docs/events' },
          { text: 'Consuming events per module', link: '/docs/events#consuming-and-publishing-per-module' },
          { text: 'Read-only copies', link: '/docs/shadows' },
          { text: 'Services in other languages', link: '/docs/other-languages' },
        ],
      },
      {
        text: 'Engine',
        items: [{ text: 'laravel-microservices docs', link: 'https://mk-josias.github.io/laravel-microservices/' }],
      },
    ],
  },
})
