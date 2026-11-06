<script setup>
import { computed, ref, useId } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], required: true },
  tabs: { type: Array, required: true },
})

const emit = defineEmits(['update:modelValue'])

const baseId = useId()
const tabRefs = ref([])

const activeIndex = computed(() => props.tabs.findIndex((tab) => tab.value === props.modelValue))

function tabId(tab) {
  return `${baseId}-tab-${tab.value}`
}

function panelId(tab) {
  return `${baseId}-panel-${tab.value}`
}

function select(tab) {
  if (tab.disabled) return
  emit('update:modelValue', tab.value)
}

function focusTab(index) {
  const total = props.tabs.length
  const next = (index + total) % total
  tabRefs.value[next]?.focus()
  select(props.tabs[next])
}

function onKeydown(event, index) {
  switch (event.key) {
    case 'ArrowRight':
      event.preventDefault()
      focusTab(index + 1)
      break
    case 'ArrowLeft':
      event.preventDefault()
      focusTab(index - 1)
      break
    case 'Home':
      event.preventDefault()
      focusTab(0)
      break
    case 'End':
      event.preventDefault()
      focusTab(props.tabs.length - 1)
      break
  }
}
</script>

<template>
  <div class="tabs">
    <div class="tabs__list" role="tablist">
      <button
        v-for="(tab, index) in tabs"
        :id="tabId(tab)"
        :key="tab.value"
        :ref="(el) => (tabRefs[index] = el)"
        type="button"
        class="tabs__tab"
        :class="{ 'is-active': tab.value === modelValue }"
        role="tab"
        :aria-selected="tab.value === modelValue"
        :aria-controls="panelId(tab)"
        :tabindex="tab.value === modelValue ? 0 : -1"
        :disabled="tab.disabled || undefined"
        @click="select(tab)"
        @keydown="onKeydown($event, index)"
      >
        {{ tab.label }}
      </button>
    </div>
    <div
      v-if="activeIndex !== -1"
      :id="panelId(tabs[activeIndex])"
      class="tabs__panel"
      role="tabpanel"
      :aria-labelledby="tabId(tabs[activeIndex])"
    >
      <slot :active="modelValue" />
    </div>
  </div>
</template>
