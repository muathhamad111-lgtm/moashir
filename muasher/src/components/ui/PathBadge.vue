<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PATH_ICONS } from '@/constants/paths'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'
import { usePathNames } from '@/composables/usePathNames'

const props = defineProps({
  path: { type: String, required: true },
  label: { type: Object, default: null },
  compact: { type: Boolean, default: false },
})

const { te, locale } = useI18n()
const { pathName } = usePathNames()

const known = computed(() => Boolean(PATH_ICONS[props.path]))
const text = computed(() => {
  const served = localizedText(props.label, locale.value)
  if (served) return served
  return te(`paths.labels.${props.path}`) ? pathName(props.path) : props.path
})
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
    <span class="path-badge__label">{{ text }}</span>
  </span>
</template>
