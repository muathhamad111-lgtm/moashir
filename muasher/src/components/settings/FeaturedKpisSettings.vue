<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import SiteSettingsForm from './SiteSettingsForm.vue'
import { SITE_SETTINGS_LIMITS } from '@/constants/cms'

const FIELDS = ['showFeaturedKpis', 'featuredKpiLimit']

const { t } = useI18n()

const limitOptions = computed(() =>
  Array.from(
    { length: SITE_SETTINGS_LIMITS.featuredMax - SITE_SETTINGS_LIMITS.featuredMin + 1 },
    (_, index) => {
      const value = SITE_SETTINGS_LIMITS.featuredMin + index
      return { value, label: String(value) }
    },
  ),
)
</script>

<template>
  <SiteSettingsForm
    v-slot="{ draft, errorOf }"
    heading-id="featured-kpis-heading"
    :title="t('settings.homepage.siteSettings.featured')"
    :description="t('settings.homepage.siteSettings.featuredDescription')"
    :fields="FIELDS"
  >
    <ToggleSwitch
      v-model="draft.showFeaturedKpis"
      name="showFeaturedKpis"
      :label="t('settings.homepage.siteSettings.showFeaturedKpis')"
      :description="t('settings.homepage.siteSettings.showFeaturedKpisHint')"
    />
    <AppSelect
      v-model="draft.featuredKpiLimit"
      name="featuredKpiLimit"
      :label="t('settings.homepage.siteSettings.featuredKpiLimit')"
      :hint="t('settings.homepage.siteSettings.featuredKpiLimitHint')"
      :options="limitOptions"
      :error="errorOf('featuredKpiLimit')"
    />
  </SiteSettingsForm>
</template>
