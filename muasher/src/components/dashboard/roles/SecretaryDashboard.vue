<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDashboard } from '@/composables/useDashboard'
import RoleDashboardShell from '@/components/dashboard/RoleDashboardShell.vue'
import DashboardStatsGroup from '@/components/dashboard/DashboardStatsGroup.vue'
import ProgressGauge from '@/components/dashboard/ProgressGauge.vue'
import MiniBarChart from '@/components/dashboard/MiniBarChart.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Card from '@/components/ui/Card.vue'

const { t, n } = useI18n()
const { summary, actions, status, errorMessage, load, localized } = useDashboard('secretary')

const columns = computed(() => [
  { key: 'entity', label: t('dashboard.secretary.queue.entity') },
  { key: 'value', label: t('dashboard.secretary.queue.score') },
  { key: 'status', label: t('dashboard.secretary.queue.status') },
])
</script>

<template>
  <RoleDashboardShell
    :status="status"
    :actions="actions"
    :error-message="errorMessage"
    :localized="localized"
    @retry="load"
  >
    <section class="role-dashboard__section" aria-labelledby="secretary-summary">
      <h2 id="secretary-summary" class="role-dashboard__section-title">
        {{ t('dashboard.secretary.summary.title') }}
      </h2>
      <DashboardStatsGroup :items="summary.summary" />
    </section>

    <div class="role-dashboard__two-col">
      <Card :title="t('dashboard.secretary.committeeProgress')">
        <div class="role-dashboard__gauge-wrap">
          <ProgressGauge
            :value="summary.committeeProgress"
            :label="t('dashboard.secretary.committeeProgress')"
            color="primary"
          />
        </div>
      </Card>
      <Card :title="t('dashboard.secretary.topEntities')">
        <MiniBarChart
          :series="summary.topEntities"
          :localized="localized"
          color="success"
          unit="%"
        />
      </Card>
    </div>

    <div class="role-dashboard__two-col">
      <Card :title="t('dashboard.secretary.scoreDistribution')">
        <MiniBarChart :series="summary.scoreDistribution" :localized="localized" color="info" />
      </Card>
      <Card :title="t('dashboard.secretary.byRegion')">
        <MiniBarChart :series="summary.byRegion" :localized="localized" color="primary" />
      </Card>
    </div>

    <Card :title="t('dashboard.secretary.approvalQueue')">
      <DataTable :columns="columns" :items="summary.approvalQueue" status="success" row-key="id">
        <template #cell-entity="{ item }">{{ localized(item.entity) }}</template>
        <template #cell-value="{ item }">{{ n(item.value, 'default') }}%</template>
        <template #cell-status="{ item }"><StatusBadge :status="item.status" /></template>
      </DataTable>
    </Card>
  </RoleDashboardShell>
</template>
