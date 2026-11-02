<script setup>
import { useI18n } from 'vue-i18n'
import { useDashboard } from '@/composables/useDashboard'
import RoleDashboardShell from '@/components/dashboard/RoleDashboardShell.vue'
import DashboardStatsGroup from '@/components/dashboard/DashboardStatsGroup.vue'
import MiniBarChart from '@/components/dashboard/MiniBarChart.vue'
import ActivityTimeline from '@/components/dashboard/ActivityTimeline.vue'
import Card from '@/components/ui/Card.vue'

const { t } = useI18n()
const { summary, actions, status, errorMessage, load, localized } = useDashboard('admin')
</script>

<template>
  <RoleDashboardShell
    :status="status"
    :actions="actions"
    :error-message="errorMessage"
    :localized="localized"
    @retry="load"
  >
    <section class="role-dashboard__section" aria-labelledby="admin-summary">
      <h2 id="admin-summary" class="role-dashboard__section-title">
        {{ t('dashboard.admin.summary.title') }}
      </h2>
      <DashboardStatsGroup :items="summary.summary" />
    </section>

    <section class="role-dashboard__section" aria-labelledby="admin-assessments">
      <h2 id="admin-assessments" class="role-dashboard__section-title">
        {{ t('dashboard.admin.assessments.title') }}
      </h2>
      <DashboardStatsGroup :items="summary.assessments" />
    </section>

    <section class="role-dashboard__section" aria-labelledby="admin-payments">
      <h2 id="admin-payments" class="role-dashboard__section-title">
        {{ t('dashboard.admin.payments.title') }}
      </h2>
      <DashboardStatsGroup :items="summary.payments" />
    </section>

    <div class="role-dashboard__two-col">
      <Card :title="t('dashboard.admin.distribution.byRegion')">
        <MiniBarChart :series="summary.distribution.byRegion" :localized="localized" color="primary" />
      </Card>
      <Card :title="t('dashboard.admin.distribution.byGovernance')">
        <MiniBarChart :series="summary.distribution.byGovernance" :localized="localized" color="info" />
      </Card>
    </div>

    <div class="role-dashboard__two-col">
      <Card :title="t('dashboard.admin.lowestCriteria')">
        <MiniBarChart
          :series="summary.lowestCriteria"
          :localized="localized"
          color="danger"
          unit="%"
        />
      </Card>
      <Card :title="t('dashboard.admin.lenses')">
        <MiniBarChart
          :series="summary.lenses"
          :localized="localized"
          color="success"
          :unit="t('dashboard.admin.lensUnit')"
        />
      </Card>
    </div>

    <Card :title="t('dashboard.admin.activity')">
      <ActivityTimeline :items="summary.activity" :localized="localized" />
    </Card>
  </RoleDashboardShell>
</template>
