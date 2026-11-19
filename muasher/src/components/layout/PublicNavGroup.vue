<script setup>
import { ref, useId } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useDisclosure } from '@/composables/useDisclosure'

const props = defineProps({
  group: { type: Object, required: true },
  isActive: { type: Function, required: true },
  variant: { type: String, default: 'bar' },
})

const emit = defineEmits(['navigate'])

const panelId = useId()
const rootRef = ref(null)
const triggerRef = ref(null)
const { isOpen, toggle, close, onKeydown, onFocusOut } = useDisclosure({ rootRef, triggerRef })

function navigate(event, link) {
  close()
  emit('navigate', event, link)
}
</script>

<template>
  <div
    ref="rootRef"
    class="public-nav-group"
    :class="[`public-nav-group--${variant}`, { 'is-open': isOpen }]"
    @keydown="onKeydown"
    @focusout="onFocusOut"
  >
    <button
      ref="triggerRef"
      type="button"
      class="public-nav-links__link public-nav-group__trigger"
      :class="{ 'is-active': props.group.children.some(isActive) }"
      :aria-expanded="isOpen ? 'true' : 'false'"
      :aria-controls="panelId"
      @click="toggle"
    >
      <span>{{ group.label }}</span>
      <AppIcon name="chevron-down" class="public-nav-group__caret" />
    </button>
    <ul v-show="isOpen" :id="panelId" class="public-nav-group__panel">
      <li v-for="link in group.children" :key="link.key">
        <a
          v-if="link.href"
          class="public-nav-group__link"
          :href="link.href"
          target="_blank"
          rel="noopener noreferrer"
          @click="close"
        >
          {{ link.label }}
        </a>
        <router-link
          v-else
          class="public-nav-group__link"
          :class="{ 'is-active': isActive(link) }"
          :to="link.to"
          :aria-current="isActive(link) ? 'location' : undefined"
          @click="navigate($event, link)"
        >
          {{ link.label }}
        </router-link>
      </li>
    </ul>
  </div>
</template>
