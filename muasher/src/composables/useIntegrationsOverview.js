import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { integrationsOverviewService } from '@/services/integrationsOverviewService'

export const OVERVIEW_STATES = Object.freeze({ LOADING: 'loading', SUCCESS: 'success', EMPTY: 'empty', ERROR: 'error', FORBIDDEN: 'forbidden' })

const FORBIDDEN_STATUS = 403

export function useIntegrationsOverview() {
  const { t } = useI18n()
  const rows = ref([])
  const meta = ref({})
  const status = ref(OVERVIEW_STATES.LOADING)
  const errorMessage = ref('')

  async function load() {
    status.value = OVERVIEW_STATES.LOADING
    errorMessage.value = ''
    try {
      const envelope = await integrationsOverviewService.list()
      rows.value = envelope.data ?? []
      meta.value = envelope.meta ?? {}
      status.value = rows.value.length ? OVERVIEW_STATES.SUCCESS : OVERVIEW_STATES.EMPTY
    } catch (error) {
      errorMessage.value = error?.normalized?.message || t('common.error')
      status.value = error?.response?.status === FORBIDDEN_STATUS ? OVERVIEW_STATES.FORBIDDEN : OVERVIEW_STATES.ERROR
    }
  }

  const paymentTestInProduction = computed(() => meta.value?.paymentTestModeInProduction === true)

  return { rows, status, errorMessage, paymentTestInProduction, load }
}
