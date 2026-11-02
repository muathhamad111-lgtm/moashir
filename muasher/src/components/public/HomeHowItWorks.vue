<script setup>
import { computed } from 'vue'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const steps = computed(() =>
  [...(props.section.items ?? [])].sort((a, b) => a.order - b.order),
)
</script>

<template>
  <div class="home-section home-steps">
    <div class="home-section__container">
      <header class="home-section__head">
        <h2 class="home-section__title">{{ localized(section.title) }}</h2>
        <p v-if="section.subtitle" class="home-section__subtitle">
          {{ localized(section.subtitle) }}
        </p>
        <p v-if="section.description" class="home-section__description">
          {{ localized(section.description) }}
        </p>
      </header>

      <ol class="home-steps__list">
        <li
          v-for="(step, index) in steps"
          :key="step.id"
          class="home-step"
        >
          <span class="home-step__marker" aria-hidden="true">
            <span class="home-step__number">{{ index + 1 }}</span>
          </span>
          <div class="home-step__body">
            <h3 class="home-step__title">{{ localized(step.title) }}</h3>
            <p v-if="step.description" class="home-step__desc">
              {{ localized(step.description) }}
            </p>
          </div>
        </li>
      </ol>
    </div>
  </div>
</template>
