<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ReviewEvidenceCards from './ReviewEvidenceCards.vue'
import { localizedText } from '@/utils/localizedText'

// What the entity uploaded for one item: a row per requirement, its name on the start side and its
// files on the end side; a required slot with nothing in it says so in red (design §B.3.1).
defineProps({
  // [{ key, name, required, late, files }] from evidenceRowsOf
  rows: { type: Array, required: true },
  downloading: { type: Object, required: true },
  newIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()
</script>

<template>
  <ul class="review-rows">
    <li v-for="row in rows" :key="row.key" class="review-rows__row">
      <p class="review-rows__name">
        <AppIcon name="attachment" />
        <span>{{ localizedText(row.name, locale) || t('review.evidenceOther') }}</span>
        <Badge v-if="row.required" variant="info">{{ t('review.evidenceRequired') }}</Badge>
        <Badge v-if="row.late" variant="warning">{{ t('review.evidenceLate') }}</Badge>
      </p>
      <ReviewEvidenceCards
        v-if="row.files.length"
        class="review-rows__files"
        :files="row.files"
        :downloading="downloading"
        :new-ids="newIds"
        @download="emit('download', $event)"
      />
      <p v-else class="review-rows__missing">
        <AppIcon name="warning-triangle" />
        {{ t('review.evidenceMissing') }}
      </p>
    </li>
  </ul>
</template>
