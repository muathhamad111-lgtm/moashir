import { i18n } from '@/i18n'

export function applyAcceptLanguage(config) {
  config.headers.set('Accept-Language', i18n.global.locale.value)
  return config
}
