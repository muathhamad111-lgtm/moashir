import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { LOCALE_DIR } from '@/constants/locales'

export function useChartDirection() {
  const { locale } = useI18n()
  return { isRtl: computed(() => LOCALE_DIR[locale.value] === 'rtl') }
}
