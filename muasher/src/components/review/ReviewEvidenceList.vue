<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import ReviewEvidenceCards from './ReviewEvidenceCards.vue'
import { formatNumber } from '@/utils/formatNumber'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  files: { type: Array, required: true },
  downloading: { type: Object, required: true },
  slots: { type: Array, default: () => [] },
})

const emit = defineEmits(['download'])
const { t, n, locale } = useI18n()

const groups = computed(() => {
  if (!props.slots.length) return null
  const keys = new Set(props.slots.map((slot) => slot.key))
  const bySlot = props.slots
    .map((slot) => ({
      key: slot.key,
      name: localizedText(slot.name, locale.value),
      required: slot.required === true,
      files: props.files.filter((file) => file.attachmentKey === slot.key),
    }))
    .filter((group) => group.files.length || group.required)
  const others = props.files.filter((file) => !keys.has(file.attachmentKey))
  return others.length
    ? [...bySlot, { key: '', name: t('review.evidenceOther'), required: false, files: others }]
    : bySlot
})
</script>

<!-- Evidence as small download cards: the file name, its type and size, one clear download button. -->
<template>
  <div class="review-evidence">
    <p class="review-evidence__title">
      {{ t('review.evidenceTitle', { count: formatNumber(n, files.length) }) }}
    </p>
    <template v-if="groups">
      <section v-for="group in groups" :key="group.key" class="review-evidence__group">
        <p class="review-evidence__slot">
          <span>{{ group.name }}</span>
          <Badge v-if="group.required" variant="info">{{ t('review.evidenceRequired') }}</Badge>
        </p>
        <ReviewEvidenceCards
          v-if="group.files.length"
          :files="group.files"
          :downloading="downloading"
          @download="emit('download', $event)"
        />
        <p v-else class="review-evidence__empty">{{ t('review.evidenceMissing') }}</p>
      </section>
    </template>
    <ReviewEvidenceCards
      v-else-if="files.length"
      :files="files"
      :downloading="downloading"
      @download="emit('download', $event)"
    />
    <p v-else class="review-evidence__empty">{{ t('review.noEvidence') }}</p>
  </div>
</template>
