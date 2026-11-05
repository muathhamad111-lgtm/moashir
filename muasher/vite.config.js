import process from 'node:process'
import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import { apiProxy } from './vite-plugins/apiProxy'
import { buildPosture } from './vite-plugins/buildPosture'

const proxy = apiProxy(process.env.MOASHIR_API_PROXY_TARGET)

export default defineConfig({
  plugins: [buildPosture(), vue(), vueDevTools()],
  define: {
    __VUE_I18N_FULL_INSTALL__: true,
    __VUE_I18N_LEGACY_API__: false,
    __INTLIFY_DROP_MESSAGE_COMPILER__: false,
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: { proxy },
  preview: { proxy },
})
