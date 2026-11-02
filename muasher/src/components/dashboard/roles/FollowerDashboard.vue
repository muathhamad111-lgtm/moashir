<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDashboard } from '@/composables/useDashboard'
import RoleDashboardShell from '@/components/dashboard/RoleDashboardShell.vue'
import DashboardStatsGroup from '@/components/dashboard/DashboardStatsGroup.vue'
import ProgressGauge from '@/components/dashboard/ProgressGauge.vue'
import MiniBarChart from '@/components/dashboard/MiniBarChart.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Card from '@/components/ui/Card.vue'

const { t, n } = useI18n()
const { summary, actions, status, errorMessage, load, localized } = useDashboard('follower')

const columns = computed(() => [
  { key: 'name', label: t('dashboard.follower.assessors.name') },
  { key: 'total', label: t('dashboard.follower.assessors.total') },
  { key: 'ongoing', label: t('dashboard.follower.assessors.ongoing') },
  { key: 'completed', label: t('dashboard.follower.assessors.completed') },
])

const topAssessors = computed(() =>
  [...(summary.value?.assessors ?? [])].sort((a, b) => b.completed - a.completed).slice(0, 5),
)
</script>

<template>
  <RoleDashboardShell
    :status="status"
    :actions="actions"
    :error-message="errorMessage"
    :localized="localized"
    @retry="load"
  >
    <section class="role-dashboard__section" aria-labelledby="follower-requests">
      <h2 id="follower-requests" class="role-dashboard__section-title">
        {{ t('dashboard.follower.requests.title') }}
      </h2>
      <DashboardStatsGroup :items="summary.requests" />
    </section>

    <div class="role-dashboard__two-col">
      <Card :title="t('dashboard.follower.completionRate')">
        <div class="role-dashboard__gauge-wrap">
          <ProgressGauge
            :value="summary.completionRate"
            :label="t('dashboard.follower.completionRate')"
            color="success"
          />
          <p class="role-dashboard__gauge-note">
            {{ t('dashboard.follower.overdueNote', { count: n(summary.overdueCount, 'default') }) }}
          </p>
        </div>
      </Card>
      <Card :title="t('dashboard.follower.byPath')">
        <MiniBarChart :series="summary.byPath" :localized="localized" color="primary" />
      </Card>
    </div>

    <Card :title="t('dashboard.follower.topAssessors')">
      <DataTable :columns="columns" :items="topAssessors" status="success" row-key="id">
        <template #cell-name="{ item }">{{ localized(item.name) }}</template>
        <template #cell-total="{ item }">{{ n(item.total, 'default') }}</template>
        <template #cell-ongoing="{ item }">{{ n(item.ongoing, 'default') }}</template>
        <template #cell-completed="{ item }">{{ n(item.completed, 'default') }}</template>
      </DataTable>
    </Card>
  </RoleDashboardShell>
</template>
