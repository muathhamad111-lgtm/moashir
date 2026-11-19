<script setup>
import { computed, toRef } from 'vue'
import FormField from './FormField.vue'
import PasswordRevealButton from './PasswordRevealButton.vue'
import { usePasswordReveal } from '@/composables/usePasswordReveal'
import { westernizeInputDigits } from '@/utils/numerals'

const NUMBER_INPUT_MODES = ['numeric', 'decimal', 'tel']
const PASSWORD_TYPE = 'password'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, required: true },
  name: { type: String, required: true },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  inputmode: { type: String, default: undefined },
  autocomplete: { type: String, default: undefined },
  inputDir: { type: String, default: undefined },
})

const emit = defineEmits(['update:modelValue', 'blur'])
const { revealed, toggle } = usePasswordReveal(toRef(props, 'modelValue'))

const isPassword = computed(() => props.type === PASSWORD_TYPE)
const controlType = computed(() => (isPassword.value && revealed.value ? 'text' : props.type))

function onInput(event) {
  const isNumberField = NUMBER_INPUT_MODES.includes(props.inputmode) || props.type === 'tel'
  emit(
    'update:modelValue',
    isNumberField ? westernizeInputDigits(event.target) : event.target.value,
  )
}
</script>

<template>
  <FormField
    v-slot="{ id, describedBy, invalid }"
    :class="{ 'field--revealable': isPassword }"
    :label="label"
    :name="name"
    :error="error"
    :hint="hint"
    :required="required"
    :disabled="disabled"
    :filled="Boolean(String(modelValue).length)"
  >
    <div class="field__frame" :class="{ 'field__frame--reveal': isPassword }">
      <input
        :id="id"
        class="field__control"
        :type="controlType"
        :name="name"
        :value="modelValue"
        placeholder=" "
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :inputmode="inputmode"
        :autocomplete="autocomplete"
        :dir="inputDir"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedBy"
        @input="onInput"
        @blur="emit('blur', $event)"
      />
      <PasswordRevealButton
        v-if="isPassword"
        :revealed="revealed"
        :controls="id"
        :disabled="disabled"
        @toggle="toggle"
      />
    </div>
  </FormField>
</template>
