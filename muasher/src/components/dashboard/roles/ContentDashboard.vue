<script setup>
import { useI18n } from 'vue-i18n'
import { useDashboard } from '@/composables/useDashboard'
import RoleDashboardShell from '@/components/dashboard/RoleDashboardShell.vue'
import DashboardStatsGroup from '@/components/dashboard/DashboardStatsGroup.vue'
import ActivityTimeline from '@/components/dashboard/ActivityTimeline.vue'
import Card from '@/components/ui/Card.vue'

const { t } = useI18n()
const { summary, actions, status, errorMessage, load, localized } = useDashboard('content-officer')
</script>

<template>
  <RoleDashboardShell
    :status="status"
    :actions="actions"
    :error-message="errorMessage"
    :localized="localized"
    @retry="load"
  >
    <section class="role-dashboard__section" aria-labelledby="content-summary">
      <h2 id="content-summary" class="role-dashboard__section-title">
        {{ t('dashboard.content.summary.title') }}
      </h2>
      <DashboardStatsGroup :items="summary.summary" />
    </section>

    <Card :title="t('dashboard.content.latestEdits')">
      <ActivityTimeline
        :items="summary.latestEdits.map((edit) => ({ ...edit, type: 'edit' }))"
        :localized="localized"
      />
    </Card>
  </RoleDashboardShell>
</template>
