<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  saving: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
  readOnly: { type: Boolean, default: false },
  submitBlocked: { type: Boolean, default: false },
  dirty: { type: Boolean, default: false },
  isFirst: { type: Boolean, default: true },
  isLast: { type: Boolean, default: true },
})

const emit = defineEmits(['previous', 'next', 'save', 'submit'])
const { t } = useI18n()

const busy = computed(() => props.saving || props.submitting)
const hint = computed(() => {
  if (props.readOnly) return t('assessment.self.actions.readOnlyHint')
  if (props.submitBlocked) return t('assessment.self.actions.submitBlockedHint')
  if (props.dirty) return t('assessment.self.actions.unsavedHint')
  return ''
})
</script>

<template>
  <footer class="self-actions">
    <p v-if="hint" class="self-actions__hint" role="status">{{ hint }}</p>
    <p v-if="!readOnly && isLast" class="submit-hint">
      <AppIcon name="lock" />
      {{ t('assessment.self.actions.submitHint') }}
    </p>
    <div class="self-actions__buttons">
      <BaseButton
        v-if="!isFirst"
        variant="ghost"
        icon="arrow-left"
        data-testid="self-previous"
        :disabled="busy"
        @click="emit('previous')"
      >
        {{ t('assessment.self.actions.previous') }}
      </BaseButton>
      <BaseButton
        variant="secondary"
        icon="save"
        data-testid="self-save"
        :loading="saving"
        :disabled="readOnly || busy"
        @click="emit('save')"
      >
        {{ t('assessment.self.actions.saveDraft') }}
      </BaseButton>
      <BaseButton
        v-if="!isLast"
        variant="primary"
        icon-end="arrow-right"
        data-testid="self-next"
        :disabled="busy"
        @click="emit('next')"
      >
        {{ t('assessment.self.actions.next') }}
      </BaseButton>
      <BaseButton
        v-else
        variant="primary"
        icon="send"
        data-testid="self-submit"
        :loading="submitting"
        :disabled="readOnly || submitBlocked || busy"
        @click="emit('submit')"
      >
        {{ t('assessment.self.actions.submit') }}
      </BaseButton>
    </div>
  </footer>
</template>
