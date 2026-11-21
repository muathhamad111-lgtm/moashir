import { computed, ref, unref } from 'vue'
import { useI18n } from 'vue-i18n'
import { contentService } from '@/services/contentService'

const LINE_BREAK = /\r?\n/

function localized(text, locale) {
  if (!text) return ''
  return text[locale] || text.ar || text.en || ''
}

export function usePathGuide(pathSource) {
  const { locale } = useI18n()
  const guide = ref(null)
  const status = ref('idle')

  async function load() {
    status.value = 'loading'
    try {
      guide.value = (await contentService.getPathGuide(unref(pathSource))).data
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  const texts = computed(() => guide.value?.entityTexts ?? {})
  const intro = computed(() => localized(texts.value.intro, locale.value))
  const afterSubmission = computed(() => localized(texts.value.afterSubmission, locale.value))
  const steps = computed(() =>
    localized(texts.value.instructions, locale.value)
      .split(LINE_BREAK)
      .map((line) => line.trim())
      .filter(Boolean),
  )
  const pathLabel = computed(() => localized(guide.value?.pathLabel, locale.value))

  return { status, intro, steps, afterSubmission, pathLabel, load }
}
