<script setup>
import { computed } from 'vue'
import { PATH_ICONS } from '@/constants/paths'
import AppIcon from '@/components/ui/AppIcon.vue'
import { usePathLabelText } from '@/composables/usePathLabelText'

const props = defineProps({
  path: { type: String, required: true },
  label: { type: Object, default: null },
  compact: { type: Boolean, default: false },
})

const known = computed(() => Boolean(PATH_ICONS[props.path]))
const text = usePathLabelText(
  () => props.path,
  () => props.label,
)
</script>

<template>
  <span
    class="path-badge"
    :class="[
      known ? `path-badge--${path}` : 'path-badge--other',
      { 'path-badge--compact': compact },
    ]"
  >
    <AppIcon :name="PATH_ICONS[path] ?? 'route'" class="path-badge__icon" />
    <span class="path-badge__label" :title="text">{{ text }}</span>
  </span>
</template>
