<script setup>
import { computed, nextTick, onMounted, ref, useId, watch } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], required: true },
  tabs: { type: Array, required: true },
})

const emit = defineEmits(['update:modelValue'])

const baseId = useId()
const tabRefs = ref([])

const activeIndex = computed(() => props.tabs.findIndex((tab) => tab.value === props.modelValue))

// On a narrow screen the tab list scrolls sideways: keep the active tab in view, on load and on
// change. Only the list scrolls (by a physical offset, so it holds in RTL); the page never moves.
function revealActiveTab() {
  const tab = tabRefs.value[activeIndex.value]
  const list = tab?.parentElement
  if (!list || list.scrollWidth <= list.clientWidth) return
  const listBox = list.getBoundingClientRect()
  const tabBox = tab.getBoundingClientRect()
  const overflow =
    Math.min(tabBox.left - listBox.left, 0) || Math.max(tabBox.right - listBox.right, 0)
  if (overflow) list.scrollBy({ left: overflow })
}

watch(activeIndex, () => nextTick(revealActiveTab))
onMounted(revealActiveTab)

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

// The arrow that points along the reading direction moves forward: ← in Arabic, → in English.
function stepOf(event) {
  const isRtl = getComputedStyle(event.currentTarget).direction === 'rtl'
  const forwardKey = isRtl ? 'ArrowLeft' : 'ArrowRight'
  return event.key === forwardKey ? 1 : -1
}

function onKeydown(event, index) {
  switch (event.key) {
    case 'ArrowRight':
    case 'ArrowLeft':
      event.preventDefault()
      focusTab(index + stepOf(event))
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
        <span v-if="tab.badge" class="tabs__badge" :aria-label="tab.badgeLabel || undefined">{{
          tab.badge
        }}</span>
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
