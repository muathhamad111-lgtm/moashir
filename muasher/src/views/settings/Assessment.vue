<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import Tabs from '@/components/ui/Tabs.vue'
import AssessmentGeneralSettings from '@/components/settings/AssessmentGeneralSettings.vue'
import PathSettingsPanel from '@/components/settings/PathSettingsPanel.vue'
import { usePortalTab } from '@/composables/usePortalTab'
import { REVIEWED_PATHS } from '@/constants/paths'

const GENERAL = 'general'
const TAB_VALUES = [...REVIEWED_PATHS, GENERAL]

const { t } = useI18n()
const { tab } = usePortalTab(TAB_VALUES)

const tabs = computed(() =>
  TAB_VALUES.map((value) => ({
    value,
    label: value === GENERAL ? t('settings.paths.generalTab') : t(`paths.labels.${value}`),
  })),
)
</script>

<template>
  <SettingsSection
    :title="t('settings.assessment.title')"
    :description="t('settings.assessment.description')"
  >
    <Tabs v-model="tab" :tabs="tabs">
      <AssessmentGeneralSettings v-if="tab === GENERAL" />
      <PathSettingsPanel v-else :key="tab" :path="tab" />
    </Tabs>
  </SettingsSection>
</template>
