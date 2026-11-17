<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import DashboardValue from '../DashboardValue.vue'
import OverviewDelta from './OverviewDelta.vue'
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
      <span class="overview-cards__icon" aria-hidden="true">
        <AppIcon :name="OVERVIEW_CARD_ICONS[card.key] ?? 'pie-chart'" />
      </span>
      <span class="overview-cards__body">
        <span class="overview-cards__value">
          <DashboardValue :value="card.value" :format="card.format" />
        </span>
        <span class="overview-cards__label">{{ t(`dashboard.overview.cards.${card.key}`) }}</span>
        <OverviewDelta
          v-if="card.previous !== null"
          :value="card.value"
          :previous="card.previous"
          :format="card.format"
        />
        <span v-else class="overview-cards__previous">{{
          t('dashboard.overview.currentState')
        }}</span>
      </span>
      <span v-if="card.previous !== null" class="overview-cards__previous overview-cards__was">
        {{ t('dashboard.overview.previousValue') }}
        <DashboardValue :value="card.previous" :format="card.format" />
      </span>
    </li>
  </ul>
</template>
