<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { formatFileSize } from '@/utils/payments/amountFormat'

defineProps({
  files: { type: Array, required: true },
  downloading: { type: Object, required: true },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()

function extensionOf(fileName) {
  const dot = fileName.lastIndexOf('.')
  return dot > 0 ? fileName.slice(dot + 1).toUpperCase() : ''
}
</script>

<template>
  <ul class="review-evidence__list">
    <li v-for="file in files" :key="file.id" class="review-evidence__card" :title="file.fileName">
      <span class="review-evidence__type" aria-hidden="true">{{ extensionOf(file.fileName) }}</span>
      <span class="review-evidence__meta">
        <bdi class="review-evidence__name" dir="ltr">{{ file.fileName }}</bdi>
        <span class="review-evidence__size">{{
          t('accredited.evidence.sizeKb', { size: formatFileSize(file.sizeBytes, locale) })
        }}</span>
      </span>
      <BaseButton
        variant="secondary"
        size="sm"
        icon="download"
        :loading="Boolean(downloading[file.id])"
        :aria-label="t('accredited.evidence.download', { name: file.fileName })"
        @click="emit('download', file)"
        >{{ t('review.evidenceDownload') }}</BaseButton
      >
    </li>
  </ul>
</template>
