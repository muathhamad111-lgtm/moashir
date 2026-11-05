<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  title: { type: String, required: true },
  steps: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <section class="provider-steps" :aria-label="title">
    <h3 class="provider-steps__title">
      <i class="fa-solid fa-list-check" aria-hidden="true"></i>
      {{ title }}
    </h3>
    <ol class="provider-steps__list">
      <li
        v-for="step in steps"
        :key="step.key"
        class="provider-steps__item"
        :class="{ 'is-done': step.done === true }"
      >
        <span class="provider-steps__text">{{ step.label }}</span>
        <span v-if="step.done !== undefined" class="provider-steps__state">
          <i
            :class="step.done ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle'"
            aria-hidden="true"
          ></i>
          {{ t(step.done ? 'integrations.steps.done' : 'integrations.steps.pending') }}
        </span>
      </li>
    </ol>
    <div v-if="$slots.default" class="provider-steps__extra">
      <slot />
    </div>
  </section>
</template>
