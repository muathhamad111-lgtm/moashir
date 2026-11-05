<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import ConnectionTestResult from './ConnectionTestResult.vue'
import ProviderActionFeedback from './ProviderActionFeedback.vue'
import { PROVIDER_ACTION_STATES, useProviderAction } from '@/composables/useProviderAction'

const props = defineProps({
  run: { type: Function, required: true },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  disabledHint: { type: String, default: '' },
})

const emit = defineEmits(['settled'])
const { t } = useI18n()
const { state, result, failure, execute } = useProviderAction(() => props.run(), {
  onSettled: () => emit('settled', result.value ?? failure.value?.result ?? null),
})
const shownResult = computed(
  () =>
    (state.value === PROVIDER_ACTION_STATES.SUCCESS ? result.value : failure.value?.result) ?? null,
)
</script>

<template>
  <div class="connection-test" :aria-busy="state === PROVIDER_ACTION_STATES.PENDING || undefined">
    <div class="connection-test__actions">
      <BaseButton
        variant="secondary"
        icon="fa-solid fa-plug-circle-check"
        :loading="state === PROVIDER_ACTION_STATES.PENDING"
        :disabled="disabled"
        @click="execute"
      >
        {{ label || t('integrations.actions.testConnection') }}
      </BaseButton>
      <span v-if="disabled && disabledHint" class="connection-test__hint">{{ disabledHint }}</span>
    </div>
    <ProviderActionFeedback v-if="state === PROVIDER_ACTION_STATES.FAILED" :failure="failure" />
    <ConnectionTestResult v-if="shownResult" :result="shownResult" />
  </div>
</template>
