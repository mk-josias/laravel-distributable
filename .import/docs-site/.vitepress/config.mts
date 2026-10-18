import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Laravel Microservices',
  description: 'Laravel applications working together as microservices: signed RPC, events, read-only copies.',
  rewrites: { 'README.md': 'index.md' },
  cleanUrls: true,
  base: '/laravel-microservices/',
  themeConfig: {
    search: { provider: 'local' },
    outline: [2, 3],
    socialLinks: [{ icon: 'github', link: 'https://github.com/mk-josias/laravel-microservices' }],
    nav: [
      { text: 'Overview', link: '/' },
      { text: 'Calls', link: '/docs/rpc' },
      { text: 'Events', link: '/docs/events' },
      { text: 'Modules', link: 'https://mk-josias.github.io/laravel-distributable-modules/' },
      { text: 'Packagist', link: 'https://packagist.org/packages/mk-josias/laravel-microservices' },
    ],
    sidebar: [
      {
        text: 'Start',
        items: [
          { text: 'Overview', link: '/' },
          { text: 'Installation', link: '/#installation' },
          { text: 'The foundation', link: '/#the-foundation' },
          { text: 'Configuration', link: '/docs/configuration' },
        ],
      },
      {
        text: 'Calls',
        items: [
          { text: 'Calling another service', link: '/#calling-another-service' },
          { text: 'Calls (RPC)', link: '/docs/rpc' },
          { text: 'Services in other languages', link: '/docs/other-languages' },
        ],
      },
      {
        text: 'Events',
        items: [
          { text: 'Announcing and handling events', link: '/#announcing-and-handling-events' },
          { text: 'Events', link: '/docs/events' },
          { text: 'Consuming', link: '/docs/events#consuming' },
          { text: 'Idempotent handlers', link: '/docs/events#idempotent-handlers' },
          { text: 'Writing a transport', link: '/docs/transports' },
        ],
      },
      {
        text: 'Copies of rows',
        items: [
          { text: 'Keeping a copy', link: '/#keeping-a-copy-of-another-service-s-rows' },
          { text: 'Copies', link: '/docs/shadows' },
        ],
      },
      {
        text: 'Drivers',
        items: [
          { text: 'HTTP calls', link: '/docs/drivers/rpc-http' },
          { text: 'Redis streams', link: '/docs/drivers/stream-redis' },
          { text: 'Laravel queues', link: '/docs/drivers/stream-queue' },
          { text: 'Extending', link: '/#extending' },
        ],
      },
    ],
  },
})
