<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { withLatinNumerals } from '@/utils/numerals'
import { useDashboard } from '@/composables/useDashboard'
import RoleDashboardShell from '@/components/dashboard/RoleDashboardShell.vue'
import DashboardStatsGroup from '@/components/dashboard/DashboardStatsGroup.vue'
import ProgressGauge from '@/components/dashboard/ProgressGauge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Card from '@/components/ui/Card.vue'

const { t, n, locale } = useI18n()
const { summary, actions, status, errorMessage, load, localized } = useDashboard('committee-assessor')

const columns = computed(() => [
  { key: 'entity', label: t('dashboard.committeeAssessor.tasks.entity') },
  { key: 'status', label: t('dashboard.committeeAssessor.tasks.status') },
  { key: 'at', label: t('dashboard.committeeAssessor.tasks.date') },
])

function taskDate(at) {
  if (!at) return ''
  const lang = withLatinNumerals(locale.value === 'ar' ? 'ar-SA' : 'en-GB')
  return new Intl.DateTimeFormat(lang, { dateStyle: 'medium' }).format(new Date(at))
}

function cycleDate(at) {
  if (!at) return ''
  const lang = withLatinNumerals(locale.value === 'ar' ? 'ar-SA' : 'en-GB')
  return new Intl.DateTimeFormat(lang, { dateStyle: 'long' }).format(new Date(at))
}
</script>

<template>
  <RoleDashboardShell
    :status="status"
    :actions="actions"
    :error-message="errorMessage"
    :localized="localized"
    @retry="load"
  >
    <section class="role-dashboard__section" aria-labelledby="committee-assessor-stats">
      <h2 id="committee-assessor-stats" class="role-dashboard__section-title">
        {{ t('dashboard.committeeAssessor.stats.title') }}
      </h2>
      <DashboardStatsGroup :items="summary.stats" />
    </section>

    <div class="role-dashboard__two-col">
      <Card :title="t('dashboard.committeeAssessor.progress')">
        <div class="role-dashboard__gauge-wrap">
          <ProgressGauge
            :value="summary.progress"
            :label="t('dashboard.committeeAssessor.progress')"
            color="primary"
          />
          <dl class="role-dashboard__facts">
            <div class="role-dashboard__fact">
              <dt class="role-dashboard__fact-label">{{ t('dashboard.committeeAssessor.averageScore') }}</dt>
              <dd class="role-dashboard__fact-value">{{ n(summary.averageScore, 'default') }}%</dd>
            </div>
            <div class="role-dashboard__fact">
              <dt class="role-dashboard__fact-label">{{ t('dashboard.committeeAssessor.cycleEnds') }}</dt>
              <dd class="role-dashboard__fact-value">{{ cycleDate(summary.cycleEndsAt) }}</dd>
            </div>
          </dl>
        </div>
      </Card>
      <Card :title="t('dashboard.committeeAssessor.latestTasks')">
        <DataTable :columns="columns" :items="summary.latestTasks" status="success" row-key="id">
          <template #cell-entity="{ item }">{{ localized(item.entity) }}</template>
          <template #cell-status="{ item }"><StatusBadge :status="item.status" /></template>
          <template #cell-at="{ item }">{{ taskDate(item.at) }}</template>
        </DataTable>
      </Card>
    </div>
  </RoleDashboardShell>
</template>
