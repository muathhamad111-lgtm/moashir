<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import AwardStateBadge from './AwardStateBadge.vue'
import { ASSIGNMENT_STATUSES, SEAT_STATUSES } from '@/constants/committee'

const props = defineProps({
  evaluations: { type: Array, required: true },
  approvesSubstitution: { type: Function, required: true },
})

const emit = defineEmits(['view', 'approveSubstitution'])
const { t } = useI18n()
const names = computed(
  () => new Map(props.evaluations.map((row) => [row.id, row.member.user?.name])),
)

const columns = computed(() => [
  { key: 'member', label: t('award.secretary.evaluations.member') },
  { key: 'status', label: t('award.secretary.evaluations.status') },
  { key: 'percent', label: t('award.secretary.evaluations.percent') },
  { key: 'submittedAt', label: t('award.secretary.evaluations.submittedAt') },
  { key: 'actions', label: t('components.actions.label') },
])
</script>

<template>
  <DataTable
    :columns="columns"
    :items="evaluations"
    status="success"
    :empty-title="t('award.secretary.evaluations.emptyTitle')"
    :empty-message="t('award.secretary.evaluations.emptyMessage')"
  >
    <template #cell-member="{ item }">
      <span class="committee-member__name">{{ item.member.user?.name }}</span>
      <span v-if="item.isSubstitute" class="committee-member__note">{{
        t('award.secretary.evaluations.substituteFor', {
          name: names.get(item.replacesAssignmentId) ?? '',
        })
      }}</span>
    </template>
    <template #cell-status="{ item }">
      <span class="committee-work__badges">
        <AwardStateBadge kind="row" :status="item.status" />
        <Badge :variant="item.counted ? 'success' : 'muted'">{{
          item.counted
            ? t('award.secretary.evaluations.counted')
            : t('award.secretary.evaluations.notCounted')
        }}</Badge>
        <Badge v-if="item.voidReason" variant="muted">{{
          t(`award.secretary.evaluations.void.${item.voidReason}`)
        }}</Badge>
        <AwardStateBadge
          v-if="item.member.status !== SEAT_STATUSES.ACTIVE"
          kind="seat"
          :status="item.member.status"
        />
      </span>
    </template>
    <template #cell-percent="{ item }"
      ><bdi v-if="item.percent" class="u-ltr">{{ item.percent }}%</bdi></template
    >
    <template #cell-submittedAt="{ item }"><DateStamp :value="item.submittedAt" /></template>
    <template #cell-actions="{ item }">
      <span class="committee-work__badges">
        <BaseButton
          v-if="item.status !== ASSIGNMENT_STATUSES.NOT_STARTED"
          variant="ghost"
          size="sm"
          icon="eye"
          @click="emit('view', item)"
          >{{ t('award.secretary.evaluations.view') }}</BaseButton
        >
        <BaseButton
          v-if="approvesSubstitution(item)"
          variant="secondary"
          size="sm"
          icon="user-check"
          @click="emit('approveSubstitution', item)"
          >{{ t('award.secretary.evaluations.approveSubstitution') }}</BaseButton
        >
      </span>
    </template>
  </DataTable>
</template>
