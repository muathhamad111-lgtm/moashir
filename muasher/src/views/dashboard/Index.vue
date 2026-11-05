<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import StaffDashboard from '@/components/dashboard/StaffDashboard.vue'
import JourneyHome from '@/components/portal/home/JourneyHome.vue'
import { isPortalAudience } from '@/utils/portalAudience'
import { localizedText } from '@/utils/localizedText'

const { t, locale } = useI18n()
const auth = useAuthStore()

const isPortal = computed(() => isPortalAudience(auth.roles))
const greetingName = computed(
  () => localizedText(auth.user?.name, locale.value) || t('dashboard.greetingFallback'),
)
</script>

<template>
  <JourneyHome v-if="isPortal" />
  <section v-else class="dashboard">
    <header class="dashboard__header">
      <p class="dashboard__eyebrow">{{ t('dashboard.eyebrow') }}</p>
      <h1 class="dashboard__title">{{ t('dashboard.greeting', { name: greetingName }) }}</h1>
      <p class="dashboard__subtitle">{{ t('dashboard.roleSubtitle') }}</p>
    </header>
    <StaffDashboard v-if="auth.isAuthenticated" />
  </section>
</template>
