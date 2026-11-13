import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { maturityTextsService } from '@/services/maturityTextsService'
import { assessmentsService } from '@/services/assessmentsService'
import { MATURITY_BANDS } from '@/constants/insights'
import { logger } from '@/utils/logger'

const PAGE_SIZE = 100

function blankEditor(band = MATURITY_BANDS.at(-1), code = '') {
  return { band, mainCriterionCode: code, ar: '', en: '' }
}

export function useMaturityTexts() {
  const { t } = useI18n()
  const toast = useToast()
  const status = ref('loading')
  const rows = ref([])
  const mains = ref([])
  const editor = ref(null)
  const errors = ref({})
  const saving = ref(false)

  const generic = computed(() => rows.value.filter((row) => row.isGeneric))
  const overrides = computed(() => rows.value.filter((row) => !row.isGeneric))

  async function load() {
    status.value = 'loading'
    try {
      const [texts, criteria] = await Promise.all([
        maturityTextsService.list({ perPage: PAGE_SIZE }),
        assessmentsService.getPublishedCriteria(),
      ])
      rows.value = texts.data
      mains.value = criteria.data?.mains ?? []
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  function edit(row) {
    errors.value = {}
    editor.value = row
      ? {
          ...blankEditor(row.band, row.mainCriterionCode ?? ''),
          ...row.text,
          generic: row.isGeneric,
        }
      : { ...blankEditor(), generic: false }
  }

  function cancel() {
    editor.value = null
    errors.value = {}
  }

  async function save(draft) {
    saving.value = true
    errors.value = {}
    logger.breadcrumb('form', 'content.maturityTexts.save', { band: draft.band })
    try {
      const payload = {
        ...(draft.generic ? {} : { mainCriterionCode: draft.mainCriterionCode }),
        text: { ar: draft.ar, en: draft.en },
      }
      await maturityTextsService.save(draft.band, payload)
      toast.success(t('settings.maturityTexts.saved'))
      editor.value = null
      await load()
    } catch (error) {
      errors.value = error?.normalized?.fieldErrors ?? {}
      toast.error(error?.normalized?.message || t('settings.maturityTexts.failed'))
    } finally {
      saving.value = false
    }
  }

  async function remove(row) {
    try {
      await maturityTextsService.remove(row.id)
      toast.success(t('settings.maturityTexts.deleted'))
      await load()
    } catch (error) {
      toast.error(error?.normalized?.message || t('settings.maturityTexts.failed'))
    }
  }

  return {
    status,
    generic,
    overrides,
    mains,
    editor,
    errors,
    saving,
    load,
    edit,
    cancel,
    save,
    remove,
  }
}
