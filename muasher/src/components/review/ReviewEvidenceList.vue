<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { formatFileSize } from '@/utils/payments/amountFormat'
import { formatNumber } from '@/utils/formatNumber'

defineProps({
  files: { type: Array, required: true },
  downloading: { type: Object, required: true },
})

const emit = defineEmits(['download'])
const { t, n, locale } = useI18n()

function extensionOf(fileName) {
  const dot = fileName.lastIndexOf('.')
  return dot > 0 ? fileName.slice(dot + 1).toUpperCase() : ''
}
</script>

<!-- Evidence as small download cards: the file name, its type and size, one clear download button. -->
<template>
  <div class="review-evidence">
    <p class="review-evidence__title">
      {{ t('review.evidenceTitle', { count: formatNumber(n, files.length) }) }}
    </p>
    <ul v-if="files.length" class="review-evidence__list">
      <li v-for="file in files" :key="file.id" class="review-evidence__card" :title="file.fileName">
        <span class="review-evidence__type" aria-hidden="true">{{
          extensionOf(file.fileName)
        }}</span>
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
    <p v-else class="review-evidence__empty">{{ t('review.noEvidence') }}</p>
  </div>
</template>
