<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Tabs from '@/components/ui/Tabs.vue'
import KpiIndicatorsPanel from '@/components/kpis/KpiIndicatorsPanel.vue'
import KpiCatalogPanel from '@/components/kpis/KpiCatalogPanel.vue'
import { useKpiCatalogAdmin } from '@/composables/useKpiCatalogAdmin'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

const TABS = ['indicators', 'categories', 'types']

const { t, locale } = useI18n()
const activeTab = ref(TABS[0])
const catalogs = {
  categories: useKpiCatalogAdmin('categories'),
  types: useKpiCatalogAdmin('types'),
}
const tabs = computed(() => TABS.map((value) => ({ value, label: t(`kpis.admin.tabs.${value}`) })))

function localized(value) {
  return localizedText(value, locale.value)
}

onMounted(() => Promise.all([catalogs.categories.load(), catalogs.types.load()]))
</script>

<template>
  <section class="kpis-admin">
    <header class="kpis-admin__header">
      <div>
        <h1 class="kpis-admin__title">{{ t('kpis.admin.index.title') }}</h1>
        <p class="kpis-admin__subtitle">{{ t('kpis.admin.index.subtitle') }}</p>
      </div>
      <RouterLink
        class="kpis-admin__public-link"
        :to="{ name: ROUTE_NAMES.PUBLIC_KPIS }"
        target="_blank"
        rel="noopener"
      >
        <AppIcon name="link" />
        {{ t('kpis.admin.index.viewPublic') }}
      </RouterLink>
    </header>
    <Tabs v-model="activeTab" :tabs="tabs" class="kpis-admin__tabs">
      <KpiIndicatorsPanel
        v-if="activeTab === 'indicators'"
        :categories="catalogs.categories.entries.value"
        :types="catalogs.types.entries.value"
        :localized="localized"
      />
      <KpiCatalogPanel
        v-else
        :key="activeTab"
        :kind="activeTab"
        :catalog="catalogs[activeTab]"
        :localized="localized"
      />
    </Tabs>
  </section>
</template>
