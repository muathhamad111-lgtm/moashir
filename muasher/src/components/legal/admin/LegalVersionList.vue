<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import LegalVersionStatus from './LegalVersionStatus.vue'
import { formatRiyadhDate, formatRiyadhDateTime } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'

defineProps({
  versions: { type: Array, default: () => [] },
  status: { type: String, default: 'loading' },
  errorMessage: { type: String, default: '' },
  busy: { type: String, default: '' },
})

const emit = defineEmits(['open', 'retry'])
const { t, locale } = useI18n()

const columns = computed(() =>
  ['version', 'status', 'title', 'effectiveFrom', 'updatedAt', 'actions'].map((key) => ({
    key,
    label: t(`legalAdmin.columns.${key}`),
  })),
)
</script>

<template>
  <DataTable
    :columns="columns"
    :items="versions"
    :status="status === 'success' && !versions.length ? 'empty' : status"
    :error-message="errorMessage"
    :empty-title="t('legalAdmin.empty.title')"
    :empty-message="t('legalAdmin.empty.message')"
    @retry="emit('retry')"
  >
    <template #cell-version="{ item }"
      ><bdi dir="ltr">{{ item.version }}</bdi></template
    >
    <template #cell-status="{ item }"><LegalVersionStatus :status="item.status" /></template>
    <template #cell-title="{ item }">{{ localizedText(item.title, locale) }}</template>
    <template #cell-effectiveFrom="{ item }">
      <bdi>{{ formatRiyadhDate(item.effectiveFrom, locale) || t('legalAdmin.notPublished') }}</bdi>
    </template>
    <template #cell-updatedAt="{ item }">
      <bdi>{{ formatRiyadhDateTime(item.updatedAt, locale) }}</bdi>
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
