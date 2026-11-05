<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import SignInCodeChannels from '@/components/settings/SignInCodeChannels.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useAuthSettings } from '@/composables/useAuthSettings'

const FORM_ID = 'sign-in-security-form'
const ALWAYS_APPLIES = ['audited']

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

const facts = computed(() =>
  settings.value?.staffTotpRequired ? ['staffTotp', ...ALWAYS_APPLIES] : ALWAYS_APPLIES,
)

onMounted(load)
</script>

<template>
  <SettingsSection
    :title="t('settings.signIn.title')"
    :description="t('settings.signIn.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <form v-if="settings" :id="FORM_ID" class="settings-form" novalidate @submit.prevent="submit">
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.signIn.entityTitle') }}</legend>
        <p class="settings-form__note sign-in-security__statement" role="note">
          <AppIcon name="shield-info" />
          <span>{{ t('settings.signIn.statement') }}</span>
        </p>
        <SignInCodeChannels :sms-status="settings.smsStatus" :sms-locked="smsLocked" />
      </fieldset>
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.signIn.alwaysTitle') }}</legend>
        <ul class="sign-in-security__facts">
          <li v-for="fact in facts" :key="fact" class="sign-in-security__fact">
            <AppIcon name="check-circle" />
            <span>{{ t(`settings.signIn.always.${fact}`) }}</span>
          </li>
        </ul>
      </fieldset>
    </form>
    <template #actions>
      <BaseButton
        v-if="settings"
        type="submit"
        :form="FORM_ID"
        variant="primary"
        :loading="saving"
        :disabled="smsLocked"
        icon="save"
        >{{ t('actions.save') }}</BaseButton
      >
    </template>
  </SettingsSection>
  <ReasonDialog
    v-model="reasonOpen"
    :title="t('settings.signIn.reasonTitle')"
    :message="t('settings.signIn.reasonMessage')"
    :submit-label="t('actions.save')"
    :action="confirmReason"
    action-name="settings.auth.update"
  />
</template>
