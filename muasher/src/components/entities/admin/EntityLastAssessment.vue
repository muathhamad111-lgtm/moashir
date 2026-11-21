<script setup>
import { useI18n } from 'vue-i18n'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { percentText } from '@/utils/percentText'

defineProps({
  assessment: { type: Object, default: null },
})

const { t, n } = useI18n()
const { date } = useEntityDisplay()
</script>

<template>
  <span v-if="assessment" class="entity-last-assessment">
    <span class="entity-last-assessment__percent u-ltr">
      {{ assessment.percent === null ? '—' : percentText(n, t, assessment.percent) }}
    </span>
    <span class="entity-last-assessment__date">{{ date(assessment.completedAt) }}</span>
  </span>
  <span v-else class="entity-last-assessment entity-last-assessment--none">
    {{ t('entities.lastAssessment.none') }}
  </span>
</template>
