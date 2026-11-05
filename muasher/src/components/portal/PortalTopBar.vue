<script setup>
import { useI18n } from 'vue-i18n'
import { useBrandLogo } from '@/composables/useBrandLogo'
import { usePortalIdentity } from '@/composables/usePortalIdentity'
import { ROUTE_NAMES } from '@/constants/routeNames'
import PortalNavLinks from './PortalNavLinks.vue'
import PortalBell from './PortalBell.vue'
import AccountMenu from './AccountMenu.vue'

const { t } = useI18n()
const { themedLogo } = useBrandLogo()
const identity = usePortalIdentity()
</script>

<template>
  <header class="portal-top-bar">
    <div class="portal-top-bar__inner">
      <div class="portal-top-bar__start">
        <router-link class="portal-top-bar__brand" :to="{ name: ROUTE_NAMES.APP_DASHBOARD }">
          <img class="portal-top-bar__logo" :src="themedLogo" :alt="t('portal.brandHome')" />
        </router-link>
        <PortalNavLinks class="portal-top-bar__nav" />
      </div>
      <div class="portal-top-bar__end">
        <PortalBell />
        <AccountMenu class="portal-top-bar__account" />
        <router-link
          class="portal-top-bar__avatar"
          :to="{ name: ROUTE_NAMES.PORTAL_MORE }"
          :aria-label="t('portal.account.openMore', { name: identity.entityName.value })"
        >
          <span aria-hidden="true">{{ identity.initial.value }}</span>
        </router-link>
      </div>
    </div>
  </header>
</template>
