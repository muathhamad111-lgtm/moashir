<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import Tabs from '@/components/ui/Tabs.vue'
import AssessmentGeneralSettings from '@/components/settings/AssessmentGeneralSettings.vue'
import PathSettingsPanel from '@/components/settings/PathSettingsPanel.vue'
import InsightsSettingsCard from '@/components/settings/InsightsSettingsCard.vue'
import CertificateSettingsCard from '@/components/settings/paths/CertificateSettingsCard.vue'
import PathPriceCard from '@/components/settings/paths/PathPriceCard.vue'
import { usePortalTab } from '@/composables/usePortalTab'
import { usePathNames } from '@/composables/usePathNames'
import { usePermissions } from '@/composables/usePermissions'
import { KIT_PRICE_KEY, MANAGED_PATHS } from '@/constants/paths'
import { EXCELLENCE_TOOLS_SETTINGS_ENABLED } from '@/constants/insights'
import { PERMISSIONS } from '@/constants/permissionKeys'

const GENERAL = 'general'
const INSIGHTS = 'insights'
// The excellence-tools tab follows the settings switch; a link to it while hidden opens the first tab.
const TAB_VALUES = [
  ...MANAGED_PATHS,
  GENERAL,
  ...(EXCELLENCE_TOOLS_SETTINGS_ENABLED ? [INSIGHTS] : []),
]
const TAB_LABELS = { [GENERAL]: 'settings.paths.generalTab', [INSIGHTS]: 'settings.insights.tab' }

const { t } = useI18n()
const { pathName } = usePathNames()
const { tab } = usePortalTab(TAB_VALUES)
const { can } = usePermissions()
const managesPaths = can(PERMISSIONS.SETTINGS_ASSESSMENT_MANAGE)
const managesPrices = can(PERMISSIONS.SETTINGS_PRICES_MANAGE)
const managesCertificates = can(PERMISSIONS.SETTINGS_CERTIFICATES_MANAGE)

const tabs = computed(() =>
  TAB_VALUES.map((value) => ({
    value,
    label: TAB_LABELS[value] ? t(TAB_LABELS[value]) : pathName(value),
  })),
)
</script>

<template>
  <SettingsSection :title="t('settings.paths.title')">
    <Tabs v-model="tab" :tabs="tabs">
      <div v-if="tab === GENERAL" class="path-settings">
        <AssessmentGeneralSettings v-if="managesPaths" />
        <CertificateSettingsCard v-if="managesCertificates" part="levels" />
        <PathPriceCard v-if="managesPrices" :price-key="KIT_PRICE_KEY" />
      </div>
      <InsightsSettingsCard v-else-if="tab === INSIGHTS" />
      <PathSettingsPanel v-else :key="tab" :path="tab" />
    </Tabs>
  </SettingsSection>
</template>
