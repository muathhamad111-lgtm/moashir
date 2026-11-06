<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import AwardPageHeader from '@/components/committee/AwardPageHeader.vue'
import ResultsTabs from '@/components/committee/ResultsTabs.vue'
import RegionResultsCard from '@/components/committee/RegionResultsCard.vue'
import { useResultsBoard } from '@/composables/useResultsBoard'
import { usePermissions } from '@/composables/usePermissions'
import { useConfirm } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { COMMITTEE_LIMITS } from '@/constants/committee'
import { localizedText } from '@/utils/localizedText'

const { t, locale } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const { can } = usePermissions()
const { confirm } = useConfirm()
const cycleId = computed(() => Number(route.params.id))
const board = useResultsBoard(cycleId, 'regional')
const { cycle, status, errorMessage, rows, meta, national, busy, reasons } = board
const unapproving = ref(null)

useHead({ title: () => t('award.results.title') })

const locked = computed(() => national.value.approved)
const regions = computed(() =>
  (meta.value?.regions ?? []).filter((region) => region.total || region.pending),
)
const rowsOf = (region) => rows.value.filter((row) => row.region?.id === region.id)
const unapproveOpen = computed({
  get: () => unapproving.value !== null,
  set: (open) => {
    if (!open) unapproving.value = null
  },
})

async function approve(region) {
  const name = localizedText(region.name, locale.value)
  if (
    await confirm({
      title: t('award.results.approveTitle'),
      message: t('award.results.approveMessage', { region: name }),
      confirmLabel: t('award.results.approveRegion'),
    })
  )
    board.approveRegion(region)
}

function unapprove(reason) {
  return board.unapproveRegion(unapproving.value, reason)
}
</script>

<template>
  <section class="cycles-page results-board">
    <AwardPageHeader
      :title="t('award.results.title')"
      :cycle="cycle"
      :back="{ name: ROUTE_NAMES.AWARD_RESULTS }"
      :back-label="t('award.common.backToCycles')"
    />
    <ResultsTabs :cycle-id="cycleId" />
    <AssessmentNotice
      variant="info"
      :title="t('award.results.decisionTitle')"
      :message="t('award.results.decisionNote')"
    />
    <AssessmentNotice
      v-if="locked"
      variant="info"
      :title="t('award.results.lockedTitle')"
      :message="t('award.results.lockedMessage')"
    />
    <div v-if="status === 'loading'" aria-busy="true"><Skeleton variant="block" /></div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="board.load" />
    <EmptyState
      v-else-if="status === 'missing' || status === 'blocked'"
      icon="lock"
      :title="t('award.results.unavailableTitle')"
      :message="errorMessage"
    />
    <EmptyState
      v-else-if="status === 'empty' && !regions.length"
      icon="trophy"
      :title="t('award.results.emptyTitle')"
      :message="t('award.results.emptyMessage')"
    />
    <div v-else class="results-board__regions">
      <RegionResultsCard
        v-for="region in regions"
        :key="region.id"
        :region="region"
        :rows="rowsOf(region)"
        :can-rank="can(PERMISSIONS.AWARD_RESULTS_RANK) && !locked"
        :can-approve="can(PERMISSIONS.AWARD_RESULTS_APPROVE) && !locked"
        :can-unapprove="auth.isSuperAdmin && !locked"
        :busy="busy"
        @rank="board.setRank"
        @approve="approve"
        @unapprove="unapproving = $event"
      />
    </div>
    <ReasonDialog
      v-if="unapproving"
      v-model="unapproveOpen"
      :title="
        t('award.results.unapproveTitle', { region: localizedText(unapproving.name, locale) })
      "
      :message="t('award.results.unapproveMessage')"
      :submit-label="t('award.results.unapproveRegion')"
      :min-length="COMMITTEE_LIMITS.UNAPPROVE_REASON_MIN"
      :action="unapprove"
      action-name="results.unapproveRegion"
    />
    <ReasonDialog
      v-model="reasons.prompt.open.value"
      :title="t('review.reasonTitle')"
      :message="t('review.reasonMessage')"
      :submit-label="t('actions.confirm')"
      :action="reasons.prompt.provide"
      action-name="results.superAdminReason"
    />
  </section>
</template>
