<script setup>
import { ref, useId } from 'vue'

const props = defineProps({
  items: { type: Array, required: true },
  multiple: { type: Boolean, default: false },
})

const baseId = useId()
const openIds = ref([])

function isOpen(item) {
  return openIds.value.includes(item.id)
}

function toggle(item) {
  if (isOpen(item)) {
    openIds.value = openIds.value.filter((id) => id !== item.id)
    return
  }
  openIds.value = props.multiple ? [...openIds.value, item.id] : [item.id]
}

function triggerId(item) {
  return `${baseId}-trigger-${item.id}`
}

function panelId(item) {
  return `${baseId}-panel-${item.id}`
}
</script>

<template>
  <div class="accordion">
    <div
      v-for="item in items"
      :key="item.id"
      class="accordion__item"
      :class="{ 'is-open': isOpen(item) }"
    >
      <h3 class="accordion__header">
        <button
          :id="triggerId(item)"
          type="button"
          class="accordion__trigger"
          :aria-expanded="isOpen(item)"
          :aria-controls="panelId(item)"
          @click="toggle(item)"
        >
          <span>{{ item.title }}</span>
          <i class="fa-solid fa-chevron-down accordion__chevron" aria-hidden="true"></i>
        </button>
      </h3>
      <div
        v-if="isOpen(item)"
        :id="panelId(item)"
        class="accordion__panel"
        role="region"
        :aria-labelledby="triggerId(item)"
      >
        <slot name="item" :item="item">{{ item.content }}</slot>
      </div>
    </div>
  </div>
</template>
