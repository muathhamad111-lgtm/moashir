<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import { useContactSettings } from '@/composables/useContactSettings'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const { status, errorMessage, settings, saving, reasonOpen, load, submit, confirmReason } =
  useContactSettings()

onMounted(load)
</script>

<template>
  <SettingsSection
    :title="t('settings.contactMessages.title')"
    :description="t('settings.contactMessages.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <form
      v-if="settings"
      id="contact-settings-form"
      class="settings-form"
      novalidate
      @submit.prevent="submit"
    >
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">
          {{ t('settings.contactMessages.retentionTitle') }}
        </legend>
        <p class="settings-form__note" role="note">
          <AppIcon name="info-circle" />
          {{ t('settings.contactMessages.retentionNote') }}
        </p>
        <FieldInput
          name="retentionMonths"
          :label="t('settings.contactMessages.retentionMonths')"
          :hint="
            t('settings.contactMessages.retentionHint', {
              min: settings.retentionLimits.min,
              max: settings.retentionLimits.max,
            })
          "
          inputmode="numeric"
          input-dir="ltr"
          required
        />
      </fieldset>
    </form>
    <template #actions>
      <BaseButton
        v-if="settings"
        type="submit"
        form="contact-settings-form"
        variant="primary"
        :loading="saving"
        icon="save"
        >{{ t('actions.save') }}</BaseButton
      >
    </template>
  </SettingsSection>
  <ReasonDialog
    v-model="reasonOpen"
    :title="t('settings.assessment.reasonTitle')"
    :submit-label="t('actions.save')"
    :action="confirmReason"
    action-name="settings.contactMessages.update"
  />
</template>
