<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePermissions } from '@/composables/usePermissions'
import { accessFor } from '@/constants/routeAccess'
import { ROUTE_NAMES } from '@/constants/routeNames'

const LINKS = [
  { key: 'overview', name: ROUTE_NAMES.FINANCE_OVERVIEW, icon: 'fa-solid fa-chart-pie' },
  {
    key: 'transactions',
    name: ROUTE_NAMES.FINANCE_TRANSACTIONS,
    icon: 'fa-solid fa-money-bill-transfer',
  },
  { key: 'refunds', name: ROUTE_NAMES.FINANCE_REFUNDS, icon: 'fa-solid fa-rotate-left' },
  { key: 'paymentEvents', name: ROUTE_NAMES.FINANCE_PAYMENT_EVENTS, icon: 'fa-solid fa-list-ul' },
  {
    key: 'abandonedCarts',
    name: ROUTE_NAMES.ADMIN_SETTINGS_ABANDONED_CARTS,
    icon: 'fa-solid fa-cart-arrow-down',
  },
  { key: 'settings', name: ROUTE_NAMES.ADMIN_SETTINGS_FINANCE, icon: 'fa-solid fa-coins' },
]

const { t } = useI18n()
const { canAccess } = usePermissions()
const links = computed(() => LINKS.filter((link) => canAccess(accessFor(link.name))))
</script>

<template>
  <nav v-if="links.length > 1" class="finance-nav" :aria-label="t('finance.nav.label')">
    <RouterLink
      v-for="link in links"
      :key="link.key"
      :to="{ name: link.name }"
      class="finance-nav__link"
      exact-active-class="finance-nav__link--active"
    >
      <i :class="link.icon" aria-hidden="true"></i>
      <span>{{ t(`finance.nav.${link.key}`) }}</span>
    </RouterLink>
  </nav>
</template>
