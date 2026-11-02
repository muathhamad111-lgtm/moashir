<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const router = useRouter()

const lenses = computed(() =>
  [...(props.section.items ?? [])].sort((a, b) => a.order - b.order),
)

const ruleNote = computed(() => props.section.meta?.rule ?? null)

function goToCta() {
  const url = props.section.ctaUrl
  if (!url) return
  if (/^https?:\/\//.test(url)) {
    window.open(url, '_blank', 'noopener')
    return
  }
  router.push(url)
}
</script>

<template>
  <div class="home-section home-lenses">
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

      <ul class="home-lenses__grid">
        <li
          v-for="lens in lenses"
          :key="lens.id"
          class="home-lens"
        >
          <span class="home-lens__icon" aria-hidden="true">
            <i :class="lens.icon || 'fa-solid fa-chart-pie'"></i>
          </span>
          <div class="home-lens__body">
            <h3 class="home-lens__title">{{ localized(lens.title) }}</h3>
            <p v-if="lens.description" class="home-lens__desc">
              {{ localized(lens.description) }}
            </p>
          </div>
        </li>
      </ul>

      <p v-if="ruleNote" class="home-lenses__rule">
        <i class="home-lenses__rule-icon fa-solid fa-shield-halved" aria-hidden="true"></i>
        <span>{{ localized(ruleNote) }}</span>
      </p>

      <div v-if="section.ctaLabel" class="home-lenses__cta">
        <BaseButton
          variant="primary"
          icon="fa-solid fa-arrow-right"
          @click="goToCta"
        >
          {{ localized(section.ctaLabel) }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>
