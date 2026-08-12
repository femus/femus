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
          { text: 'Raspberry Pi GPIO', link: '/guide/linux-board' },
          { text: 'Event Loop', link: '/guide/event-loop' },
          { text: 'Testing Without Hardware', link: '/guide/testing' },
        ],
      },
      {
        text: 'Devices',
        collapsed: false,
        items: [
          { text: 'Overview', link: '/devices/' },
          { text: 'Wiring Basics', link: '/devices/wiring-basics' },
          { text: 'Arduino Nano Pinout', link: '/devices/arduino-nano-pinout' },
          { text: 'Button', link: '/devices/button' },
          { text: 'Analog Sensors', link: '/devices/analog-sensor' },
          { text: 'Rotary Encoder (KY-040)', link: '/devices/rotary-encoder' },
          { text: 'Shift Register (74HC595)', link: '/devices/74hc595' },
          { text: 'Load Cell (HX711)', link: '/devices/hx711' },
          { text: 'LCD 16×2', link: '/devices/lcd1602' },
          { text: 'Gyro (MPU-6050)', link: '/devices/mpu6050' },
          { text: 'Thermometer (DS18B20)', link: '/devices/ds18b20' },
          { text: '433 MHz Radio Modules', link: '/devices/radio-433' },
          { text: 'BLE Radio Bridge', link: '/devices/radio-ble-bridge' },
          { text: 'HC-05 / HM-10 Bluetooth', link: '/devices/hc05-bluetooth' },
          { text: 'GSM Modem', link: '/devices/gsm-modem' },
        ],
      },
      {
        text: 'Going Further',
        items: [
          { text: 'PWM — brightness & RGB', link: '/guide/pwm' },
          { text: 'Smart Pantry — jar levels', link: '/guide/pantry' },
          { text: '433 MHz Radio', link: '/guide/radio' },
          { text: 'GSM & SMS', link: '/guide/gsm' },
          { text: 'SMS Gateway', link: '/guide/sms-gateway' },
          { text: 'Firmware & CLI', link: '/guide/firmware' },
          { text: 'AI Agents (MCP)', link: '/guide/mcp' },
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
