<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldToggle from '@/components/form/FieldToggle.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import SettingsCardState from './SettingsCardState.vue'
import { useAssignmentReasonSettings } from '@/composables/useAssignmentReasonSettings'

// Whether assigning a request, and moving it to another assessor, need a reason (admin review 2026-11-20).
const { t } = useI18n()
const { status, errorMessage, settings, saving, reasonOpen, load, submit, confirmReason } =
  useAssignmentReasonSettings()

onMounted(load)
</script>

<template>
  <SettingsCardState :status="status" :error-message="errorMessage" @retry="load">
    <form
      v-if="settings"
      class="settings-form general-settings__card"
      data-testid="assignment-reasons-settings"
      novalidate
      @submit.prevent="submit"
    >
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.assignmentReasons.title') }}</legend>
        <FieldToggle
          name="assignRequired"
          :label="t('settings.assignmentReasons.assign')"
          :description="t('settings.assignmentReasons.assignHint')"
        />
        <FieldToggle
          name="reassignRequired"
          :label="t('settings.assignmentReasons.reassign')"
          :description="t('settings.assignmentReasons.reassignHint')"
        />
        <p class="settings-form__note">{{ t('settings.assignmentReasons.superAdminNote') }}</p>
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
      action-name="settings.assignmentReasons.update"
    />
  </SettingsCardState>
</template>
