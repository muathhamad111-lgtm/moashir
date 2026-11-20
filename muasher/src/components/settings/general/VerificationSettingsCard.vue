<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SignInCodeChannels from '@/components/settings/SignInCodeChannels.vue'
import TwoFactorPolicyFields from './TwoFactorPolicyFields.vue'
import NationalIdPolicyFields from './NationalIdPolicyFields.vue'
import SettingsCardState from './SettingsCardState.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import { useAuthSettings } from '@/composables/useAuthSettings'
import FieldToggle from '@/components/form/FieldToggle.vue'

const { t } = useI18n()
const {
  status,
  errorMessage,
  settings,
  saving,
  reasonOpen,
  smsLocked,
  load,
  submit,
  confirmReason,
} = useAuthSettings()

onMounted(load)
</script>

<template>
  <SettingsCardState :status="status" :error-message="errorMessage" @retry="load">
    <form
      v-if="settings"
      class="settings-form general-settings__card"
      data-testid="verification-settings"
      novalidate
      @submit.prevent="submit"
    >
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.general.verificationTitle') }}</legend>
        <SignInCodeChannels :sms-status="settings.smsStatus" :sms-locked="smsLocked" />
      </fieldset>
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">
          {{ t('settings.general.registrationReview.title') }}
        </legend>
        <FieldToggle
          name="entityRegistrationReview"
          :label="t('settings.general.registrationReview.label')"
          :description="t('settings.general.registrationReview.description')"
        />
      </fieldset>
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.general.twoFactor.title') }}</legend>
        <TwoFactorPolicyFields />
      </fieldset>
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.general.nationalId.title') }}</legend>
        <NationalIdPolicyFields :file-access-roles="settings.fileAccessRoles" />
      </fieldset>
      <div class="general-settings__actions">
        <BaseButton type="submit" variant="primary" :loading="saving" icon="save">
          {{ t('actions.save') }}
        </BaseButton>
      </div>
    </form>
    <ReasonDialog
      v-model="reasonOpen"
      :title="t('settings.signIn.reasonTitle')"
      :submit-label="t('actions.save')"
      :action="confirmReason"
      action-name="settings.auth.update"
    />
  </SettingsCardState>
</template>
