<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import BaseButton from '@/components/ui/BaseButton.vue'
import ProviderActionFeedback from '@/components/integrations/ProviderActionFeedback.vue'
import { PROVIDER_ACTION_STATES } from '@/composables/useProviderAction'
import { AI_FINISHES } from '@/constants/integrations'

const props = defineProps({
  answer: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
})

const { t, n } = useI18n()
const result = computed(() => props.answer.result.value)
const finishLabel = computed(() =>
  t(
    `integrations.ai.finish.${AI_FINISHES.includes(result.value?.finish) ? result.value.finish : 'other'}`,
  ),
)
const usage = computed(() =>
  result.value
    ? t('integrations.ai.answerUsage', {
        ms: formatNumber(n, result.value.latencyMs ?? 0, 'integer'),
        input: formatNumber(n, result.value.inputTokens ?? 0, 'integer'),
        output: formatNumber(n, result.value.outputTokens ?? 0, 'integer'),
      })
    : '',
)
</script>

<template>
  <div class="ai-answer">
    <div class="ai-answer__actions">
      <BaseButton
        variant="secondary"
        icon="fa-solid fa-comment-dots"
        :loading="answer.state.value === PROVIDER_ACTION_STATES.PENDING"
        :disabled="disabled"
        @click="answer.execute()"
      >
        {{ t('integrations.ai.testAnswer') }}
      </BaseButton>
      <span class="field__hint">{{ t('integrations.ai.testAnswerHint') }}</span>
    </div>
    <figure
      v-if="answer.state.value === PROVIDER_ACTION_STATES.SUCCESS && result"
      class="ai-answer__result"
      role="status"
    >
      <blockquote class="ai-answer__text" dir="auto">{{ result.text }}</blockquote>
      <figcaption class="ai-answer__usage">
        {{
          t('integrations.ai.answerSummary', { model: result.model, finish: finishLabel, usage })
        }}
        <span v-if="result.providerRequestId" class="ai-answer__reference">
          {{ t('integrations.ai.requestReference') }}
          <code dir="ltr">{{ result.providerRequestId }}</code>
        </span>
      </figcaption>
    </figure>
    <ProviderActionFeedback
      v-if="answer.state.value === PROVIDER_ACTION_STATES.FAILED"
      :failure="answer.failure.value"
    />
  </div>
</template>
