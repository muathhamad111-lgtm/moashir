<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { useAppTabs } from '@/composables/useAppTabs'
import { useAppBackTarget } from '@/composables/useAppBackTarget'
import { useBackOfficeLogo } from '@/composables/useBackOfficeLogo'
import AppIcon from '@/components/ui/AppIcon.vue'
import PhoneNavDrawer from './PhoneNavDrawer.vue'
import Sidebar from './Sidebar.vue'

const { t } = useI18n()
const route = useRoute()
const { isTabRoot, owningTabRoute } = useAppTabs()
const { goBack } = useAppBackTarget(owningTabRoute)
const { topBarLogo } = useBackOfficeLogo()

const title = computed(() => (route.meta.titleKey ? t(route.meta.titleKey) : ''))
</script>

<!--
  The start of the top bar on phones (TopNavbar renders it below md): the navigation drawer button, then
  the logo on a tab's own screen, else a back button and the screen's title. Every routed page names itself in its own h1, so the title
  here is visual only (aria-hidden) and is not read twice.
-->
<template>
  <div class="navbar__phone" data-testid="phone-top-bar">
    <PhoneNavDrawer>
      <Sidebar variant="drawer" />
    </PhoneNavDrawer>
    <router-link
      v-if="isTabRoot"
      class="navbar__phone-brand"
      :to="{ name: ROUTE_NAMES.APP_DASHBOARD }"
    >
      <img class="navbar__phone-logo" :src="topBarLogo" :alt="t('shell.brandHome')" />
    </router-link>
    <template v-else>
      <button
        type="button"
        class="btn btn--ghost btn--icon navbar__back"
        :aria-label="t('shell.back')"
        data-testid="phone-back"
        @click="goBack"
      >
        <AppIcon name="arrow-left" />
      </button>
      <span class="navbar__phone-title" aria-hidden="true">{{ title }}</span>
    </template>
  </div>
</template>
