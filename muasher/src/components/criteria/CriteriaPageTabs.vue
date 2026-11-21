<script setup>
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'

// The two pages of criteria settings (design §A.3.1): the criteria themselves and their versions.
const { t } = useI18n()

const LINKS = [
  { name: ROUTE_NAMES.ADMIN_CRITERIA, key: 'criteria' },
  { name: ROUTE_NAMES.ADMIN_CRITERIA_VERSIONS, key: 'versions' },
]
</script>

<template>
  <nav class="tabs__list criteria-page-tabs" :aria-label="t('criteria.pageTabs.label')">
    <RouterLink
      v-for="link in LINKS"
      :key="link.key"
      :to="{ name: link.name }"
      class="tabs__tab"
      exact-active-class="is-active"
    >
      {{ t(`criteria.pageTabs.${link.key}`) }}
    </RouterLink>
  </nav>
</template>
