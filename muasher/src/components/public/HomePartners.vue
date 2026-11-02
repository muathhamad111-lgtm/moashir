<script setup>
import { computed } from 'vue'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const partners = computed(() =>
  [...(props.section.items ?? [])]
    .filter((partner) => partner.image)
    .sort((a, b) => a.order - b.order),
)
</script>

<template>
  <div v-if="partners.length" class="home-section home-partners">
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

      <ul class="home-partners__grid">
        <li v-for="partner in partners" :key="partner.id" class="home-partner">
          <img
            class="home-partner__logo"
            :src="partner.image"
            :alt="localized(partner.title)"
            loading="lazy"
          />
        </li>
      </ul>
    </div>
  </div>
</template>
