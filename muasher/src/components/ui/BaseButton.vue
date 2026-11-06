<script setup>
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'ghost', 'danger'].includes(value),
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg', 'xl'].includes(value),
  },
  type: {
    type: String,
    default: 'button',
    validator: (value) => ['button', 'submit', 'reset'].includes(value),
  },
  icon: { type: String, default: '' },
  iconEnd: { type: String, default: '' },
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
    <AppIcon v-if="icon && !loading" :name="icon" class="btn__icon" />
    <span v-if="!iconOnly" class="btn__label"><slot /></span>
    <AppIcon v-if="iconEnd && !loading" :name="iconEnd" class="btn__icon btn__icon--end" />
  </button>
</template>
