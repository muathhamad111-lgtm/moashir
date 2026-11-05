<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const { t } = useI18n()

const cards = computed(() => [...(props.section.items ?? [])].sort((a, b) => a.order - b.order))

const howItWorks = computed(() => props.section.meta?.howItWorks ?? null)

const kitImage = computed(() => props.section.image || null)
const kitAlt = computed(() => props.localized(props.section.title) || t('illustrations.fastKit'))
</script>

<template>
  <div class="home-section home-kit">
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

      <IllustrationSlot
        v-if="kitImage"
        class="home-kit__media"
        :src="kitImage"
        :alt="kitAlt"
        variant="banner"
      />

      <div class="home-kit__grid">
        <article v-for="card in cards" :key="card.id" class="home-kit__card">
          <span class="home-kit__icon" aria-hidden="true">
            <i :class="card.icon || 'fa-solid fa-bolt'"></i>
          </span>
          <h3 class="home-kit__card-title">{{ localized(card.title) }}</h3>
          <p v-if="card.description" class="home-kit__card-desc">
            {{ localized(card.description) }}
          </p>
        </article>
      </div>

      <aside v-if="howItWorks" class="home-kit__note" aria-labelledby="home-kit-note-title">
        <span class="home-kit__note-icon" aria-hidden="true">
          <i class="fa-solid fa-circle-info"></i>
        </span>
        <div class="home-kit__note-body">
          <h3 id="home-kit-note-title" class="home-kit__note-title">
            {{ localized(howItWorks.title) }}
          </h3>
          <p class="home-kit__note-text">{{ localized(howItWorks.body) }}</p>
        </div>
      </aside>

      <div class="home-kit__cta">
        <router-link class="home-kit__cta-link" :to="{ name: ROUTE_NAMES.BAG_DETAILS }">
          <BaseButton variant="primary" icon="fa-solid fa-briefcase" tabindex="-1">
            {{ t('home.fastKit.detailsCta') }}
          </BaseButton>
        </router-link>
      </div>
    </div>
  </div>
</template>
