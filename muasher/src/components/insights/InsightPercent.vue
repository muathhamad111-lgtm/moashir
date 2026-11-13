<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { percentText } from '@/utils/percentText'

const props = defineProps({
  value: { type: [String, Number], required: true },
})

const { t, n } = useI18n()
const text = computed(() => percentText(n, t, Number(props.value)))
</script>

<template>
  <bdi class="insight-percent" dir="ltr">{{ text }}</bdi>
</template>
