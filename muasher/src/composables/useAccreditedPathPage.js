import { computed, onMounted, ref, shallowRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { accreditedService } from '@/services/accreditedService'
import { useEntityPathsStore } from '@/stores/entityPaths'
import { usePathSummaries } from '@/composables/usePathSummaries'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { PATH_CODES, PATH_KEYS } from '@/constants/entityPaths'
import { ACCREDITED_CODES } from '@/constants/accredited'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { ACCREDITED_STATES, isPaidPath } from '@/utils/pathStates'
import { errorCodeOf, financeErrorMessage } from '@/utils/payments/financeErrors'
import { logger } from '@/utils/logger'

const PAY_ROUTE = { name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_PAY }

function formRoute(id) {
  return { name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM, params: { id: String(id) } }
}

export function useAccreditedPathPage() {
  const i18n = useI18n()
  const { t } = i18n
  const router = useRouter()
  const toast = useToast()
  const { confirm } = useConfirm()
  const store = useEntityPathsStore()
  const { paths, status } = storeToRefs(store)
  const summaries = usePathSummaries(paths)
  const current = shallowRef(null)
  const starting = ref(false)

  const accredited = computed(() => paths.value?.accredited ?? null)
  const summary = computed(() => summaries.value?.[PATH_KEYS.ACCREDITED] ?? null)
  const apply = computed(() => current.value?.apply ?? null)
  const offersNextPeriod = computed(
    () => summary.value?.state === ACCREDITED_STATES.ASSESSED && apply.value?.canStart === true,
  )

  async function loadCurrent() {
    current.value = await accreditedService
      .getCurrent()
      .then(({ data }) => data)
      .catch(() => null)
  }

  function reload() {
    store.load().catch(() => null)
    loadCurrent()
  }

  function rejectedStart(error) {
    const code = errorCodeOf(error)
    const assessmentId = error?.normalized?.meta?.assessmentId
    if (code === PATH_CODES.PAYMENT_REQUIRED) return router.push(PAY_ROUTE)
    if (code === ACCREDITED_CODES.REQUEST_OPEN && assessmentId)
      return router.push(formRoute(assessmentId))
    toast.error(financeErrorMessage(i18n, error, 'paths.errors.startFailed', 'paths.errors'))
    reload()
    return null
  }

  async function startFree() {
    if (starting.value) return
    starting.value = true
    logger.breadcrumb('form', 'accredited.startFree.submit')
    try {
      const { data } = await accreditedService.startFree()
      toast.success(t('paths.pages.accredited.started', { number: data.number }))
      await router.push(formRoute(data.id))
    } catch (error) {
      await rejectedStart(error)
    } finally {
      starting.value = false
    }
  }

  async function applyForNextPeriod() {
    const date = accredited.value?.nextSubmissionFrom ?? ''
    const accepted = await confirm({
      title: t('paths.pages.accredited.nextPeriod.title'),
      message: t('paths.pages.accredited.nextPeriod.message', { date }),
      confirmLabel: t('paths.pages.accredited.nextPeriod.confirm'),
      variant: 'warning',
    })
    if (!accepted) return
    if (isPaidPath(accredited.value)) router.push(PAY_ROUTE)
    else startFree()
  }

  onMounted(reload)

  return {
    status,
    paths,
    accredited,
    summary,
    current,
    offersNextPeriod,
    starting,
    reload,
    startFree,
    applyForNextPeriod,
  }
}
