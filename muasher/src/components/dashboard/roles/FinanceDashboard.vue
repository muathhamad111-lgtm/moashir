<script setup>
import { useI18n } from 'vue-i18n'
import { useDashboard } from '@/composables/useDashboard'
import RoleDashboardShell from '@/components/dashboard/RoleDashboardShell.vue'
import DashboardStatsGroup from '@/components/dashboard/DashboardStatsGroup.vue'
import MiniBarChart from '@/components/dashboard/MiniBarChart.vue'
import Card from '@/components/ui/Card.vue'

const { t } = useI18n()
const { summary, actions, status, errorMessage, load, localized } = useDashboard('finance')

const currencyUnit = t('dashboard.finance.currencyUnit')
</script>

<template>
  <RoleDashboardShell
    :status="status"
    :actions="actions"
    :error-message="errorMessage"
    :localized="localized"
    @retry="load"
  >
    <section class="role-dashboard__section" aria-labelledby="finance-summary">
      <h2 id="finance-summary" class="role-dashboard__section-title">
        {{ t('dashboard.finance.summary.title') }}
      </h2>
      <DashboardStatsGroup :items="summary.summary" />
    </section>

    <div class="role-dashboard__two-col">
      <Card :title="t('dashboard.finance.revenueTrend')">
        <MiniBarChart
          :series="summary.revenueTrend"
          :localized="localized"
          color="success"
          :unit="currencyUnit"
        />
      </Card>
      <Card :title="t('dashboard.finance.topServices')">
        <MiniBarChart
          :series="summary.topServices"
          :localized="localized"
          color="primary"
          :unit="currencyUnit"
        />
      </Card>
    </div>
  </RoleDashboardShell>
</template>
