<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { SECRET_ACTIONS, isSecretStored } from '@/utils/integrations/secretValue'
import { formatDate } from '@/utils/datetime'

const props = defineProps({
  modelValue: { type: Object, default: () => ({ action: SECRET_ACTIONS.KEEP, value: '' }) },
  meta: { type: Object, default: null },
  label: { type: String, required: true },
  name: { type: String, required: true },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { t, locale } = useI18n()

const controlId = useId()
const labelId = `${controlId}-label`
const errorId = `${controlId}-error`
const hintId = `${controlId}-hint`

const stored = computed(() => isSecretStored(props.meta))
const action = computed(() => props.modelValue?.action ?? SECRET_ACTIONS.KEEP)
const entering = computed(() => !stored.value || action.value === SECRET_ACTIONS.REPLACE)
const clearing = computed(() => stored.value && action.value === SECRET_ACTIONS.CLEAR)

const updatedLine = computed(() => {
  if (!props.meta?.updatedAt) return ''
  const at = formatDate(props.meta.updatedAt, locale.value, { dateStyle: 'medium', timeStyle: 'short' })
  const by = props.meta.updatedBy?.name
  return by ? t('integrations.secret.updatedBy', { at, by }) : t('integrations.secret.updatedAt', { at })
})

const describedBy = computed(() => [props.error && errorId, props.hint && hintId].filter(Boolean).join(' ') || undefined)

const classes = computed(() => ['field', 'secret-input', props.error && 'field--error', props.disabled && 'field--disabled'])

function emitAction(nextAction, value = '') {
  emit('update:modelValue', { action: nextAction, value })
}

function onInput(event) {
  emitAction(SECRET_ACTIONS.REPLACE, event.target.value)
}
</script>

<template>
  <div :class="classes">
    <label v-if="entering" class="secret-input__label" :for="controlId">{{ label }}</label>
    <span v-else :id="labelId" class="secret-input__label">{{ label }}</span>

    <div v-if="entering" class="secret-input__entry">
      <input
        :id="controlId"
        class="field__control secret-input__control"
        type="password"
        :name="name"
        :value="modelValue?.value ?? ''"
        :disabled="disabled"
        autocomplete="off"
        autocapitalize="off"
        spellcheck="false"
        dir="ltr"
        :aria-invalid="Boolean(error) || undefined"
        :aria-describedby="describedBy"
        @input="onInput"
      />
      <BaseButton v-if="stored" variant="ghost" size="sm" icon="fa-solid fa-rotate-left" :disabled="disabled" @click="emitAction(SECRET_ACTIONS.KEEP)">
        {{ t('integrations.secret.keep') }}
      </BaseButton>
    </div>

    <div v-else-if="clearing" class="secret-input__state secret-input__state--clearing" role="group" :aria-labelledby="labelId">
      <span class="secret-input__status" role="status">
        <i class="fa-solid fa-eraser" aria-hidden="true"></i>
        {{ t('integrations.secret.willClear') }}
      </span>
      <BaseButton variant="ghost" size="sm" icon="fa-solid fa-rotate-left" :disabled="disabled" @click="emitAction(SECRET_ACTIONS.KEEP)">
        {{ t('integrations.secret.undo') }}
      </BaseButton>
    </div>

    <div v-else class="secret-input__state" role="group" :aria-labelledby="labelId" :aria-describedby="describedBy">
      <span class="secret-input__status">
        <i class="fa-solid fa-key" aria-hidden="true"></i>
        {{ t('integrations.secret.stored') }}
      </span>
      <span v-if="meta.last4" class="secret-input__last4" dir="ltr">{{ t('integrations.secret.endingIn', { last4: meta.last4 }) }}</span>
      <span v-if="updatedLine" class="secret-input__updated">{{ updatedLine }}</span>
      <span class="secret-input__actions">
        <BaseButton variant="secondary" size="sm" icon="fa-solid fa-pen" :disabled="disabled" @click="emitAction(SECRET_ACTIONS.REPLACE)">
          {{ t('integrations.secret.replace') }}
        </BaseButton>
        <BaseButton variant="ghost" size="sm" icon="fa-solid fa-trash-can" :disabled="disabled" @click="emitAction(SECRET_ACTIONS.CLEAR)">
          {{ t('integrations.secret.clear') }}
        </BaseButton>
      </span>
    </div>

    <p v-if="hint && !error" :id="hintId" class="field__hint">{{ hint }}</p>
    <p v-if="error" :id="errorId" class="field__error" role="alert">{{ error }}</p>
  </div>
</template>
