import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { awardResultsService } from '@/services/awardResultsService'
import { awardCycleService } from '@/services/awardCycleService'
import { useAwardCycle } from '@/composables/useAwardCycle'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { useToast } from '@/composables/useToast'
import { errorMessageOf, failureState } from '@/utils/award/loadState'
import { RANK_NONE } from '@/constants/committee'

const STAGES = {
  regional: {
    list: (cycleId) => awardResultsService.listRegional(cycleId),
    rank: (cycleId, id, payload) => awardResultsService.setRegionalRank(cycleId, id, payload),
  },
  national: {
    list: (cycleId) => awardResultsService.listNational(cycleId),
    rank: (cycleId, id, payload) => awardResultsService.setNationalRank(cycleId, id, payload),
  },
}

export function useResultsBoard(cycleId, stage) {
  const { t } = useI18n()
  const toast = useToast()
  const reasons = useSuperAdminReason()
  const { cycle, loadCycle } = useAwardCycle(cycleId)
  const status = ref('loading')
  const errorMessage = ref('')
  const rows = ref([])
  const meta = ref(null)
  const busy = ref('')

  const national = computed(
    () => meta.value?.national ?? { approved: false, approvedAt: null, announcedAt: null },
  )

  async function load({ silent = false } = {}) {
    if (!silent) status.value = 'loading'
    errorMessage.value = ''
    try {
      const envelope = await STAGES[stage].list(cycleId.value)
      rows.value = envelope.data
      meta.value = envelope.meta
      status.value = rows.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = errorMessageOf(error)
      status.value = failureState(error)
    }
  }

  async function perform(key, run, successKey, params = {}) {
    if (busy.value) return false
    busy.value = key
    try {
      const extra = await reasons.reasonExtra()
      if (extra === null) return false
      await run(extra)
      toast.success(t(successKey, params))
      await Promise.all([load({ silent: true }), loadCycle()])
      return true
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
      await load({ silent: true })
      return false
    } finally {
      busy.value = ''
    }
  }

  function setRank(row, value) {
    const rank = value === RANK_NONE || value === null ? null : Number(value)
    return perform(
      `rank-${row.assessmentId}`,
      (extra) => STAGES[stage].rank(cycleId.value, row.assessmentId, { rank, ...extra }),
      'award.results.toasts.ranked',
      { number: row.number },
    )
  }

  function approveRegion(region) {
    return perform(
      `region-${region.id}`,
      (extra) => awardResultsService.approveRegion(cycleId.value, region.id, extra),
      'award.results.toasts.regionApproved',
    )
  }

  async function unapproveRegion(region, reason) {
    await awardResultsService.unapproveRegion(cycleId.value, region.id, { reason })
    toast.success(t('award.results.toasts.regionUnapproved'))
    await load({ silent: true })
  }

  function approveNational() {
    return perform(
      'national',
      (extra) => awardResultsService.approveNational(cycleId.value, extra),
      'award.results.toasts.nationalApproved',
    )
  }

  function announce() {
    return perform(
      'announce',
      (extra) => awardCycleService.announce(cycleId.value, extra),
      'award.results.toasts.announced',
    )
  }

  onMounted(load)

  return {
    cycle,
    status,
    errorMessage,
    rows,
    meta,
    national,
    busy,
    reasons,
    load,
    setRank,
    approveRegion,
    unapproveRegion,
    approveNational,
    announce,
  }
}
