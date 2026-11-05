<script setup>
import { computed } from 'vue'
import { SECTION_ALIGN } from '@/constants/homeSections'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
  align: {
    type: String,
    default: SECTION_ALIGN.center,
    validator: (value) => Object.values(SECTION_ALIGN).includes(value),
  },
  titleId: { type: String, default: undefined },
})

const eyebrow = computed(() => props.localized(props.section.meta?.eyebrow))
const subtitle = computed(() => props.localized(props.section.subtitle))
const description = computed(() => props.localized(props.section.description))
</script>

<template>
  <header
    v-reveal="{ stagger: true }"
    class="home-section__head"
    :class="`home-section__head--${align}`"
  >
    <slot name="before" />
    <span v-if="eyebrow" class="home-section__eyebrow">
      <span class="home-section__eyebrow-line" aria-hidden="true"></span>
      {{ eyebrow }}
    </span>
    <h2 :id="titleId" class="home-section__title">{{ localized(section.title) }}</h2>
    <p v-if="subtitle" class="home-section__subtitle">{{ subtitle }}</p>
    <p v-if="description" class="home-section__description">{{ description }}</p>
    <slot />
  </header>
</template>
