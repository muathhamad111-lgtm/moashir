<script setup>
import { ref, useId } from 'vue'
import LandingLink from '@/components/landing/LandingLink.vue'
import { useDisclosure } from '@/composables/useDisclosure'

const props = defineProps({
  group: { type: Object, required: true },
  current: { type: String, default: null },
})

const emit = defineEmits(['navigate'])

const panelId = useId()
const rootRef = ref(null)
const triggerRef = ref(null)
const { isOpen, toggle, close, onKeydown, onFocusOut } = useDisclosure({ rootRef, triggerRef })

function isCurrent(link) {
  return Boolean(link.anchor) && props.current === link.anchor
}

function navigate() {
  close()
  emit('navigate')
}
</script>

<template>
  <div
    ref="rootRef"
    class="lp-nav-group"
    :class="{ 'is-open': isOpen }"
    @keydown="onKeydown"
    @focusout="onFocusOut"
  >
    <button
      ref="triggerRef"
      type="button"
      class="lp-top__link lp-nav-group__trigger"
      :class="{ 'is-active': group.children.some(isCurrent) }"
      :aria-expanded="isOpen ? 'true' : 'false'"
      :aria-controls="panelId"
      @click="toggle"
    >
      <span>{{ group.label }}</span>
      <svg class="lp-nav-group__caret" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M6 9l6 6 6-6" />
      </svg>
    </button>
    <ul v-show="isOpen" :id="panelId" class="lp-nav-group__panel">
      <li v-for="link in group.children" :key="link.key">
        <a
          v-if="link.href"
          class="lp-nav-group__link"
          :href="link.href"
          target="_blank"
          rel="noopener noreferrer"
          @click="navigate"
        >
          {{ link.label }}
        </a>
        <LandingLink
          v-else
          class="lp-nav-group__link"
          :class="{ 'is-active': isCurrent(link) }"
          :aria-current="isCurrent(link) ? 'true' : undefined"
          :to="link.anchor ? `/#${link.anchor}` : String(link.to)"
          @navigate="navigate"
        >
          {{ link.label }}
        </LandingLink>
      </li>
    </ul>
  </div>
</template>
