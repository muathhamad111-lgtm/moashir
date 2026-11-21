<script setup>
import { computed, toRef } from 'vue'
import { useField } from 'vee-validate'
import FormField from './FormField.vue'
import { digitsOnly } from '@/utils/numerals'

const props = defineProps({
  name: { type: String, required: true },
  label: { type: String, required: true },
  hint: { type: String, default: '' },
  maxlength: { type: Number, required: true },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const { value, errorMessage, handleBlur } = useField(toRef(props, 'name'))
const text = computed(() => String(value.value ?? ''))

function onInput(event) {
  const digits = digitsOnly(event.target.value).slice(0, props.maxlength)
  event.target.value = digits
  value.value = digits
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
    <input
      :id="id"
      class="field__control field__control--digits"
      type="text"
      :name="name"
      :value="text"
      placeholder=" "
      inputmode="numeric"
      autocomplete="off"
      dir="ltr"
      :maxlength="maxlength"
      :disabled="disabled"
      :required="required"
      :aria-invalid="invalid || undefined"
      :aria-describedby="describedBy"
      @input="onInput"
      @blur="handleBlur"
    />
  </FormField>
</template>
