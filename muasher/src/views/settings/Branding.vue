<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { IDENTITY_TABS, identityTabsFor } from '@/constants/identityTabs'
import { usePermissions } from '@/composables/usePermissions'
import Tabs from '@/components/ui/Tabs.vue'
import BrandingEditor from '@/components/branding/BrandingEditor.vue'
import IdentityGeneralSettings from '@/components/settings/IdentityGeneralSettings.vue'
import IdentityMenusSettings from '@/components/settings/IdentityMenusSettings.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()

const available = computed(() => identityTabsFor(can))
const tabs = computed(() =>
  available.value.map((value) => ({ value, label: t(`identity.tabs.${value}`) })),
)
const activeTab = computed({
  get: () => (available.value.includes(route.query.tab) ? route.query.tab : available.value[0]),
  set: (tab) => router.replace({ query: { ...route.query, tab } }),
})
</script>

<template>
  <section class="branding-page">
    <header class="page-header">
      <RouterLink :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS }" class="branding-page__back">
        <AppIcon name="arrow-right" />
        {{ t('settings.hub.backToHub') }}
      </RouterLink>
      <h1 class="page-header__title">{{ t('branding.title') }}</h1>
      <p class="page-header__subtitle">{{ t('branding.subtitle') }}</p>
    </header>

    <Tabs v-if="tabs.length" v-model="activeTab" :tabs="tabs" class="branding-page__tabs">
      <IdentityGeneralSettings v-if="activeTab === IDENTITY_TABS.general" />
      <BrandingEditor v-else-if="activeTab === IDENTITY_TABS.visual" />
      <IdentityMenusSettings v-else />
    </Tabs>
  </section>
</template>
