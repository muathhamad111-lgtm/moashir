<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'
import ToolTeaser from '@/components/portal/ToolTeaser.vue'

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()

const toolKey = computed(() => route.meta.toolKey ?? '')
const feature = computed(() => (route.meta.titleKey ? t(route.meta.titleKey) : ''))
const backRoute = computed(() =>
  auth.isAuthenticated ? ROUTE_NAMES.APP_DASHBOARD : ROUTE_NAMES.PUBLIC_HOME,
)
const backLabel = computed(() =>
  auth.isAuthenticated ? t('comingSoon.backToDashboard') : t('comingSoon.backHome'),
)

useHead({ title: () => feature.value || t('comingSoon.title') })
</script>

<template>
  <ToolTeaser v-if="toolKey" :tool-key="toolKey" />
  <section v-else class="coming-soon" aria-labelledby="coming-soon-title">
    <div class="coming-soon__inner">
      <span class="coming-soon__icon" aria-hidden="true">
        <AppIcon name="calendar" />
      </span>
      <p class="coming-soon__badge">{{ t('comingSoon.badge') }}</p>
      <h1 id="coming-soon-title" class="coming-soon__title">
        {{ feature || t('comingSoon.title') }}
      </h1>
      <p class="coming-soon__message">{{ t('comingSoon.message') }}</p>
      <RouterLink class="coming-soon__back" :to="{ name: backRoute }">
        <AppIcon name="arrow-left" />
        {{ backLabel }}
      </RouterLink>
    </div>
  </section>
</template>
