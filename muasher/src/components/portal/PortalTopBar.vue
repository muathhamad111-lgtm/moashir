<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { useRoute } from 'vue-router'
import { useActionCenterStore } from '@/stores/actionCenter'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { portalPageTitleKey } from '@/utils/portalPageTitle'
import LocaleSwitcher from '@/components/ui/LocaleSwitcher.vue'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import PortalBell from './PortalBell.vue'
import AccountMenu from './AccountMenu.vue'

const { t, locale } = useI18n()
const route = useRoute()
const { themedLogo } = useBrandLogo()
const { generatedAt } = storeToRefs(useActionCenterStore())
const titleKey = computed(() => portalPageTitleKey(route))
const updatedText = computed(() =>
  generatedAt.value ? formatRiyadhDateTime(generatedAt.value, locale.value) : '',
)
</script>

<template>
  <header class="portal-top-bar">
    <div class="portal-top-bar__inner">
      <router-link class="portal-top-bar__brand" :to="{ name: ROUTE_NAMES.APP_DASHBOARD }">
        <img class="portal-top-bar__logo" :src="themedLogo" :alt="t('portal.brandHome')" />
      </router-link>
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
        <ThemeToggle class="portal-top-bar__tool" />
        <PortalBell />
        <AccountMenu class="portal-top-bar__account" />
      </div>
    </div>
  </header>
</template>
