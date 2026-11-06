import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { certificatesService } from '@/services/certificatesService'
import { accreditedService } from '@/services/accreditedService'
import { assessmentsService } from '@/services/assessmentsService'
import { usePortalTab } from '@/composables/usePortalTab'
import {
  CERTIFICATE_LIST_LIMIT,
  CERTIFICATE_TAB_STATUSES,
  CERTIFICATE_TABS,
} from '@/constants/certificates'
import { ACCREDITED_STATUSES } from '@/constants/accredited'
import { issuingRequestsOf } from '@/utils/certificates/issuingRequests'

const COMPLETED_QUERY = {
  'filter[path]': 'accredited',
  'filter[status]': ACCREDITED_STATUSES.COMPLETED,
  perPage: CERTIFICATE_LIST_LIMIT,
}

function sumOf(counts, statuses) {
  if (!counts) return null
  return statuses.reduce((sum, status) => sum + (counts[status] ?? 0), 0)
}

export function useCertificatesHub() {
  const { t } = useI18n()
  const { tab } = usePortalTab(Object.values(CERTIFICATE_TABS))
  const certificates = ref([])
  const issuing = ref([])
  const statusCounts = ref(null)
  const status = ref('loading')
  const errorMessage = ref('')

  const tabs = computed(() => [
    {
      value: CERTIFICATE_TABS.VALID,
      label: t('portal.certificates.tabs.valid'),
      count: sumOf(statusCounts.value, CERTIFICATE_TAB_STATUSES[CERTIFICATE_TABS.VALID]),
    },
    {
      value: CERTIFICATE_TABS.ISSUING,
      label: t('portal.certificates.tabs.in_progress'),
      count: status.value === 'loading' ? null : issuing.value.length,
    },
    {
      value: CERTIFICATE_TABS.PREVIOUS,
      label: t('portal.certificates.tabs.previous'),
      count: sumOf(statusCounts.value, CERTIFICATE_TAB_STATUSES[CERTIFICATE_TABS.PREVIOUS]),
    },
  ])

  const visible = computed(() => {
    const statuses = CERTIFICATE_TAB_STATUSES[tab.value] ?? []
    return certificates.value.filter((certificate) => statuses.includes(certificate.status))
  })

  async function loadIssuing(allCertificates) {
    const [current, completed] = await Promise.all([
      accreditedService.getCurrent().then(({ data }) => data),
      assessmentsService.list(COMPLETED_QUERY).then(({ data }) => data),
    ])
    issuing.value = issuingRequestsOf({
      active: current?.active ?? null,
      completed,
      certifiedRequestIds: allCertificates.map((certificate) => certificate.requestId),
    })
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data, meta } = await certificatesService.listCards({
        perPage: CERTIFICATE_LIST_LIMIT,
      })
      certificates.value = data
      statusCounts.value = meta?.statusCounts ?? null
      await loadIssuing(data)
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  watch(tab, () => {
    if (status.value === 'error') load()
  })
  onMounted(load)

  return { tab, tabs, visible, issuing, statusCounts, status, errorMessage, load }
}
