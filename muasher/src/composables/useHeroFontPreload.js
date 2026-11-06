import { computed } from 'vue'
import { useHead } from '@unhead/vue'
import { useI18n } from 'vue-i18n'
import arabicHeading from '@/assets/fonts/alexandria-arabic-700.woff2?url'
import latinHeading from '@/assets/fonts/montserrat-latin-variable.woff2?url'

const HERO_FONTS = Object.freeze({ ar: arabicHeading, en: latinHeading })

export function useHeroFontPreload() {
  const { locale } = useI18n()
  const href = computed(() => HERO_FONTS[locale.value] ?? HERO_FONTS.ar)

  useHead({
    link: () => [
      { rel: 'preload', as: 'font', type: 'font/woff2', href: href.value, crossorigin: '' },
    ],
  })
}
