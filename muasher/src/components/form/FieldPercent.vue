<script setup>
import { computed, toRef } from 'vue'
import { useField } from 'vee-validate'
import FormField from './FormField.vue'
import { limitDecimalInput } from '@/utils/numerals'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  hint: { type: String, default: '' },
  integerDigits: { type: Number, required: true },
  decimals: { type: Number, required: true },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const { value, errorMessage, handleBlur } = useField(toRef(props, 'name'))
const text = computed(() => String(value.value ?? ''))

function onInput(event) {
  const limited = limitDecimalInput(event.target.value, props)
  event.target.value = limited
  value.value = limited
}
</script>

<template>
  <FormField
    v-slot="{ id, describedBy, invalid }"
    :label="label"
    :name="name"
    :error="errorMessage"
    :hint="hint"
    :required="required"
    :disabled="disabled"
    :filled="text.length > 0"
  >
    <div class="field__affixed" dir="ltr">
      <input
        :id="id"
        class="field__control field__control--digits"
        type="text"
        :name="name"
        :value="text"
        placeholder=" "
        inputmode="decimal"
        autocomplete="off"
        :disabled="disabled"
        :required="required"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedBy"
        @input="onInput"
        @blur="handleBlur"
      />
      <span class="field__suffix" aria-hidden="true">%</span>
    </div>
  </FormField>
</template>
