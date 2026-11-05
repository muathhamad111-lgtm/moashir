<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { PORTAL_NAV_ITEMS } from '@/constants/portal'
import { portalAreaOf } from '@/utils/portalAudience'
import { formatNumber } from '@/utils/formatNumber'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t, n } = useI18n()
const route = useRoute()
const counts = storeToRefs(usePortalCountsStore())
const activeArea = computed(() => portalAreaOf(route.name))

function badgeOf(item) {
  return item.badge ? (counts[item.badge]?.value ?? 0) : 0
}
</script>

<template>
  <nav class="portal-nav" :aria-label="t('portal.nav.label')">
    <ul class="portal-nav__list">
      <li v-for="item in PORTAL_NAV_ITEMS" :key="item.area">
        <router-link
          class="portal-nav__link"
          :class="{ 'is-active': activeArea === item.area }"
          :to="{ name: item.routeName }"
          :aria-current="activeArea === item.area ? 'page' : undefined"
        >
          <AppIcon :name="item.icon" class="portal-nav__icon" />
          <span>{{ t(item.labelKey) }}</span>
          <span v-if="badgeOf(item) > 0" class="portal-nav__badge">
            <span aria-hidden="true">{{ formatNumber(n, badgeOf(item)) }}</span>
            <span class="portal-nav__sr">{{
              t('portal.nav.unread', { count: badgeOf(item) }, badgeOf(item))
            }}</span>
          </span>
        </router-link>
      </li>
    </ul>
  </nav>
</template>
