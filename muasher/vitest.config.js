import { fileURLToPath } from 'node:url'
import { mergeConfig, defineConfig, configDefaults } from 'vitest/config'
import viteConfig from './vite.config'

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      setupFiles: ['./src/test-utils/jsdomStorage.js'],
      exclude: [...configDefaults.exclude, 'e2e/**'],
      css: { include: [/styles\/tokens\/.+\.module\.scss$/] },
      root: fileURLToPath(new URL('./', import.meta.url)),
    },
  }),
)
