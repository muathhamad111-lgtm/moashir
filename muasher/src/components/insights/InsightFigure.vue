<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  value: { type: [String, Number], required: true },
})

const { n } = useI18n()
const text = computed(() => formatNumber(n, Number(props.value)))
</script>

<template>
  <bdi dir="ltr">{{ text }}</bdi>
</template>
