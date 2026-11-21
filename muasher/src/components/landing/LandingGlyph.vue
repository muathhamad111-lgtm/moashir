<script setup>
import { computed, onMounted, ref } from 'vue'
import { landingGlyph } from '@/constants/landingGlyphs'

const props = defineProps({
  name: { type: String, default: '' },
})

const FRAME = 32
const HALF = FRAME / 2
const svgRef = ref(null)
const viewBox = ref(`0 0 ${FRAME} ${FRAME}`)
const shapes = computed(() => landingGlyph(props.name))

function centred(box) {
  const x = box.x + box.width / 2 - HALF
  const y = box.y + box.height / 2 - HALF
  return `${x.toFixed(2)} ${y.toFixed(2)} ${FRAME} ${FRAME}`
}

onMounted(() => {
  const box = svgRef.value?.getBBox?.()
  if (box && box.width && box.height) viewBox.value = centred(box)
})
</script>

<template>
  <svg ref="svgRef" class="lp-glyph" :viewBox="viewBox" aria-hidden="true" focusable="false">
    <component
      :is="shape.tag"
      v-for="(shape, index) in shapes"
      :key="index"
      v-bind="shape.attrs"
      pathLength="1"
    />
  </svg>
</template>
