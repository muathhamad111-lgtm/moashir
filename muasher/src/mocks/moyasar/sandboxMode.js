import { readCookie } from '../contract/cookies'

export const MOYASAR_FORM_COOKIE = 'moashir_moyasar_form'
export const MOYASAR_CDN_FORM = 'cdn'

export function usesMoyasarCdnForm() {
  return typeof document !== 'undefined' && readCookie(MOYASAR_FORM_COOKIE) === MOYASAR_CDN_FORM
}
