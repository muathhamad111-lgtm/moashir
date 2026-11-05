<script setup>
import { computed } from 'vue'
import { useFieldValue } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ProviderActionFeedback from '@/components/integrations/ProviderActionFeedback.vue'
import { PROVIDER_ACTION_STATES } from '@/composables/useProviderAction'
import { INTEGRATION_LIMITS } from '@/constants/integrations'

const props = defineProps({
  prefix: { type: String, required: true },
  models: { type: Object, required: true },
  loadedModels: { type: Array, required: true },
  keyStored: { type: Boolean, default: false },
})

const { t } = useI18n()
const currentModel = useFieldValue(computed(() => `${props.prefix}.model`))
const loaded = computed(() => props.models.state.value === PROVIDER_ACTION_STATES.SUCCESS)
const modelOptions = computed(() => {
  const options = props.loadedModels.map((model) => ({
    value: model.id,
    label: model.displayName || model.id,
  }))
  const current = currentModel.value
  return current && !options.some((option) => option.value === current)
    ? [{ value: current, label: current }, ...options]
    : options
})
const tokens = INTEGRATION_LIMITS.AI_MAX_OUTPUT_TOKENS
const timeout = INTEGRATION_LIMITS.AI_TIMEOUT
</script>

<template>
  <div class="ai-models">
    <div class="ai-models__select">
      <FieldSelect
        :name="`${prefix}.model`"
        :label="t('integrations.ai.fields.model')"
        :options="modelOptions"
        :hint="t('integrations.ai.fields.modelHint')"
      />
      <BaseButton
        variant="secondary"
        size="sm"
        icon="refresh"
        :loading="models.state.value === PROVIDER_ACTION_STATES.PENDING"
        :disabled="!keyStored"
        @click="models.execute()"
      >
        {{ t('integrations.ai.loadModels') }}
      </BaseButton>
    </div>
    <p v-if="!keyStored" class="field__hint">{{ t('integrations.feedback.saveKeysFirst') }}</p>
    <p v-else-if="loaded && !loadedModels.length" class="ai-models__empty" role="status">
      {{ t('integrations.ai.noModels') }}
    </p>
    <p v-else-if="loaded" class="field__hint" role="status">
      {{ t('integrations.ai.modelsLoaded', { count: loadedModels.length }) }}
    </p>
    <ProviderActionFeedback
      v-if="models.state.value === PROVIDER_ACTION_STATES.FAILED"
      :failure="models.failure.value"
    />
    <div class="integration-form__grid">
      <FieldInput
        :name="`${prefix}.maxOutputTokens`"
        type="number"
        inputmode="numeric"
        :label="t('integrations.ai.fields.maxOutputTokens')"
        :hint="t('integrations.validation.integerBetween', { min: tokens.MIN, max: tokens.MAX })"
      />
      <FieldInput
        :name="`${prefix}.timeoutSeconds`"
        type="number"
        inputmode="numeric"
        :label="t('integrations.ai.fields.timeout')"
        :hint="t('integrations.validation.integerBetween', { min: timeout.MIN, max: timeout.MAX })"
      />
    </div>
  </div>
</template>
