<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'

const props = defineProps({
  assessment: { type: Object, required: true },
  criteria: { type: Object, required: true },
  scale: { type: Object, required: true },
  progress: { type: Object, required: true },
})

const { t } = useI18n()

const progressLabel = computed(() =>
  t('assessment.self.progress.count', {
    scored: props.progress.scored,
    total: props.progress.required,
  }),
)
</script>

<template>
  <section class="self-summary" :aria-label="t('assessment.self.summaryLabel')">
    <div class="self-summary__head">
      <div class="self-summary__identity">
        <p class="self-summary__number">
          {{ t('assessment.self.requestNumber') }}
          <span class="u-ltr">{{ assessment.number }}</span>
        </p>
        <p class="self-summary__meta">
          {{ t('assessment.self.versionMeta', { number: criteria.versionNumber }) }}
          <span aria-hidden="true">·</span>
          {{ t(`assessment.self.scaleNames.${scale.kind}`) }}
        </p>
      </div>
      <div class="self-summary__badges">
        <StatusBadge :status="assessment.status" />
        <FrozenBadge v-if="assessment.isFrozen" />
      </div>
    </div>
    <div class="self-summary__progress">
      <div class="self-summary__progress-labels">
        <span>{{ t('assessment.self.progress.label') }}</span>
        <span class="self-summary__progress-value">{{ progressLabel }}</span>
      </div>
      <progress
        class="self-summary__bar"
        :max="Math.max(progress.required, 1)"
        :value="progress.scored"
        :aria-label="t('assessment.self.progress.label')"
        :aria-valuetext="progressLabel"
      ></progress>
      <p class="self-summary__note">{{ t('assessment.self.progress.savedOnly') }}</p>
    </div>
  </section>
</template>
