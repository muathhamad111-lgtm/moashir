<script setup>
import { computed } from 'vue'
import DeltaChip from '@/components/charts/DeltaChip.vue'
import { deltaValue } from '@/utils/insights'

const props = defineProps({
  deltaPp: { type: [String, Number], default: null },
  onBand: { type: Boolean, default: false },
})

const value = computed(() => deltaValue(props.deltaPp))
</script>

<template>
  <DeltaChip v-if="value !== null" :value="value" :on-band="onBand" />
</template>
