<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import DocumentPreviewDialog from '@/components/files/DocumentPreviewDialog.vue'
import { PDF_MIME_TYPE } from '@/constants/entityDocuments'

const props = defineProps({
  file: { type: Object, required: true },
  label: { type: String, default: '' },
})

const { t } = useI18n()
const open = ref(false)
const isPdf = computed(() => props.file.mimeType === PDF_MIME_TYPE)
const name = computed(() => props.file.name || t('entities.profileChanges.review.openFile'))

function requestLink() {
  return Promise.resolve({ data: { url: props.file.url, fileName: props.file.name } })
}
</script>

<template>
  <span v-if="isPdf" class="change-value__file">
    <BaseButton variant="ghost" size="sm" icon="eye" @click="open = true">
      {{ t('entities.documents.preview') }}
    </BaseButton>
    <bdi dir="ltr">{{ name }}</bdi>
    <DocumentPreviewDialog
      v-model="open"
      :file="file"
      :label="label || name"
      :request-link="requestLink"
    />
  </span>
  <a v-else :href="file.url" target="_blank" rel="noopener" class="change-value__file">
    {{ name }}
  </a>
</template>
