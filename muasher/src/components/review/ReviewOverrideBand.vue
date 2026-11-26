<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { overrideSummaryParts } from '@/utils/assessments/overrideSummary'
import { twoDecimals } from '@/utils/percentText'

// The band of the override mode (design §B.3.5): every change is recorded; the line shows how many
// items changed and what the server computes for the total and level. «مراجعة التجاوز» lists only
// the changes for confirmation.
const props = defineProps({
  override: { type: Object, required: true },
})

const emit = defineEmits(['review'])
const { t } = useI18n()

const count = computed(() => props.override.changedUnits.value.length)
const summary = computed(() =>
  overrideSummaryParts(t, {
    count: count.value,
    preview: props.override.preview.value,
    formatPercent: (value) => t('review.override.mode.percent', { value: twoDecimals(value) }),
  }).join(' · '),
)
const canReview = computed(() => count.value > 0 && props.override.isValid.value)
</script>

<template>
  <section class="override-band" :aria-label="t('review.override.mode.title')">
    <AppIcon name="pen" class="override-band__icon" />
    <div class="override-band__text">
      <p class="override-band__title">{{ t('review.override.mode.title') }}</p>
      <p class="override-band__summary" aria-live="polite">
        <span>{{ summary }}</span>
        <span v-if="override.previewing.value" class="override-band__busy">{{
          t('review.override.mode.computing')
        }}</span>
        <span v-else-if="count && !override.isValid.value">{{
          t('review.override.mode.invalid')
        }}</span>
      </p>
    </div>
    <div class="override-band__actions">
      <BaseButton variant="secondary" size="sm" @click="override.leave()">
        {{ t('review.override.mode.cancel') }}
      </BaseButton>
      <BaseButton
        variant="danger"
        size="sm"
        icon="check"
        data-testid="override-review"
        :disabled="!canReview"
        @click="emit('review')"
      >
        {{ t('review.override.mode.review') }}
      </BaseButton>
    </div>
  </section>
</template>
