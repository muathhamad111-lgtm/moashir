<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import StaffDashboard from '@/components/dashboard/StaffDashboard.vue'
import ExecutiveOverview from '@/components/dashboard/overview/ExecutiveOverview.vue'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { SYSTEM_ADMIN_BOARD } from '@/constants/dashboardBoards'
import JourneyHome from '@/components/portal/home/JourneyHome.vue'
import { isPortalAudience } from '@/utils/portalAudience'
import { localizedText } from '@/utils/localizedText'

const { t, locale } = useI18n()
const auth = useAuthStore()
const { can } = usePermissions()

const isPortal = computed(() => isPortalAudience(auth.roles))
const showsOverview = computed(() => can(PERMISSIONS.DASHBOARD_EXECUTIVE_VIEW))
// The overview carries the platform-wide figures of the System Administrator board.
const coveredBoards = computed(() => (showsOverview.value ? [SYSTEM_ADMIN_BOARD] : []))
const greeting = computed(() => {
  const name = localizedText(auth.user?.name, locale.value)
  return name ? t('dashboard.greeting', { name }) : t('dashboard.greetingAnonymous')
})
</script>

<template>
  <JourneyHome v-if="isPortal" />
  <section v-else class="dashboard">
    <header class="dashboard__header">
      <p class="dashboard__eyebrow">{{ t('dashboard.eyebrow') }}</p>
      <h1 class="dashboard__title">{{ greeting }}</h1>
    </header>
    <StaffDashboard v-if="auth.isAuthenticated" :covered-boards="coveredBoards">
      <ExecutiveOverview v-if="showsOverview" />
    </StaffDashboard>
  </section>
</template>
