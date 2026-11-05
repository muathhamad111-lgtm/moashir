<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'

defineProps({
  cycleId: { type: Number, required: true },
})

const { t } = useI18n()
</script>

<template>
  <nav class="results-tabs" :aria-label="t('award.results.tabsLabel')">
    <RouterLink
      class="results-tabs__link"
      exact-active-class="is-active"
      :to="{ name: ROUTE_NAMES.AWARD_RESULTS_BOARD, params: { id: cycleId } }"
      >{{ t('award.results.regionalTab') }}</RouterLink
    >
    <RouterLink
      class="results-tabs__link"
      exact-active-class="is-active"
      :to="{ name: ROUTE_NAMES.AWARD_NATIONAL, params: { id: cycleId } }"
      >{{ t('award.results.nationalTab') }}</RouterLink
    >
  </nav>
</template>
