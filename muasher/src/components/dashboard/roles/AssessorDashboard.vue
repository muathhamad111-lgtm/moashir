<script setup>
import { useI18n } from 'vue-i18n'
import { withLatinNumerals } from '@/utils/numerals'
import { useDashboard } from '@/composables/useDashboard'
import RoleDashboardShell from '@/components/dashboard/RoleDashboardShell.vue'
import DashboardStatsGroup from '@/components/dashboard/DashboardStatsGroup.vue'
import MiniBarChart from '@/components/dashboard/MiniBarChart.vue'
import Card from '@/components/ui/Card.vue'

const { t, n, locale } = useI18n()
const { summary, actions, status, errorMessage, load, localized } = useDashboard('assessor')

function noteDate(at) {
  if (!at) return ''
  const lang = withLatinNumerals(locale.value === 'ar' ? 'ar-SA' : 'en-GB')
  return new Intl.DateTimeFormat(lang, { dateStyle: 'medium' }).format(new Date(at))
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
    <section class="role-dashboard__section" aria-labelledby="assessor-tasks">
      <h2 id="assessor-tasks" class="role-dashboard__section-title">
        {{ t('dashboard.assessor.tasks.title') }}
      </h2>
      <DashboardStatsGroup :items="summary.tasks" />
    </section>

    <div class="role-dashboard__two-col">
      <Card :title="t('dashboard.assessor.averageDuration')">
        <div class="role-dashboard__metric">
          <span class="role-dashboard__metric-value">{{
            n(summary.averageDurationDays, 'default')
          }}</span>
          <span class="role-dashboard__metric-unit">{{ t('dashboard.assessor.days') }}</span>
        </div>
        <p class="role-dashboard__metric-note">{{ t('dashboard.assessor.averageDurationNote') }}</p>
      </Card>
      <Card :title="t('dashboard.assessor.gapCriteria')">
        <MiniBarChart :series="summary.gapCriteria" :localized="localized" color="warning" />
      </Card>
    </div>

    <Card :title="t('dashboard.assessor.latestNotes')">
      <ul class="role-dashboard__notes">
        <li v-for="note in summary.latestNotes" :key="note.id" class="role-dashboard__note">
          <div class="role-dashboard__note-head">
            <span class="role-dashboard__note-entity">{{ localized(note.entity) }}</span>
            <time class="role-dashboard__note-date" :datetime="note.at">{{
              noteDate(note.at)
            }}</time>
          </div>
          <p class="role-dashboard__note-text">{{ localized(note.note) }}</p>
        </li>
      </ul>
    </Card>
  </RoleDashboardShell>
</template>
