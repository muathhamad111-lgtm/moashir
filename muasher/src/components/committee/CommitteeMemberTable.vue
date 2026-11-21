<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import AwardStateBadge from './AwardStateBadge.vue'

const props = defineProps({
  members: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
  actionsFor: { type: Function, default: null },
})

const emit = defineEmits(['action', 'retry'])
const { t } = useI18n()
const names = computed(() => new Map(props.members.map((member) => [member.id, member.user.name])))

const columns = computed(() => [
  { key: 'name', label: t('award.roster.columns.name') },
  { key: 'status', label: t('award.roster.columns.status') },
  { key: 'progress', label: t('award.roster.columns.progress') },
  { key: 'appointedAt', label: t('award.roster.columns.appointedAt') },
  ...(props.actionsFor ? [{ key: 'actions', label: t('components.actions.label') }] : []),
])
</script>

<template>
  <DataTable
    :columns="columns"
    :items="members"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('award.roster.emptyTitle')"
    :empty-message="t('award.roster.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-name="{ item }">
      <span class="committee-member__name">{{ item.user.name }}</span>
      <span v-if="item.replacesId" class="committee-member__note">{{
        t('award.roster.replaces', { name: names.get(item.replacesId) ?? '' })
      }}</span>
      <span v-if="item.replacedById" class="committee-member__note">{{
        t('award.roster.replacedBy', { name: names.get(item.replacedById) ?? '' })
      }}</span>
    </template>
    <template #cell-status="{ item }">
      <AwardStateBadge kind="seat" :status="item.status" />
      <span v-if="item.disabledReason" class="committee-member__note">{{
        t('award.roster.disabledReason', { reason: item.disabledReason })
      }}</span>
    </template>
    <template #cell-progress="{ item }">
      <span class="committee-member__progress">{{
        t('award.roster.progress', {
          completed: item.progress.completed,
          total: item.progress.notStarted + item.progress.inProgress + item.progress.completed,
        })
      }}</span>
    </template>
    <template #cell-appointedAt="{ item }"
      ><DateStamp :value="item.appointedAt" :with-time="false"
    /></template>
    <template #cell-actions="{ item }">
      <AppMenu
        v-if="actionsFor(item).length"
        :actions="actionsFor(item)"
        :label="t('components.actions.menuFor', { name: item.user.name })"
        @select="emit('action', $event, item)"
      />
    </template>
  </DataTable>
</template>
