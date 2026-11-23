<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import { formatRiyadhDateTime } from '@/utils/datetime'

const props = defineProps({
  rows: { type: Array, default: () => [] },
  status: { type: String, default: 'loading' },
  errorMessage: { type: String, default: '' },
  currentVersion: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()

const columns = computed(() =>
  ['user', 'roles', 'version', 'status', 'acceptedAt', 'ip'].map((key) => ({
    key,
    label: t(`nda.register.columns.${key}`),
  })),
)

const items = computed(() => props.rows.map((row) => ({ ...row, id: row.user.id })))

function roleLabel(role) {
  return role === 'super_admin' ? t('nda.roles.superAdmin') : t(`users.roleLabels.${role}`)
}

function statusLabel(row) {
  return row.status === 'accepted'
    ? t('nda.statuses.accepted')
    : t('nda.register.pendingFor', { version: props.currentVersion })
}
</script>

<template>
  <DataTable
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('nda.register.empty.title')"
    :empty-message="t('nda.register.empty.message')"
    @retry="emit('retry')"
  >
    <template #cell-user="{ item }">{{ item.user.name }}</template>
    <template #cell-roles="{ item }">{{ item.user.roles.map(roleLabel).join('، ') }}</template>
    <template #cell-version="{ item }">
      <bdi dir="ltr">{{
        item.status === 'accepted' ? currentVersion : item.lastAcceptedVersion || '—'
      }}</bdi>
    </template>
    <template #cell-status="{ item }">
      <Badge :variant="item.status === 'accepted' ? 'success' : 'warning'">{{
        statusLabel(item)
      }}</Badge>
    </template>
    <template #cell-acceptedAt="{ item }">
      <bdi>{{ formatRiyadhDateTime(item.acceptedAt, locale) || '—' }}</bdi>
    </template>
    <template #cell-ip="{ item }"
      ><bdi dir="ltr">{{ item.ipMasked || '—' }}</bdi></template
    >
  </DataTable>
</template>
