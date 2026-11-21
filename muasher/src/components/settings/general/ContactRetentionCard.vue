<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldDigits from '@/components/form/FieldDigits.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import SettingsCardState from './SettingsCardState.vue'
import { useContactSettings } from '@/composables/useContactSettings'

const RETENTION_DIGITS = 3

const { t } = useI18n()
const { status, errorMessage, settings, saving, reasonOpen, load, submit, confirmReason } =
  useContactSettings()

onMounted(load)
</script>

<template>
  <SettingsCardState :status="status" :error-message="errorMessage" @retry="load">
    <form
      v-if="settings"
      class="settings-form general-settings__card"
      data-testid="contact-retention-settings"
      novalidate
      @submit.prevent="submit"
    >
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.contactMessages.title') }}</legend>
        <FieldDigits
          name="retentionMonths"
          :label="t('settings.contactMessages.retentionMonths')"
          :maxlength="RETENTION_DIGITS"
          required
        />
      </fieldset>
      <div class="general-settings__actions">
        <BaseButton type="submit" variant="primary" :loading="saving" icon="save">
          {{ t('actions.save') }}
        </BaseButton>
      </div>
    </form>
    <ReasonDialog
      v-model="reasonOpen"
      :title="t('settings.assessment.reasonTitle')"
      :submit-label="t('actions.save')"
      :action="confirmReason"
      action-name="settings.contactMessages.update"
    />
  </SettingsCardState>
</template>
