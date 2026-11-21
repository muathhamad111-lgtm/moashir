<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  tier: { type: Object, default: null },
})

const ringing = ref(false)

watch(
  () => props.tier?.key,
  () => {
    ringing.value = false
    requestAnimationFrame(() => (ringing.value = true))
  },
)
</script>

<template>
  <div
    class="lp-lv__seal"
    :class="{ 'is-ringing': ringing }"
    :style="tier ? { '--sg': tier.glow } : undefined"
    aria-hidden="true"
  >
    <Transition name="lp-seal-swap">
      <img v-if="tier" :key="tier.key" :src="tier.seal" alt="" width="180" height="180" />
    </Transition>
  </div>
</template>
