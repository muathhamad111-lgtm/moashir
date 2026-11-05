<script setup>
import { computed } from 'vue'
import { ICON_SIZES } from '@/constants/icons'
import { resolveIcon } from '@/utils/icons/iconRegistry'

const props = defineProps({
  name: { type: String, default: '' },
  size: {
    type: String,
    default: '',
    validator: (value) => value === '' || ICON_SIZES.includes(value),
  },
  label: { type: String, default: '' },
  mirror: { type: Boolean, default: true },
})

const icon = computed(() => resolveIcon(props.name))
const classes = computed(() => [
  'app-icon',
  props.size && `app-icon--${props.size}`,
  icon.value?.flipped && 'app-icon--flip',
  props.mirror && icon.value?.mirrorInRtl && 'app-icon--mirror',
  icon.value?.spinning && 'app-icon--spin',
])
</script>

<template>
  <svg
    v-if="icon"
    :class="classes"
    :data-icon="icon.name"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="currentColor"
    focusable="false"
    :role="label ? 'img' : undefined"
    :aria-label="label || undefined"
    :aria-hidden="label ? undefined : 'true'"
  >
    <path v-for="path in icon.paths" :key="path" :d="path" />
  </svg>
</template>
