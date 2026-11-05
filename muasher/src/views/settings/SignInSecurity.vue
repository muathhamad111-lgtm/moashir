<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import FieldToggle from '@/components/form/FieldToggle.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import SecondFactorRisk from '@/components/settings/SecondFactorRisk.vue'
import { useAuthSettings } from '@/composables/useAuthSettings'
import AppIcon from '@/components/ui/AppIcon.vue'

const FORM_ID = 'sign-in-security-form'
const ALWAYS_APPLIES = ['enrolledAuthenticator', 'audited']

const { t } = useI18n()
const { status, errorMessage, settings, form, saving, reasonOpen, load, save, confirmReason } =
  useAuthSettings()

const secondFactorOn = computed(() => form.values.entitySecondFactorEnabled === true)
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
    <form v-if="settings" :id="FORM_ID" class="settings-form" novalidate @submit.prevent="save">
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.signIn.entityTitle') }}</legend>
        <FieldToggle
          name="entitySecondFactorEnabled"
          :label="t('settings.signIn.entitySecondFactor')"
          :description="t('settings.signIn.entitySecondFactorDesc')"
        />
        <p v-if="secondFactorOn" class="settings-form__note" role="note">
          <AppIcon name="shield-info" />
          {{ t('settings.signIn.onNote') }}
        </p>
        <SecondFactorRisk v-else />
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
