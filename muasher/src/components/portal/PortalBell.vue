<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const { unreadNotifications } = storeToRefs(usePortalCountsStore())
const label = computed(() =>
  t('portal.bell.label', { count: unreadNotifications.value }, unreadNotifications.value),
)
</script>

<template>
  <router-link
    class="portal-bell"
    :to="{ name: ROUTE_NAMES.NOTIFICATIONS_INDEX }"
    :aria-label="label"
    :title="label"
  >
    <AppIcon name="bell" />
    <span v-if="unreadNotifications > 0" class="portal-bell__dot" aria-hidden="true"></span>
  </router-link>
</template>
