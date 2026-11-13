<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'
import { isRightToLeft, tabIndexAfterKey } from '@/utils/tabNavigation'

const props = defineProps({
  tabs: { type: Array, required: true },
  modelValue: { type: String, required: true },
  label: { type: String, required: true },
  panelId: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { n } = useI18n()
const tabRefs = ref([])

function tabId(value) {
  return `${props.panelId}-tab-${value}`
}

function hasCount(tab) {
  return tab.count !== null && tab.count !== undefined
}

function select(index) {
  const tab = props.tabs[index]
  if (!tab) return
  emit('update:modelValue', tab.value)
  tabRefs.value[index]?.focus()
}

function onKeydown(event, index) {
  const next = tabIndexAfterKey(event.key, index, props.tabs.length, isRightToLeft())
  if (next === null) return
  event.preventDefault()
  select(next)
}
</script>

<template>
  <div class="area-tabs" role="tablist" :aria-label="label">
    <button
      v-for="(tab, index) in tabs"
      :id="tabId(tab.value)"
      :key="tab.value"
      ref="tabRefs"
      type="button"
      role="tab"
      class="area-tabs__tab"
      :class="{ 'is-active': tab.value === modelValue }"
      :aria-selected="tab.value === modelValue"
      :aria-controls="panelId"
      :tabindex="tab.value === modelValue ? 0 : -1"
      @click="select(index)"
      @keydown="onKeydown($event, index)"
    >
      <span class="area-tabs__label">{{ tab.label }}</span>
      <bdi v-if="hasCount(tab)" class="area-tabs__count" dir="ltr">{{
        formatNumber(n, tab.count)
      }}</bdi>
    </button>
  </div>
</template>
