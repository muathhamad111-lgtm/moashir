<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import OverviewCard from './OverviewCard.vue'
import { OVERVIEW_CARD_ICONS, OVERVIEW_CARD_ORDER } from '@/constants/dashboardOverview'

const props = defineProps({
  cards: { type: Array, required: true },
})

const { t } = useI18n()

function rank(card) {
  const index = OVERVIEW_CARD_ORDER.indexOf(card.key)
  return index === -1 ? OVERVIEW_CARD_ORDER.length : index
}

const ordered = computed(() => [...props.cards].sort((a, b) => rank(a) - rank(b)))
</script>

<template>
  <ul class="overview-cards" :aria-label="t('dashboard.overview.cardsLabel')">
    <li
      v-for="(card, index) in ordered"
      :key="card.key"
      class="overview-cards__card"
      :class="{ 'overview-cards__card--featured': index === 0 }"
      :data-card="card.key"
    >
      <OverviewCard
        :card="card"
        :label="t(`dashboard.overview.cards.${card.key}`)"
        :icon="OVERVIEW_CARD_ICONS[card.key] ?? 'pie-chart'"
      />
    </li>
  </ul>
</template>
