<script setup>
import { ref, useId } from 'vue'

defineProps({
  text: { type: String, required: true },
})

const visible = ref(false)
const bubbleId = useId()

function show() {
  visible.value = true
}

function hide() {
  visible.value = false
}

function onKeydown(event) {
  if (event.key === 'Escape') hide()
}
</script>

<template>
  <span
    class="tooltip"
    @mouseenter="show"
    @mouseleave="hide"
    @focusin="show"
    @focusout="hide"
    @keydown="onKeydown"
  >
    <slot :described-by="visible ? bubbleId : undefined" />
    <span v-if="visible" :id="bubbleId" class="tooltip__bubble" role="tooltip">{{ text }}</span>
  </span>
</template>
