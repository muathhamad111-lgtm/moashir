<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { anchorOf, linkKind } from '@/utils/landing/links'
import { scrollBehavior } from '@/utils/motion'

const props = defineProps({
  to: { type: String, required: true },
})

const emit = defineEmits(['navigate'])

const router = useRouter()
const kind = computed(() => linkKind(props.to))
const anchor = computed(() => anchorOf(props.to))
const href = computed(() => (kind.value === 'anchor' ? `#${anchor.value}` : props.to))

function onClick(event) {
  emit('navigate')
  if (kind.value === 'route') {
    event.preventDefault()
    router.push(props.to)
    return
  }
  if (kind.value !== 'anchor') return
  const target = document.getElementById(anchor.value)
  if (!target) {
    event.preventDefault()
    router.push({ path: '/', hash: `#${anchor.value}` })
    return
  }
  event.preventDefault()
  target.scrollIntoView({ behavior: scrollBehavior() })
}
</script>

<template>
  <a
    :href="href"
    :target="kind === 'external' ? '_blank' : undefined"
    :rel="kind === 'external' ? 'noopener noreferrer' : undefined"
    @click="onClick"
  >
    <slot />
  </a>
</template>
