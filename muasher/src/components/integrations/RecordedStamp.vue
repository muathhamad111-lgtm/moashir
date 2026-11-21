<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDate } from '@/utils/datetime'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  kind: {
    type: String,
    required: true,
    validator: (value) =>
      ['confirmed', 'acknowledged', 'uploaded', 'registered', 'loaded'].includes(value),
  },
  at: { type: String, default: null },
  by: { type: Object, default: null },
})

const { t, locale } = useI18n()
const text = computed(() => {
  if (!props.at) return ''
  const at = formatDate(props.at, locale.value, { dateStyle: 'medium', timeStyle: 'short' })
  return props.by?.name
    ? t(`integrations.stamp.${props.kind}.atBy`, { at, by: props.by.name })
    : t(`integrations.stamp.${props.kind}.at`, { at })
})
</script>

<template>
  <p v-if="text" class="recorded-stamp">
    <AppIcon name="user-clock" />
    {{ text }}
  </p>
</template>
