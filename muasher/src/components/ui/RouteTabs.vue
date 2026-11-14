<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { usePermissions } from '@/composables/usePermissions'
import { accessFor } from '@/constants/routeAccess'

const props = defineProps({
  tabs: { type: Array, required: true },
  label: { type: String, required: true },
})

const { t } = useI18n()
const route = useRoute()
const { canAccess } = usePermissions()

const visibleTabs = computed(() => props.tabs.filter((tab) => canAccess(accessFor(tab.routeName))))
</script>

<template>
  <nav v-if="visibleTabs.length > 1" class="route-tabs" :aria-label="label">
    <ul class="route-tabs__list">
      <li v-for="tab in visibleTabs" :key="tab.routeName">
        <RouterLink
          class="route-tabs__link"
          :class="{ 'route-tabs__link--current': route.name === tab.routeName }"
          :aria-current="route.name === tab.routeName ? 'page' : undefined"
          :to="{ name: tab.routeName }"
          >{{ t(tab.labelKey) }}</RouterLink
        >
      </li>
    </ul>
  </nav>
</template>
