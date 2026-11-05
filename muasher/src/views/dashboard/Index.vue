<script setup>
import { computed, provide } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import {
  heldDashboardKeys,
  resolveDashboardKey,
  resolvePrimaryRole,
} from '@/constants/dashboardRoles'
import { useAggregatedActions } from '@/composables/useAggregatedActions'
import { ADDITIONAL_ACTIONS_KEY } from '@/constants/dashboardInjection'
import AdminDashboard from '@/components/dashboard/roles/AdminDashboard.vue'
import FollowerDashboard from '@/components/dashboard/roles/FollowerDashboard.vue'
import AssessorDashboard from '@/components/dashboard/roles/AssessorDashboard.vue'
import CommitteeAssessorDashboard from '@/components/dashboard/roles/CommitteeAssessorDashboard.vue'
import SecretaryDashboard from '@/components/dashboard/roles/SecretaryDashboard.vue'
import EntityDashboard from '@/components/dashboard/roles/EntityDashboard.vue'
import FinanceDashboard from '@/components/dashboard/roles/FinanceDashboard.vue'
import ContentDashboard from '@/components/dashboard/roles/ContentDashboard.vue'

const REGISTRY = {
  admin: AdminDashboard,
  follower: FollowerDashboard,
  assessor: AssessorDashboard,
  'committee-assessor': CommitteeAssessorDashboard,
  secretary: SecretaryDashboard,
  entity: EntityDashboard,
  finance: FinanceDashboard,
  'content-officer': ContentDashboard,
}

const { t } = useI18n()
const auth = useAuthStore()

const boardOptions = computed(() => ({
  hasCommitteeAppointment: auth.committeeAppointments.length > 0,
}))
const dashboardKey = computed(() => resolveDashboardKey(auth.roles, boardOptions.value))
const primaryRole = computed(() => resolvePrimaryRole(auth.roles))
const activeDashboard = computed(() => REGISTRY[dashboardKey.value] ?? EntityDashboard)
const additionalKeys = computed(() =>
  heldDashboardKeys(auth.roles, boardOptions.value).filter((key) => key !== dashboardKey.value),
)
const { actions: additionalActions } = useAggregatedActions(additionalKeys)

provide(ADDITIONAL_ACTIONS_KEY, additionalActions)

function localizedName(name) {
  if (!name) return ''
  if (typeof name === 'string') return name
  return name.ar || name.en || ''
}

const greetingName = computed(
  () => localizedName(auth.user?.name) || t('dashboard.greetingFallback'),
)
const roleLabel = computed(() => t(`users.roleLabels.${primaryRole.value}`))
</script>

<template>
  <section class="dashboard">
    <header class="dashboard__header">
      <p class="dashboard__eyebrow">{{ roleLabel }}</p>
      <h1 class="dashboard__title">{{ t('dashboard.greeting', { name: greetingName }) }}</h1>
      <p class="dashboard__subtitle">{{ t('dashboard.roleSubtitle') }}</p>
    </header>

    <component :is="activeDashboard" v-if="auth.isAuthenticated" />
  </section>
</template>
