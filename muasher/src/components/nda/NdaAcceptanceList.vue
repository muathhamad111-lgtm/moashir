<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import { formatRiyadhDateTime } from '@/utils/datetime'

const props = defineProps({
  acceptances: { type: Array, default: () => [] },
  status: { type: String, default: 'loading' },
  errorMessage: { type: String, default: '' },
  showRoles: { type: Boolean, default: false },
})

const emit = defineEmits(['retry', 'view'])
const { t, locale } = useI18n()

const columns = computed(() =>
  ['version', 'acceptedAt', 'ip', ...(props.showRoles ? ['roles'] : ['actions'])].map((key) => ({
    key,
    label: t(`nda.history.columns.${key}`),
  })),
)

function roleLabel(role) {
  return role === 'super_admin' ? t('nda.roles.superAdmin') : t(`users.roleLabels.${role}`)
}
</script>

<template>
  <DataTable
    table-key="nda-history"
    :columns="columns"
    :items="acceptances"
    :status="status === 'success' && !acceptances.length ? 'empty' : status"
    :error-message="errorMessage"
    :empty-title="t('nda.history.empty')"
    :empty-message="''"
    @retry="emit('retry')"
  >
    <template #cell-version="{ item }"
      ><bdi dir="ltr">{{ item.version }}</bdi></template
    >
    <template #cell-acceptedAt="{ item }">
      <bdi>{{ formatRiyadhDateTime(item.acceptedAt, locale) }}</bdi>
    </template>
    <template #cell-ip="{ item }"
      ><bdi dir="ltr">{{ item.ipMasked || '—' }}</bdi></template
    >
    <template #cell-roles="{ item }">{{
      item.acceptedRoles.map(roleLabel).join('، ') || '—'
    }}</template>
    <template #cell-actions="{ item }">
      <button type="button" class="btn btn--ghost btn--sm" @click="emit('view', item)">
        {{ t('legalAdmin.view') }}
      </button>
    </template>
  </DataTable>
</template>
