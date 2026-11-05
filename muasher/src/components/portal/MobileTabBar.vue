<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { PORTAL_AREAS, PORTAL_TAB_ITEMS } from '@/constants/portal'
import { portalAreaOf } from '@/utils/portalAudience'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const route = useRoute()

const activeArea = computed(() => {
  const area = portalAreaOf(route.name)
  return [PORTAL_AREAS.PAYMENTS, PORTAL_AREAS.MESSAGES].includes(area) ? PORTAL_AREAS.MORE : area
})
</script>

<template>
  <nav class="mobile-tab-bar" :aria-label="t('portal.nav.mobileLabel')">
    <ul class="mobile-tab-bar__list">
      <li v-for="item in PORTAL_TAB_ITEMS" :key="item.area" class="mobile-tab-bar__entry">
        <router-link
          class="mobile-tab-bar__tab"
          :class="{ 'is-active': activeArea === item.area }"
          :to="{ name: item.routeName }"
          :aria-current="activeArea === item.area ? 'page' : undefined"
        >
          <span class="mobile-tab-bar__pill">
            <AppIcon :name="item.icon" />
          </span>
          <span class="mobile-tab-bar__label">{{ t(item.labelKey) }}</span>
        </router-link>
      </li>
    </ul>
  </nav>
</template>
