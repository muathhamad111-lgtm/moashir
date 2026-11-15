<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { CMS_PAGE_ORDER } from '@/constants/cms'
import Tabs from '@/components/ui/Tabs.vue'
import CmsPageEditor from '@/components/cms/CmsPageEditor.vue'
import FeaturedKpisSettings from '@/components/settings/FeaturedKpisSettings.vue'
import LegalVersionsPanel from '@/components/legal/admin/LegalVersionsPanel.vue'
import { LEGAL_VERSIONED_KINDS } from '@/constants/legal'
import AppIcon from '@/components/ui/AppIcon.vue'

const FEATURED_KPIS_TAB = 'featured_kpis'
const LEGAL_TAB_PREFIX = 'legal_'

const { t } = useI18n()
const activeTab = ref(CMS_PAGE_ORDER[0])

const tabs = computed(() => [
  ...CMS_PAGE_ORDER.map((key) => ({ value: key, label: t(`cms.pages.${key}`) })),
  ...LEGAL_VERSIONED_KINDS.map((kind) => ({
    value: `${LEGAL_TAB_PREFIX}${kind}`,
    label: t(`legalAdmin.kinds.${kind}`),
  })),
  { value: FEATURED_KPIS_TAB, label: t('cms.pages.featuredKpis') },
])
const legalKind = computed(() =>
  activeTab.value.startsWith(LEGAL_TAB_PREFIX)
    ? activeTab.value.slice(LEGAL_TAB_PREFIX.length)
    : null,
)
</script>

<template>
  <section class="homepage-cms">
    <header class="homepage-cms__header">
      <RouterLink :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS }" class="homepage-cms__back">
        <AppIcon name="arrow-left" />
        {{ t('settings.hub.backToHub') }}
      </RouterLink>
      <h1 class="homepage-cms__title">{{ t('settings.homepage.title') }}</h1>
      <p class="homepage-cms__desc">{{ t('settings.homepage.description') }}</p>
    </header>

    <Tabs v-model="activeTab" :tabs="tabs" class="homepage-cms__tabs">
      <FeaturedKpisSettings v-if="activeTab === FEATURED_KPIS_TAB" />
      <LegalVersionsPanel v-else-if="legalKind" :key="activeTab" :kind="legalKind" />
      <CmsPageEditor v-else :key="activeTab" :page-key="activeTab" />
    </Tabs>
  </section>
</template>
