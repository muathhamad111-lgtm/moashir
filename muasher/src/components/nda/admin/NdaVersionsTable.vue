<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import LegalVersionStatus from '@/components/legal/admin/LegalVersionStatus.vue'
import { formatRiyadhDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'
import { COLUMN_KINDS } from '@/utils/tableColumns'

const props = defineProps({
  versions: { type: Array, default: () => [] },
  status: { type: String, default: 'loading' },
  errorMessage: { type: String, default: '' },
  busy: { type: String, default: '' },
  inScope: { type: Number, default: null },
})

const emit = defineEmits(['open', 'retry'])
const { t, locale } = useI18n()

const columns = computed(() =>
  [
    'version',
    'status',
    'publishedAt',
    'publishedBy',
    'acceptances',
    'title',
    'effectiveFrom',
    'changeSummary',
    'updatedAt',
    'createdAt',
    'actions',
  ].map((key) => ({
    key,
    label: t(`nda.admin.columns.${key}`),
    ...(key === 'changeSummary' ? { kind: COLUMN_KINDS.NOTE } : {}),
  })),
)

function acceptancesOf(item) {
  if (item.status === 'draft' || item.acceptancesCount === null) return '—'
  if (item.status === 'current' && props.inScope !== null) {
    return t('nda.admin.acceptancesOf', { count: item.acceptancesCount, total: props.inScope })
  }
  return String(item.acceptancesCount)
}
</script>

<template>
  <DataTable
    table-key="nda-versions"
    :columns="columns"
    :items="versions"
    :status="status === 'success' && !versions.length ? 'empty' : status"
    :error-message="errorMessage"
    :empty-title="t('nda.admin.empty.title')"
    :empty-message="t('nda.admin.empty.message')"
    @retry="emit('retry')"
  >
    <template #cell-version="{ item }"
      ><bdi dir="ltr">{{ item.version }}</bdi></template
    >
    <template #cell-status="{ item }"><LegalVersionStatus :status="item.status" /></template>
    <template #cell-publishedAt="{ item }">
      <bdi>{{ formatRiyadhDate(item.publishedAt, locale) || '—' }}</bdi>
    </template>
    <template #cell-publishedBy="{ item }">{{ item.publishedBy || '—' }}</template>
    <template #cell-acceptances="{ item }"
      ><bdi>{{ acceptancesOf(item) }}</bdi></template
    >
    <template #cell-title="{ item }">{{ localizedText(item.title, locale) }}</template>
    <template #cell-effectiveFrom="{ item }">
      <bdi v-if="item.effectiveFrom">{{ formatRiyadhDate(item.effectiveFrom, locale) }}</bdi>
    </template>
    <template #cell-changeSummary="{ item }">{{
      localizedText(item.changeSummary, locale)
    }}</template>
    <template #cell-updatedAt="{ item }">
      <bdi>{{ formatRiyadhDate(item.updatedAt, locale) }}</bdi>
    </template>
    <template #cell-createdAt="{ item }">
      <bdi>{{ formatRiyadhDate(item.createdAt, locale) }}</bdi>
    </template>
    <template #cell-actions="{ item }">
      <BaseButton
        variant="ghost"
        size="sm"
        :icon="item.status === 'draft' ? 'pen-line' : 'eye'"
        :loading="busy === 'open'"
        @click="emit('open', item)"
        >{{
          item.status === 'draft' ? t('legalAdmin.editDraft') : t('legalAdmin.view')
        }}</BaseButton
      >
    </template>
  </DataTable>
</template>
