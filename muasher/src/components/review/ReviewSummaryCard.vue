<script setup>
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useAssessorSummary } from '@/composables/useAssessorSummary'
import { ASSESSOR_SUMMARY_MAX } from '@/constants/insights'
import { formatRiyadhDateTime } from '@/utils/datetime'

const props = defineProps({
  assessmentId: { type: Number, required: true },
  summary: { type: Object, default: null },
  editable: { type: Boolean, default: false },
  askReason: { type: Function, required: true },
  requiresReason: { type: Boolean, default: false },
})

const emit = defineEmits(['saved'])
const { t, n, locale } = useI18n()
const { text, error, saving, length, tooLong, dirty, save } = useAssessorSummary({
  assessmentId: toRef(props, 'assessmentId'),
  summary: toRef(props, 'summary'),
  askReason: () => props.askReason(),
  requiresReason: toRef(props, 'requiresReason'),
  onSaved: (data) => emit('saved', data),
})

const counter = computed(() =>
  t('review.summary.counter', { count: n(length.value), max: n(ASSESSOR_SUMMARY_MAX) }),
)
const fieldError = computed(() =>
  tooLong.value ? t('review.summary.errors.tooLong') : error.value,
)
</script>

<template>
  <section
    class="review-summary"
    aria-labelledby="review-summary-title"
    data-testid="review-summary"
  >
    <header class="review-summary__header">
      <div>
        <h2 id="review-summary-title" class="review-summary__title">
          {{ t('review.summary.title') }}
        </h2>
        <p class="review-summary__lead">{{ t('review.summary.lead') }}</p>
      </div>
      <span v-if="summary" class="review-summary__saved" data-testid="review-summary-version">
        <AppIcon name="check" />
        {{ t('review.summary.version', { version: n(summary.version) }) }}
      </span>
    </header>
    <template v-if="editable">
      <BaseTextarea
        v-model="text"
        name="assessor-summary"
        :label="t('review.summary.label')"
        :rows="5"
        :hint="t('review.summary.help')"
        :error="fieldError"
      />
      <p class="review-summary__counter" :class="{ 'is-over': tooLong }" aria-live="polite">
        <bdi dir="ltr">{{ counter }}</bdi>
      </p>
      <div class="review-summary__actions">
        <BaseButton
          variant="secondary"
          icon="check"
          :loading="saving"
          :disabled="tooLong || !dirty"
          data-testid="review-summary-save"
          @click="save"
        >
          {{ t('review.summary.save') }}
        </BaseButton>
        <span v-if="summary?.updatedAt" class="review-summary__meta">
          {{
            t('review.summary.lastSaved', { date: formatRiyadhDateTime(summary.updatedAt, locale) })
          }}
        </span>
      </div>
    </template>
    <template v-else>
      <blockquote
        v-if="summary"
        class="review-summary__text"
        dir="auto"
        v-text="summary.body"
      ></blockquote>
      <p v-else class="review-summary__empty">{{ t('review.summary.empty') }}</p>
    </template>
  </section>
</template>
