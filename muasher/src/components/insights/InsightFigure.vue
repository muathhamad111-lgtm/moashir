<script setup>
import { computed } from 'vue'
import { pointsText } from '@/utils/percentText'

const props = defineProps({
  value: { type: [String, Number], required: true },
})

const text = computed(() => pointsText(props.value))
</script>

<template>
  <bdi dir="ltr">{{ text }}</bdi>
</template>
