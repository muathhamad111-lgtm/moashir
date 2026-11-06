import { defineConfig, globalIgnores } from 'eslint/config'
import globals from 'globals'
import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import pluginPlaywright from 'eslint-plugin-playwright'
import pluginVitest from '@vitest/eslint-plugin'
import pluginOxlint from 'eslint-plugin-oxlint'
import skipFormatting from 'eslint-config-prettier/flat'

export default defineConfig([
  {
    name: 'app/files-to-lint',
    files: ['**/*.{vue,js,mjs,jsx}'],
  },

  globalIgnores([
    '**/dist/**',
    '**/dist-ssr/**',
    '**/coverage/**',
    'public/**',
    '**/node_modules/**',
    '**/playwright-report/**',
    '**/playwright-report-integration/**',
    '**/test-results/**',
  ]),

  {
    languageOptions: {
      globals: {
        ...globals.browser,
      },
    },
  },

  js.configs.recommended,
  ...pluginVue.configs['flat/essential'],

  {
    name: 'app/views-single-word-names',
    files: ['src/views/**/*.vue'],
    rules: {
      'vue/multi-word-component-names': 'off',
    },
  },

  {
    name: 'app/catalog-single-word-names',
    files: [
      'src/components/ui/Badge.vue',
      'src/components/ui/Card.vue',
      'src/components/ui/Tabs.vue',
      'src/components/ui/Accordion.vue',
      'src/components/ui/Tooltip.vue',
      'src/components/layout/Sidebar.vue',
      'src/components/data-list/Pagination.vue',
      'src/components/feedback/Skeleton.vue',
    ],
    rules: {
      'vue/multi-word-component-names': 'off',
    },
  },

  {
    ...pluginPlaywright.configs['flat/recommended'],
    files: ['e2e/**/*.{test,spec}.{js,ts,jsx,tsx}'],
  },

  {
    ...pluginVitest.configs.recommended,
    files: ['src/**/__tests__/*'],
  },

  {
    name: 'app/node-scripts',
    files: ['scripts/**/*.{js,mjs}'],
    languageOptions: {
      globals: {
        ...globals.node,
      },
    },
  },

  ...pluginOxlint.buildFromOxlintConfigFile('.oxlintrc.json'),

  skipFormatting,
])
