<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import VerificationSettingsCard from '@/components/settings/general/VerificationSettingsCard.vue'
import TaxSettingsCard from '@/components/settings/general/TaxSettingsCard.vue'
import ContactRetentionCard from '@/components/settings/general/ContactRetentionCard.vue'
import AssignmentReasonsCard from '@/components/settings/general/AssignmentReasonsCard.vue'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'

const TAX_PERMISSIONS = [
  PERMISSIONS.SETTINGS_ASSESSMENT_MANAGE,
  PERMISSIONS.BAG_ADMIN_MANAGE,
  PERMISSIONS.SETTINGS_PAYMENT_MANAGE,
]

const { t } = useI18n()
const { can } = usePermissions()

const showsVerification = computed(() => can(PERMISSIONS.SETTINGS_AUTH_MANAGE))
const showsTax = computed(() => TAX_PERMISSIONS.some((permission) => can(permission)))
const showsContactRetention = computed(() => can(PERMISSIONS.SETTINGS_GENERAL_MANAGE))
const showsAssignmentReasons = computed(() => can(PERMISSIONS.SETTINGS_ASSESSMENT_MANAGE))
</script>

<template>
  <SettingsSection :title="t('settings.general.title')">
    <div class="general-settings">
      <VerificationSettingsCard v-if="showsVerification" />
      <TaxSettingsCard v-if="showsTax" />
      <ContactRetentionCard v-if="showsContactRetention" />
      <AssignmentReasonsCard v-if="showsAssignmentReasons" />
    </div>
  </SettingsSection>
</template>
