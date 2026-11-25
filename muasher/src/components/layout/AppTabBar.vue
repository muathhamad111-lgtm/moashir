<script setup>
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { MORE_TAB_ID, useAppTabs } from '@/composables/useAppTabs'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const { tabItems, activeTabId } = useAppTabs()

const isActive = (id) => activeTabId.value === id
const tabClass = (id) => ['mobile-tab-bar__tab', { 'is-active': isActive(id) }]
</script>

<!-- The back office's bottom tab bar on phones (AppLayout renders it below md; the sidebar shows from md up). -->
<template>
  <nav class="mobile-tab-bar mobile-tab-bar--app" :aria-label="t('shell.tabBarLabel')">
    <ul class="mobile-tab-bar__list">
      <li v-for="item in tabItems" :key="item.id" class="mobile-tab-bar__entry">
        <router-link
          :class="tabClass(item.id)"
          :to="{ name: item.routeName }"
          :aria-current="isActive(item.id) ? 'page' : undefined"
          :data-tab="item.id"
        >
          <span class="mobile-tab-bar__pill">
            <AppIcon :name="item.icon" />
            <span v-if="item.count" class="mobile-tab-bar__count" aria-hidden="true">
              <bdi>{{ item.count }}</bdi>
            </span>
          </span>
          <span class="mobile-tab-bar__label">{{ t(item.tabLabelKey) }}</span>
          <span v-if="item.count" class="mobile-tab-bar__sr">
            — {{ t('nav.pendingCount', { count: item.count }) }}
          </span>
        </router-link>
      </li>
      <li class="mobile-tab-bar__entry">
        <router-link
          :class="tabClass(MORE_TAB_ID)"
          :to="{ name: ROUTE_NAMES.APP_MORE }"
          :aria-current="isActive(MORE_TAB_ID) ? 'page' : undefined"
          :data-tab="MORE_TAB_ID"
        >
          <span class="mobile-tab-bar__pill">
            <AppIcon name="dots-horizontal" />
          </span>
          <span class="mobile-tab-bar__label">{{ t('nav.tabs.more') }}</span>
        </router-link>
      </li>
    </ul>
  </nav>
</template>
