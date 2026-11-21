import { createPinia } from 'pinia'
import { createHead } from '@unhead/vue/client'
import { i18n } from '@/i18n'
import { installSentry } from './sentry'
import { installSessionExpiry } from './sessionExpiry'
import { installLocalePreference } from './localePreference'

export function installPlugins(app, router) {
  const pinia = createPinia()
  app.use(pinia)
  app.use(i18n)
  app.use(createHead())
  app.use(router)
  installSentry(app, router)
  installSessionExpiry(router)
  installLocalePreference(pinia)
}
