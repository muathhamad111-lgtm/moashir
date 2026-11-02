import { createI18n } from 'vue-i18n'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createHead } from '@unhead/vue/client'
import ar from '@/i18n/locales/ar.json'
import en from '@/i18n/locales/en.json'
import { routes } from '@/router/routes'

export function createTestGlobals({ locale = 'ar' } = {}) {
  const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'en', messages: { ar, en } })
  const router = createRouter({ history: createMemoryHistory(), routes })
  const pinia = createPinia()
  const head = createHead()
  return {
    plugins: [i18n, router, pinia, head],
    directives: { reveal: {} },
    i18n,
    router,
    pinia,
    head,
  }
}
