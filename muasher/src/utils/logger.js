import * as Sentry from '@sentry/vue'

const sentryEnabled = Boolean(import.meta.env.VITE_SENTRY_DSN)

export const logger = {
  breadcrumb(category, message, data = {}) {
    if (!sentryEnabled) return
    Sentry.addBreadcrumb({ category, message, data, level: 'info' })
  },
  info(message, context = {}) {
    if (!sentryEnabled) return
    Sentry.captureMessage(message, { level: 'info', extra: context })
  },
  error(error, context = {}) {
    if (!sentryEnabled) return
    Sentry.captureException(error, { extra: context })
  },
}
