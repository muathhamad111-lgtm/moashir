<script setup>
import { inject } from 'vue'
import { STEP_COUNTER } from '@/utils/award/cycleSetup'

// The wizard provides a computed; templates unwrap top-level refs, so `counter` is already the text there.
const counter = inject(STEP_COUNTER, null)
</script>

<template>
  <span v-if="counter" class="cycle-setup__step-counter">{{ counter }}</span>
</template>
