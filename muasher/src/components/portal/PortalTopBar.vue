<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useRoute } from 'vue-router'
import { useActionCenterStore } from '@/stores/actionCenter'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { useBackOfficeLogo } from '@/composables/useBackOfficeLogo'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { portalPageTitleKey } from '@/utils/portalPageTitle'
import LocaleSwitcher from '@/components/ui/LocaleSwitcher.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import PortalBell from './PortalBell.vue'
import AccountMenu from './AccountMenu.vue'
import PortalSideNav from './PortalSideNav.vue'
import PhoneNavDrawer from '@/components/layout/PhoneNavDrawer.vue'
import { useCompactViewport } from '@/composables/useCompactViewport'

const { t, locale } = useI18n()
const route = useRoute()
const props = defineProps({
  navCollapsed: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle-nav'])
const { topBarLogo } = useBackOfficeLogo()
// Phones: every portal section in a drawer opened from the bar's start (the side nav shows from lg).
const isPhone = useCompactViewport()
const toggleLabel = computed(() =>
  props.navCollapsed ? t('portal.sideNav.expand') : t('portal.sideNav.collapse'),
)
const { generatedAt } = storeToRefs(useActionCenterStore())
const titleKey = computed(() => portalPageTitleKey(route))
const updatedText = computed(() =>
  generatedAt.value ? formatRiyadhDateTime(generatedAt.value, locale.value) : '',
)
</script>

<template>
  <header class="portal-top-bar">
    <div class="portal-top-bar__inner">
      <PhoneNavDrawer v-if="isPhone">
        <PortalSideNav variant="drawer" />
      </PhoneNavDrawer>
      <router-link class="portal-top-bar__brand" :to="{ name: ROUTE_NAMES.APP_DASHBOARD }">
        <img class="portal-top-bar__logo" :src="topBarLogo" :alt="t('portal.brandHome')" />
      </router-link>
      <button
        type="button"
        class="btn btn--ghost btn--icon portal-top-bar__collapse"
        :aria-expanded="!navCollapsed"
        :aria-label="toggleLabel"
        :title="toggleLabel"
        data-testid="portal-nav-collapse"
        @click="emit('toggle-nav')"
      >
        <AppIcon name="menu" />
      </button>
      <div class="portal-top-bar__start">
        <p v-if="titleKey" class="portal-top-bar__area" data-testid="portal-page-title">
          {{ t(titleKey) }}
        </p>
        <p v-if="updatedText" class="portal-top-bar__updated" data-testid="portal-updated">
          {{ t('portal.topBar.updated') }}
          <bdi>{{ updatedText }}</bdi>
        </p>
      </div>
      <div class="portal-top-bar__end">
        <LocaleSwitcher class="portal-top-bar__tool" />
        <PortalBell />
        <AccountMenu class="portal-top-bar__account" />
      </div>
    </div>
  </header>
</template>
