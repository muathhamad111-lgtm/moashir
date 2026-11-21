<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import DashboardValue from '../DashboardValue.vue'
import OverviewCard from './OverviewCard.vue'
import {
  ASSESSOR_ALERT_CARDS,
  ASSESSOR_CARD_ICONS,
  ASSESSOR_LOWER_IS_BETTER,
  ASSESSOR_OPEN_STATES,
} from '@/constants/dashboardOverview'

// The assessor's tiles: the open reviews (wide, brand-filled) with their states, the live alerts
// in their tone, then the period tiles with the change from the previous period.
const props = defineProps({
  liveCards: { type: Array, required: true },
  periodCards: { type: Array, default: () => [] },
})

const { t } = useI18n()

function liveValue(key) {
  return Number(props.liveCards.find((card) => card.key === key)?.value) || 0
}

const states = computed(() =>
  ASSESSOR_OPEN_STATES.map((key) => ({
    key,
    label: t(`dashboard.cards.${key}`),
    value: liveValue(key),
  })),
)
const openTotal = computed(() => states.value.reduce((sum, state) => sum + state.value, 0))
const alerts = computed(() =>
  ASSESSOR_ALERT_CARDS.map((alert) => ({
    ...alert,
    card: { key: alert.key, value: liveValue(alert.key), previous: null, format: 'count' },
  })),
)
const label = (key) => t(`dashboard.assessorOverview.cards.${key}`)
</script>

<template>
  <ul
    class="overview-cards assessor-cards"
    :aria-label="t('dashboard.assessorOverview.cardsLabel')"
  >
    <li
      class="overview-cards__card overview-cards__card--featured assessor-cards__open"
      data-card="open"
    >
      <span class="overview-cards__icon" aria-hidden="true"><AppIcon name="inbox" /></span>
      <span class="overview-cards__body">
        <span class="overview-cards__value">
          <DashboardValue :value="openTotal" format="count" />
        </span>
        <span class="overview-cards__label">{{ t('dashboard.assessorOverview.openTitle') }}</span>
      </span>
      <ul
        class="assessor-cards__states"
        :aria-label="t('dashboard.assessorOverview.breakdownLabel')"
      >
        <li
          v-for="state in states"
          :key="state.key"
          class="assessor-cards__state"
          :data-state="state.key"
        >
          <span>{{ state.label }}</span>
          <strong><DashboardValue :value="state.value" format="count" /></strong>
        </li>
      </ul>
    </li>
    <li
      v-for="alert in alerts"
      :key="alert.key"
      class="overview-cards__card assessor-cards__alert"
      :class="{ [`assessor-cards__alert--${alert.tone}`]: alert.card.value > 0 }"
      :data-card="alert.key"
    >
      <OverviewCard :card="alert.card" :label="label(alert.key)" :icon="alert.icon" />
    </li>
    <li
      v-for="card in periodCards"
      :key="card.key"
      class="overview-cards__card"
      :data-card="card.key"
    >
      <OverviewCard
        :card="card"
        :label="label(card.key)"
        :icon="ASSESSOR_CARD_ICONS[card.key]"
        :lower-is-better="ASSESSOR_LOWER_IS_BETTER.includes(card.key)"
      />
    </li>
  </ul>
</template>
