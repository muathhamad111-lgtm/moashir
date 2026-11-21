<script setup>
import { useI18n } from 'vue-i18n'
import EmptyState from '@/components/feedback/EmptyState.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const kitRoute = { name: ROUTE_NAMES.BAG_INDEX }
</script>

<template>
  <EmptyState
    icon="lock"
    :title="t('insights.kitLocked.title')"
    :message="t('insights.kitLocked.message')"
    data-testid="insights-kit-locked"
  >
    <template #action>
      <router-link class="btn btn--primary btn--sm" :to="kitRoute">
        {{ t('insights.kitLocked.action') }}
      </router-link>
    </template>
  </EmptyState>
</template>
