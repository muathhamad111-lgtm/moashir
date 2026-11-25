<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DocumentPreviewDialog from '@/components/files/DocumentPreviewDialog.vue'
import { useEntityDocumentList } from '@/composables/useEntityDocumentList'
import { formatRiyadhDate } from '@/utils/datetime'
import { formatFileSize } from '@/utils/payments/amountFormat'

const props = defineProps({
  documents: { type: Object, default: null },
  keys: { type: Array, required: true },
  requestLink: { type: Function, required: true },
})

const { t, locale } = useI18n()
const list = useEntityDocumentList(
  () => props.documents,
  props.keys,
  (key) => props.requestLink(key),
)

function factsOf(file) {
  const size = t('finance.common.sizeKb', { size: formatFileSize(file.sizeBytes, locale.value) })
  return `${size} · ${formatRiyadhDate(file.uploadedAt, locale.value)}`
}
</script>

<template>
  <div class="entity-documents" data-testid="entity-documents">
    <ul class="entity-documents__list">
      <li
        v-for="row in list.rows.value"
        :key="row.key"
        class="entity-documents__item"
        :data-document="row.key"
      >
        <span class="entity-documents__icon" aria-hidden="true"><AppIcon name="doc" /></span>
        <div class="entity-documents__meta">
          <span class="entity-documents__label">{{ row.label }}</span>
          <template v-if="row.file">
            <bdi class="entity-documents__name" dir="ltr">{{ row.file.name }}</bdi>
            <span class="entity-documents__facts">{{ factsOf(row.file) }}</span>
          </template>
          <span v-else class="entity-documents__missing">{{
            t('entities.documents.missing')
          }}</span>
          <slot name="status" :row="row" />
        </div>
        <div class="entity-documents__actions">
          <template v-if="row.file">
            <BaseButton variant="secondary" size="sm" icon="eye" @click="list.preview(row)">
              {{ t('entities.documents.preview') }}
            </BaseButton>
            <BaseButton
              variant="ghost"
              size="sm"
              icon="download"
              :loading="Boolean(list.downloads.busy[row.key])"
              @click="list.download(row)"
            >
              {{ t('entities.documents.download') }}
            </BaseButton>
          </template>
          <slot name="actions" :row="row" />
        </div>
      </li>
    </ul>
    <DocumentPreviewDialog
      v-model="list.previewOpen.value"
      :file="list.previewing.value?.file ?? null"
      :label="list.previewing.value?.label ?? ''"
      :request-link="list.previewLink"
    />
  </div>
</template>
