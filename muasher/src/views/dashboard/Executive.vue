<script setup>
import { useI18n } from 'vue-i18n'
import StaffDashboard from '@/components/dashboard/StaffDashboard.vue'
import { EXECUTIVE_BOARD } from '@/constants/dashboardBoards'

const { t } = useI18n()
</script>

<template>
  <section class="dashboard">
    <header class="dashboard__header">
      <h1 class="dashboard__title">{{ t('nav.executiveDashboard') }}</h1>
    </header>
    <StaffDashboard :fixed-board="EXECUTIVE_BOARD" />
  </section>
</template>
