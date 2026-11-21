<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AuditActorCell from './AuditActorCell.vue'
import { formatRiyadhDateTime } from '@/utils/datetime'

defineProps({
  items: { type: Array, default: () => [] },
  status: { type: String, default: 'loading' },
  errorMessage: { type: String, default: '' },
  filtered: { type: Boolean, default: false },
})

const emit = defineEmits(['open', 'retry'])
const { t, te, locale } = useI18n()

const columns = computed(() =>
  ['createdAt', 'event', 'actor', 'subject', 'ipAddress', 'reason', 'actions'].map((key) => ({
    key,
    label: t(`auditLog.columns.${key}`),
  })),
)

function groupOf(event) {
  const key = `auditLog.groups.${event.split('.')[0]}`
  return te(key) ? t(key) : ''
}
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t(filtered ? 'auditLog.empty.filteredTitle' : 'auditLog.empty.title')"
    :empty-message="t(filtered ? 'auditLog.empty.filteredMessage' : 'auditLog.empty.message')"
    @retry="emit('retry')"
  >
    <template #cell-createdAt="{ item }">
      <bdi>{{ formatRiyadhDateTime(item.createdAt, locale) }}</bdi>
    </template>
    <template #cell-event="{ item }">
      <span class="audit-log__event">
        <code dir="ltr">{{ item.event }}</code>
        <span v-if="groupOf(item.event)" class="audit-log__muted">{{ groupOf(item.event) }}</span>
      </span>
    </template>
    <template #cell-actor="{ item }"><AuditActorCell :actor="item.actor" /></template>
    <template #cell-subject="{ item }">
      <bdi v-if="item.subject" dir="ltr">{{ item.subject.type }} #{{ item.subject.id }}</bdi>
    </template>
    <template #cell-ipAddress="{ item }"
      ><bdi dir="ltr">{{ item.ipAddress }}</bdi></template
    >
    <template #cell-reason="{ item }">
      <span class="audit-log__reason">{{ item.reason }}</span>
    </template>
    <template #cell-actions="{ item }">
      <BaseButton variant="ghost" size="sm" icon="eye" @click="emit('open', item)">{{
        t('auditLog.details')
      }}</BaseButton>
    </template>
  </DataTable>
</template>
