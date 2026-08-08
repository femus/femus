import { defineConfig } from 'vitepress'
import llmstxt from 'vitepress-plugin-llms'

export default defineConfig({
  title: 'femus',
  description: 'Hardware for PHP developers — Arduino, sensors, radio and GSM from plain PHP',
  base: '/femus/',
  cleanUrls: true,

  vite: {
    plugins: [llmstxt()],
  },

  themeConfig: {
    nav: [
      { text: 'Guide', link: '/guide/introduction' },
      { text: 'GitHub', link: 'https://github.com/femus/femus' },
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Introduction', link: '/guide/introduction' },
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Quick Start', link: '/guide/quick-start' },
        ],
      },
      {
        text: 'The Basics',
        items: [
          { text: 'Board & Ports', link: '/guide/board' },
          { text: 'Event Loop', link: '/guide/event-loop' },
          { text: 'Testing Without Hardware', link: '/guide/testing' },
        ],
      },
      {
        text: 'Devices',
        items: [
          { text: 'Overview', link: '/devices/' },
        ],
      },
      {
        text: 'Going Further',
        items: [
          { text: '433 MHz Radio', link: '/guide/radio' },
          { text: 'GSM & SMS', link: '/guide/gsm' },
          { text: 'Firmware & CLI', link: '/guide/firmware' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/femus/femus' },
    ],

    footer: {
      message: 'Released under the MIT License.',
    },

    search: { provider: 'local' },
  },
})
