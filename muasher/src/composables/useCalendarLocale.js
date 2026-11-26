import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { LOCALE_DIR } from '@/constants/locales'
import { dateOfIso } from '@/utils/calendarMonth'

const UTC = 'UTC'

function gregorianTag(locale) {
  return `${locale}-u-ca-gregory-nu-latn`
}

function formatterOf(locale, options) {
  return new Intl.DateTimeFormat(gregorianTag(locale), { ...options, timeZone: UTC })
}

export function useCalendarLocale() {
  const { locale } = useI18n()

  const formatters = computed(() => ({
    title: formatterOf(locale.value, { month: 'long', year: 'numeric' }),
    weekdayShort: formatterOf(locale.value, { weekday: 'short' }),
    weekdayLong: formatterOf(locale.value, { weekday: 'long' }),
    day: formatterOf(locale.value, { day: 'numeric' }),
    fullDate: formatterOf(locale.value, { dateStyle: 'full' }),
  }))
  const isRtl = computed(() => LOCALE_DIR[locale.value] === 'rtl')

  function weekdaysOf(week) {
    return week.map((iso) => ({
      short: formatters.value.weekdayShort.format(dateOfIso(iso)),
      long: formatters.value.weekdayLong.format(dateOfIso(iso)),
    }))
  }

  return {
    isRtl,
    weekdaysOf,
    monthTitle: (iso) => formatters.value.title.format(dateOfIso(iso)),
    dayNumber: (iso) => formatters.value.day.format(dateOfIso(iso)),
    fullDate: (iso) => formatters.value.fullDate.format(dateOfIso(iso)),
  }
}
