import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { assessmentsService } from '@/services/assessmentsService'
import { accreditedService } from '@/services/accreditedService'
import { usePortalTab } from '@/composables/usePortalTab'
import { ASSESSMENT_TABS } from '@/constants/assessment'

const PAGE_SIZE = 10
const CURRENT_LIMIT = 20

const QUERIES = {
  [ASSESSMENT_TABS.CURRENT]: () => ({
    'filter[is_open]': 'true',
    sort: '-created_at',
    perPage: CURRENT_LIMIT,
  }),
  [ASSESSMENT_TABS.HISTORY]: (page) => ({ sort: '-created_at', page, perPage: PAGE_SIZE }),
  [ASSESSMENT_TABS.RESULTS]: (page) => ({
    'filter[has_result]': 'true',
    sort: '-completed_at',
    page,
    perPage: PAGE_SIZE,
  }),
}

const COUNT_KEYS = {
  [ASSESSMENT_TABS.CURRENT]: 'open',
  [ASSESSMENT_TABS.HISTORY]: 'total',
  [ASSESSMENT_TABS.RESULTS]: 'withResult',
}

export function useAssessmentsHub() {
  const { t } = useI18n()
  const { tab } = usePortalTab(Object.values(ASSESSMENT_TABS))
  const items = ref([])
  const counts = ref(null)
  const accredited = ref(null)
  const status = ref('loading')
  const errorMessage = ref('')
  const page = ref(1)
  const lastPage = ref(1)

  const tabs = computed(() =>
    Object.values(ASSESSMENT_TABS).map((value) => ({
      value,
      label: t(`portal.assessments.tabs.${value}`),
      count: counts.value?.[COUNT_KEYS[value]] ?? null,
    })),
  )

  async function loadAccredited() {
    if (tab.value !== ASSESSMENT_TABS.CURRENT) return
    accredited.value = await accreditedService
      .getCurrent()
      .then(({ data }) => data)
      .catch(() => null)
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const [{ data, meta }] = await Promise.all([
        assessmentsService.list(QUERIES[tab.value](page.value)),
        loadAccredited(),
      ])
      items.value = data
      counts.value = meta?.counts ?? counts.value
      lastPage.value = meta?.pagination?.lastPage ?? 1
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  function setPage(next) {
    if (next < 1 || next > lastPage.value || next === page.value) return
    page.value = next
  }

  watch(tab, () => {
    if (page.value === 1) load()
    else page.value = 1
  })
  watch(page, load)
  onMounted(load)

  return {
    tab,
    tabs,
    items,
    counts,
    accredited,
    status,
    errorMessage,
    page,
    lastPage,
    load,
    setPage,
  }
}
