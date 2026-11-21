<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import PathIdentityFields from '@/components/settings/paths/PathIdentityFields.vue'
import PathLimitsFields from '@/components/settings/PathLimitsFields.vue'
import PathEntityTextFields from '@/components/settings/PathEntityTextFields.vue'
import PathPriceCard from '@/components/settings/paths/PathPriceCard.vue'
import CertificateSettingsCard from '@/components/settings/paths/CertificateSettingsCard.vue'
import { usePathSettings } from '@/composables/usePathSettings'
import { usePathIdentityStore } from '@/stores/pathIdentity'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { PATH_PRICE_KEYS } from '@/constants/paths'

const props = defineProps({
  path: { type: String, required: true },
})

const { t } = useI18n()
const { can } = usePermissions()
const managesPath = can(PERMISSIONS.SETTINGS_ASSESSMENT_MANAGE)
const managesPrice = can(PERMISSIONS.SETTINGS_PRICES_MANAGE)
const managesCertificates = can(PERMISSIONS.SETTINGS_CERTIFICATES_MANAGE)
const identities = usePathIdentityStore()
const { status, errorMessage, settings, saving, reasonOpen, load, submit, confirmReason } =
  usePathSettings(props.path, {
    onSaved: (saved) => identities.replace(props.path, { description: saved.description }),
  })
const formId = `path-settings-${props.path}`
const reviewed = computed(() => props.path !== ASSESSMENT_PATHS.SELF)
const issuesCertificates = computed(() => props.path === ASSESSMENT_PATHS.ACCREDITED)

onMounted(() => {
  if (managesPath) load()
})
</script>

<template>
  <div class="path-settings">
    <template v-if="!managesPath" />
    <div v-else-if="status === 'loading'" class="path-settings__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>
    <ErrorState v-else-if="status !== 'success'" :message="errorMessage" @retry="load" />
    <form
      v-else-if="settings"
      :id="formId"
      class="settings-form"
      novalidate
      @submit.prevent="submit"
    >
      <PathIdentityFields />
      <template v-if="reviewed">
        <PathEntityTextFields />
        <PathLimitsFields :settings="settings" />
      </template>
      <div class="path-settings__actions">
        <BaseButton type="submit" variant="primary" :loading="saving" icon="save">{{
          t('actions.save')
        }}</BaseButton>
      </div>
    </form>
    <PathPriceCard v-if="managesPrice" :price-key="PATH_PRICE_KEYS[path]" />
    <CertificateSettingsCard v-if="issuesCertificates && managesCertificates" part="issuance" />
    <section
      v-else-if="!issuesCertificates"
      class="path-settings__card"
      :aria-label="t('settings.paths.certificatesTitle')"
    >
      <h2 class="path-settings__card-title">{{ t('settings.paths.certificatesTitle') }}</h2>
      <p class="path-settings__card-note">{{ t('settings.paths.noCertificates') }}</p>
    </section>
    <ReasonDialog
      v-model="reasonOpen"
      :title="t('settings.assessment.reasonTitle')"
      :submit-label="t('actions.save')"
      :action="confirmReason"
      :action-name="`settings.assessment.paths.${path}.update`"
    />
  </div>
</template>
