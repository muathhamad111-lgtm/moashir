<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import LevelBadge from '@/components/level/LevelBadge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import { usePermissions } from '@/composables/usePermissions'
import { localizedText } from '@/utils/localizedText'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { displayTierOf } from '@/utils/levelTier'
import { REQUEST_FACTS as F } from '@/utils/requests/requestFactSets'
import { COMPLETION_STATUSES } from '@/constants/accredited'

// One fact's value on the request record, by its key: the summary card's groups and the «بيانات الطلب» card share
// it. A flag on a value (reassigned, overdue) is a short text note under it, never a floating badge.
const props = defineProps({
  detail: { type: Object, required: true },
  fact: { type: String, required: true },
})

const { t, n, locale } = useI18n()
const { can } = usePermissions()
const entityRoute = computed(() =>
  props.detail.entity?.id && can(PERMISSIONS.ENTITIES_VIEW)
    ? { name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: props.detail.entity.id } }
    : null,
)
const DATES = {
  [F.OPENED]: (d) => d.record?.createdAt,
  [F.UPDATED]: (d) => d.record?.updatedAt,
  [F.SUBMITTED]: (d) => d.submittedAt,
  [F.ASSIGNED]: (d) => d.assignedAt,
  [F.DEADLINE]: (d) => d.completionDeadline,
  [F.COMPLETED]: (d) => d.completedAt,
}
const dateValue = computed(() => DATES[props.fact]?.(props.detail) ?? null)
const openCompletions = computed(
  () =>
    props.detail.completionRequests.filter((item) => item.status === COMPLETION_STATUSES.OPEN)
      .length,
)
</script>

<template>
  <span v-if="fact === F.ENTITY && detail.entity" class="request-fact-entity">
    <router-link v-if="entityRoute" :to="entityRoute" data-testid="request-entity">{{
      localizedText(detail.entity.name, locale)
    }}</router-link>
    <span v-else data-testid="request-entity">{{ localizedText(detail.entity.name, locale) }}</span>
    <bdi dir="ltr" class="record-identity__code">{{ detail.entity.reference }}</bdi>
    <LevelBadge
      :tier="displayTierOf(detail.entity)"
      size="s"
      :certificate="detail.entity.lastLevel"
    />
  </span>
  <template v-else-if="fact === F.STATUS">
    <StatusBadge :status="detail.status" />
    <span v-if="detail.isFrozen" class="record-fact__note record-fact__note--danger">{{
      t('entities.frozen.badge')
    }}</span>
  </template>
  <template v-else-if="fact in DATES">
    <DateStamp :value="dateValue" :with-time="false" with-hijri />
  </template>
  <template v-else-if="fact === F.DUE">
    <DateStamp :value="detail.dueAt" :with-time="false" with-hijri />
    <span
      v-if="detail.dueAt && !detail.completedAt"
      class="record-fact__note"
      :class="detail.isOverdue ? 'record-fact__note--danger' : 'record-fact__note--success'"
      >{{ detail.isOverdue ? t('requests.overdue') : t('requests.onTime') }}</span
    >
  </template>
  <template v-else-if="fact === F.ASSESSOR">
    <span data-testid="request-assessor">{{
      detail.assessor?.name ?? t('requests.unassigned')
    }}</span>
    <span v-if="detail.isReassigned" class="record-fact__note">{{ t('requests.reassigned') }}</span>
  </template>
  <template v-else-if="fact === F.FEE">{{
    detail.payment.paid ? t('requests.detail.paid') : t('requests.detail.notPaid')
  }}</template>
  <LevelBadge
    v-else-if="fact === F.LEVEL && detail.entity"
    :tier="displayTierOf(detail.entity)"
    size="s"
    :certificate="detail.entity.lastLevel"
  />
  <template v-else-if="fact === F.ASSIGNMENTS">{{
    n(detail.assignmentHistory.length, 'default')
  }}</template>
  <template v-else-if="fact === F.COMPLETIONS">{{
    t('requests.detail.facts.completionsValue', {
      total: n(detail.completionRequests.length, 'default'),
      open: n(openCompletions, 'default'),
    })
  }}</template>
  <template v-else-if="fact === F.CYCLE">{{
    localizedText(detail.awardCycle?.name, locale)
  }}</template>
  <template v-else-if="fact === F.REGION">{{
    localizedText(detail.region?.name, locale)
  }}</template>
</template>
