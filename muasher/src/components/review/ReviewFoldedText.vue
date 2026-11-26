<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'

// A long text (the entity's CV) shows two lines first, with «عرض المزيد» to read the rest.
const props = defineProps({
  title: { type: String, required: true },
  text: { type: String, default: '' },
  emptyText: { type: String, required: true },
})

// Roughly two lines of the card at desktop width; shorter texts show whole without a toggle.
const FOLD_AFTER = 180

const { t } = useI18n()
const id = useId()
const open = ref(false)
const isLong = computed(() => props.text.length > FOLD_AFTER)
</script>

<template>
  <div class="review-folded">
    <p class="review-folded__title">{{ title }}</p>
    <p v-if="text" :id="id" class="review-folded__text" :class="{ 'is-folded': isLong && !open }">
      {{ text }}
    </p>
    <p v-else class="review-folded__empty">{{ emptyText }}</p>
    <button
      v-if="isLong"
      type="button"
      class="review-folded__toggle"
      :aria-expanded="open"
      :aria-controls="id"
      @click="open = !open"
    >
      {{ t(open ? 'review.showLess' : 'review.showMore') }}
    </button>
  </div>
</template>
