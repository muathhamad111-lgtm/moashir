import * as Sentry from '@sentry/vue'
import { i18n } from '@/i18n'
import { logger } from '@/utils/logger'
import { toast } from '@/composables/useToast'
import { SENTRY_SCRUBBING } from '@/utils/monitoring/scrubEvent'

export function installSentry(app, router) {
  app.config.errorHandler = (error, instance, info) => {
    logger.error(error, { vueInfo: info })
    toast.error(i18n.global.t('errors.unexpected'))
  }

  window.addEventListener('unhandledrejection', (event) => {
    logger.error(event.reason, { source: 'unhandledrejection' })
    toast.error(i18n.global.t('errors.unexpected'))
  })

  const dsn = import.meta.env.VITE_SENTRY_DSN
  if (!dsn) return

  Sentry.init({
    app,
    dsn,
    environment: import.meta.env.VITE_SENTRY_ENVIRONMENT || import.meta.env.MODE,
    release: import.meta.env.VITE_SENTRY_RELEASE || undefined,
    integrations: [Sentry.browserTracingIntegration({ router })],
    tracesSampleRate: 0.2,
    ...SENTRY_SCRUBBING,
  })
}
