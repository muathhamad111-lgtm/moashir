<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import AwardPageHeader from '@/components/committee/AwardPageHeader.vue'
import ResultsTabs from '@/components/committee/ResultsTabs.vue'
import ResultsRowTable from '@/components/committee/ResultsRowTable.vue'
import AnnouncementCard from '@/components/committee/AnnouncementCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useResultsBoard } from '@/composables/useResultsBoard'
import { usePermissions } from '@/composables/usePermissions'
import { useConfirm } from '@/composables/useConfirm'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { MEMBER_ROLES } from '@/constants/advanced'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const route = useRoute()
const { can } = usePermissions()
const { confirm } = useConfirm()
const auth = useAuthStore()
const cycleId = computed(() => Number(route.params.id))
const board = useResultsBoard(cycleId, 'national')
const { cycle, status, errorMessage, rows, meta, national, busy, reasons } = board

useHead({ title: () => t('award.national.title') })

const pendingRegions = computed(() =>
  (meta.value?.regions ?? []).filter(
    (region) => region.pending > 0 || (region.total > 0 && !region.approved),
  ),
)
const announces = computed(
  () =>
    can(PERMISSIONS.AWARD_RESULTS_ANNOUNCE) &&
    (auth.isSuperAdmin ||
      (cycle.value?.members ?? []).some(
        (member) => member.id === auth.user?.id && member.role === MEMBER_ROLES.AWARD_SECRETARY,
      )),
)
const ranked = computed(() => rows.value.some((row) => row.nationalRank !== null))

async function approve() {
  if (
    await confirm({
      title: t('award.national.approveTitle'),
      message: t('award.national.approveMessage'),
      confirmLabel: t('award.national.approve'),
    })
  )
    board.approveNational()
}
</script>

<template>
  <section class="cycles-page results-board">
    <AwardPageHeader
      :title="t('award.national.title')"
      :cycle="cycle"
      :back="{ name: ROUTE_NAMES.AWARD_RESULTS }"
      :back-label="t('award.common.backToCycles')"
    />
    <ResultsTabs :cycle-id="cycleId" />
    <AssessmentNotice
      variant="info"
      :title="t('award.results.decisionTitle')"
      :message="t('award.national.decisionNote')"
    />
    <AssessmentNotice
      v-if="pendingRegions.length && !national.approved"
      variant="warning"
      :title="t('award.national.regionsPendingTitle')"
      :message="
        t(
          'award.national.regionsPendingMessage',
          { count: pendingRegions.length },
          pendingRegions.length,
        )
      "
    />
    <div v-if="status === 'loading'" aria-busy="true"><Skeleton variant="block" /></div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="board.load" />
    <EmptyState
      v-else-if="status === 'missing' || status === 'blocked'"
      icon="lock"
      :title="t('award.results.unavailableTitle')"
      :message="errorMessage"
    />
    <template v-else>
      <Card :title="t('award.national.candidates')">
        <EmptyState
          v-if="status === 'empty'"
          icon="trophy"
          :title="t('award.national.emptyTitle')"
          :message="t('award.national.emptyMessage')"
        />
        <ResultsRowTable
          v-else
          :rows="rows"
          rank-field="nationalRank"
          show-region
          :can-rank="can(PERMISSIONS.AWARD_RESULTS_RANK) && !national.approved"
          :busy="busy"
          @rank="board.setRank"
        />
        <div
          v-if="can(PERMISSIONS.AWARD_RESULTS_APPROVE) && !national.approved && rows.length"
          class="region-results__actions"
        >
          <BaseButton
            variant="primary"
            icon="stamp"
            :loading="busy === 'national'"
            :disabled="!ranked || pendingRegions.length > 0 || Boolean(busy)"
            @click="approve"
            >{{ t('award.national.approve') }}</BaseButton
          >
        </div>
      </Card>
      <AnnouncementCard
        :cycle-status="meta?.cycleStatus ?? ''"
        :national="national"
        :can-announce="announces"
        :busy="busy === 'announce'"
        @announce="board.announce"
      />
    </template>
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
