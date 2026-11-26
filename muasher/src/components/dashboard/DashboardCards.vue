<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import DashboardValue from './DashboardValue.vue'
import { isInternalPath } from '@/utils/internalPath'

defineProps({
  cards: { type: Array, default: () => [] },
})

const { t, te } = useI18n()

function labelOf(card) {
  const key = `dashboard.cards.${card.key}`
  return te(key) ? t(key) : card.key
}
</script>

<template>
  <ul class="dashboard-cards" :aria-label="t('dashboard.cardsLabel')">
    <li v-for="card in cards" :key="card.key" class="dashboard-cards__item" :data-card="card.key">
      <RouterLink v-if="isInternalPath(card.link)" class="dashboard-cards__card" :to="card.link">
        <span class="dashboard-cards__value">
          <DashboardValue :value="card.value" :format="card.format" />
        </span>
        <span class="dashboard-cards__label">{{ labelOf(card) }}</span>
      </RouterLink>
      <div v-else class="dashboard-cards__card">
        <span class="dashboard-cards__value">
          <DashboardValue :value="card.value" :format="card.format" />
        </span>
        <span class="dashboard-cards__label">{{ labelOf(card) }}</span>
      </div>
    </li>
  </ul>
</template>
