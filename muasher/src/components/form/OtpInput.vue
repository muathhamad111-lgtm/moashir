<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { digitsOnly } from '@/utils/numerals'

const props = defineProps({
  modelValue: { type: String, default: '' },
  length: { type: Number, default: 6 },
  label: { type: String, required: true },
  disabled: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
  describedBy: { type: String, default: undefined },
  // The boxes appear one after another when the group first mounts (a code step opening).
  entrance: { type: Boolean, default: false },
  // The code was accepted: the boxes turn to the success colour.
  verified: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'complete'])

const boxes = ref([])
const shaking = ref(false)

const digits = computed(() => {
  const chars = (props.modelValue ?? '').slice(0, props.length).split('')
  return Array.from({ length: props.length }, (_, index) => chars[index] ?? '')
})

function emitValue(next) {
  const value = next.slice(0, props.length)
  emit('update:modelValue', value)
  if (value.length === props.length) emit('complete', value)
}

function setDigit(index, char) {
  const current = digits.value.slice()
  current[index] = char
  emitValue(current.join(''))
}

function focusBox(index) {
  const el = boxes.value[index]
  if (el) {
    el.focus()
    el.select?.()
  }
}

function onInput(index, event) {
  const raw = digitsOnly(event.target.value)
  if (!raw) {
    setDigit(index, '')
    return
  }
  const char = raw[raw.length - 1]
  setDigit(index, char)
  if (index < props.length - 1) nextTick(() => focusBox(index + 1))
}

function onKeydown(index, event) {
  if (event.key === 'Backspace') {
    if (digits.value[index]) {
      setDigit(index, '')
    } else if (index > 0) {
      event.preventDefault()
      setDigit(index - 1, '')
      focusBox(index - 1)
    }
    return
  }
  if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
    event.preventDefault()
    const forward = event.key === 'ArrowRight'
    const target = forward ? index + 1 : index - 1
    if (target >= 0 && target < props.length) focusBox(target)
  }
}

function onPaste(index, event) {
  event.preventDefault()
  const text = digitsOnly(event.clipboardData?.getData('text') ?? '')
  if (!text) return
  const current = digits.value.slice()
  for (let offset = 0; offset < text.length && index + offset < props.length; offset += 1) {
    current[index + offset] = text[offset]
  }
  const joined = current.join('')
  emitValue(joined)
  const lastFilled = Math.min(index + text.length, props.length) - 1
  nextTick(() => focusBox(Math.max(0, lastFilled)))
}

function boxClasses(index) {
  return ['otp-input__box', digits.value[index] && 'is-filled', props.invalid && 'is-invalid']
}

watch(
  () => props.modelValue,
  (value) => {
    if (value === '') nextTick(() => focusBox(0))
  },
)

// A wrong code shakes the row once each time the error appears.
watch(
  () => props.invalid,
  (invalid, wasInvalid) => {
    if (!invalid || wasInvalid) return
    shaking.value = false
    requestAnimationFrame(() => (shaking.value = true))
  },
)

const groupClasses = computed(() => [
  'otp-input',
  props.entrance && 'otp-input--entrance',
  shaking.value && 'is-shaking',
  props.verified && 'is-verified',
])

defineExpose({ focusFirst: () => focusBox(0) })
</script>

<template>
  <div
    :class="groupClasses"
    role="group"
    :aria-label="label"
    :aria-describedby="describedBy"
    @animationend.self="shaking = false"
  >
    <input
      v-for="(digit, index) in digits"
      :key="index"
      ref="boxes"
      class="otp-input__box"
      :class="boxClasses(index)"
      type="text"
      inputmode="numeric"
      autocomplete="one-time-code"
      maxlength="1"
      :value="digit"
      :disabled="disabled"
      :aria-label="`${label} — ${index + 1}`"
      :aria-invalid="invalid || undefined"
      @input="onInput(index, $event)"
      @keydown="onKeydown(index, $event)"
      @paste="onPaste(index, $event)"
      @focus="$event.target.select()"
    />
  </div>
</template>
