<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  page: { type: Number, required: true },
  lastPage: { type: Number, required: true },
  disabled: { type: Boolean, default: false },
  maxButtons: { type: Number, default: 5 },
})

const emit = defineEmits(['update:page'])
const { t } = useI18n()

const pages = computed(() => {
  const total = props.lastPage
  const max = Math.min(props.maxButtons, total)
  let start = Math.max(1, props.page - Math.floor(max / 2))
  if (start + max - 1 > total) start = total - max + 1
  return Array.from({ length: max }, (_, index) => start + index)
})

function goTo(target) {
  if (props.disabled || target < 1 || target > props.lastPage || target === props.page) return
  emit('update:page', target)
}
</script>

<template>
  <nav class="pagination" :aria-label="t('components.pagination.label')">
    <button
      type="button"
      class="pagination__item"
      :class="{ 'is-disabled': disabled || page <= 1 }"
      :disabled="disabled || page <= 1"
      :aria-label="t('components.pagination.previous')"
      @click="goTo(page - 1)"
    >
      <i class="fa-solid fa-chevron-left u-flip-in-rtl" aria-hidden="true"></i>
    </button>
    <button
      v-for="candidate in pages"
      :key="candidate"
      type="button"
      class="pagination__item"
      :class="{ 'is-active': candidate === page, 'is-disabled': disabled }"
      :disabled="disabled"
      :aria-label="t('components.pagination.goTo', { page: candidate })"
      :aria-current="candidate === page ? 'page' : undefined"
      @click="goTo(candidate)"
    >
      {{ candidate }}
    </button>
    <button
      type="button"
      class="pagination__item"
      :class="{ 'is-disabled': disabled || page >= lastPage }"
      :disabled="disabled || page >= lastPage"
      :aria-label="t('components.pagination.next')"
      @click="goTo(page + 1)"
    >
      <i class="fa-solid fa-chevron-right u-flip-in-rtl" aria-hidden="true"></i>
    </button>
  </nav>
</template>
