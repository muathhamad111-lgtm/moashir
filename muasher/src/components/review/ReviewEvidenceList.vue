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
</script>

<template>
  <div class="review-evidence">
    <p class="review-evidence__title">{{ t('review.evidenceTitle', { count: files.length }, files.length) }}</p>
    <ul v-if="files.length" class="review-evidence__list">
      <li v-for="file in files" :key="file.id" class="review-evidence__item">
        <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
        <bdi class="review-evidence__name" dir="ltr">{{ file.fileName }}</bdi>
        <span class="review-evidence__size">{{ t('accredited.evidence.sizeKb', { size: formatFileSize(file.sizeBytes, locale) }) }}</span>
        <BaseButton variant="ghost" size="sm" icon-only icon="fa-solid fa-download" :loading="Boolean(downloading[file.id])" :aria-label="t('accredited.evidence.download', { name: file.fileName })" @click="emit('download', file)" />
      </li>
    </ul>
    <p v-else class="review-evidence__empty">{{ t('review.noEvidence') }}</p>
  </div>
</template>
