<script setup>
import { computed } from 'vue'
import { referenceSegments } from '@/utils/referenceSegments'

const props = defineProps({
  text: { type: String, default: '' },
})

const segments = computed(() => referenceSegments(props.text))
</script>

<template>
  <template v-for="segment in segments" :key="segment.key">
    <bdi v-if="segment.isReference" class="reference-text" dir="ltr">{{ segment.text }}</bdi>
    <template v-else>{{ segment.text }}</template>
  </template>
</template>
