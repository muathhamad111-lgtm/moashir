<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import HomeSectionHead from '@/components/public/HomeSectionHead.vue'
import HomePill from '@/components/public/HomePill.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const LIVE = 'live'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const { t } = useI18n()
const cards = computed(() => props.section.items ?? [])
const isComingSoon = computed(() => props.section.meta?.availability !== LIVE)
</script>

<template>
  <section class="home-section home-kit" aria-labelledby="home-kit-title">
    <div class="home-section__container">
      <HomeSectionHead :section="section" :localized="localized" title-id="home-kit-title">
        <template #before>
          <HomePill v-if="isComingSoon" class="home-kit__availability" tone="soon">
            {{ t('public.home.comingSoon') }}
          </HomePill>
        </template>
      </HomeSectionHead>
      <ul v-reveal="{ stagger: true }" class="home-kit__grid">
        <li v-for="card in cards" :key="card.id" class="home-kit__card">
          <span class="home-kit__icon" aria-hidden="true">
            <AppIcon :name="card.icon || 'lightning'" />
          </span>
          <h3 class="home-kit__card-title">{{ localized(card.title) }}</h3>
          <p v-if="card.description" class="home-kit__card-desc">
            {{ localized(card.description) }}
          </p>
        </li>
      </ul>
    </div>
  </section>
</template>
