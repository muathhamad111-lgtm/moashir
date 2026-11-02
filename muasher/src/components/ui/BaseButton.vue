<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'ghost', 'danger'].includes(value),
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  type: {
    type: String,
    default: 'button',
    validator: (value) => ['button', 'submit', 'reset'].includes(value),
  },
  icon: { type: String, default: '' },
  iconOnly: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['click'])

const classes = computed(() => [
  'btn',
  `btn--${props.variant}`,
  props.size !== 'md' && `btn--${props.size}`,
  props.iconOnly && 'btn--icon',
  props.loading && 'btn--loading',
])

function onClick(event) {
  if (props.disabled || props.loading) return
  emit('click', event)
}
</script>

<template>
  <button
    :type="type"
    :class="classes"
    :disabled="disabled || loading"
    :aria-busy="loading || undefined"
    @click="onClick"
  >
    <span v-if="loading" class="btn__spinner" aria-hidden="true"></span>
    <i v-if="icon && !loading" :class="icon" class="btn__icon" aria-hidden="true"></i>
    <span v-if="!iconOnly" class="btn__label"><slot /></span>
  </button>
</template>
