<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import IntegrationOverviewCard from '@/components/integrations/IntegrationOverviewCard.vue'
import TestModeBanner from '@/components/integrations/TestModeBanner.vue'
import { OVERVIEW_STATES, useIntegrationsOverview } from '@/composables/useIntegrationsOverview'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const { rows, status, errorMessage, paymentTestInProduction, load } = useIntegrationsOverview()

onMounted(load)
</script>

<template>
  <section class="integrations-overview">
    <header class="integrations-overview__header">
      <RouterLink :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS }" class="settings-section__back">
        <AppIcon name="arrow-left" />
        {{ t('settings.hub.backToHub') }}
      </RouterLink>
      <h1 class="integrations-overview__title">{{ t('integrations.overview.title') }}</h1>
      <p class="integrations-overview__desc">{{ t('integrations.overview.description') }}</p>
    </header>

    <div
      v-if="status === OVERVIEW_STATES.LOADING"
      class="integrations-overview__grid"
      aria-busy="true"
    >
      <Skeleton v-for="index in 4" :key="index" variant="block" />
    </div>
    <EmptyState
      v-else-if="status === OVERVIEW_STATES.FORBIDDEN"
      icon="lock"
      :title="t('integrations.forbidden.title')"
      :message="t('integrations.forbidden.message')"
    />
    <ErrorState
      v-else-if="status === OVERVIEW_STATES.ERROR"
      :message="errorMessage"
      @retry="load"
    />
    <EmptyState
      v-else-if="status === OVERVIEW_STATES.EMPTY"
      icon="link"
      :title="t('integrations.overview.emptyTitle')"
      :message="t('integrations.overview.emptyMessage')"
    />
    <template v-else>
      <TestModeBanner v-if="paymentTestInProduction" />
      <div class="integrations-overview__grid">
        <IntegrationOverviewCard v-for="row in rows" :key="row.kind" :row="row" />
      </div>
    </template>
  </section>
</template>
