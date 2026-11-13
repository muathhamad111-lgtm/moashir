<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import { useAssessmentSettings } from '@/composables/useAssessmentSettings'
import { ADVANCED_BOUNDS, UPLOAD_BOUNDS } from '@/utils/accredited/assessmentSettingsForm'
import { ENTITY_VISIBILITY_LIST } from '@/constants/committee'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const { status, errorMessage, settings, saving, reasonOpen, load, submit, confirmReason } =
  useAssessmentSettings()

const visibilityOptions = computed(() =>
  ENTITY_VISIBILITY_LIST.map((value) => ({
    value,
    label: t(`settings.assessment.visibility.${value}`),
  })),
)

onMounted(load)
</script>

<template>
  <div class="path-settings">
    <div v-if="status === 'loading'" class="path-settings__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="3" />
    </div>
    <ErrorState v-else-if="status !== 'success'" :message="errorMessage" @retry="load" />
    <form
      v-else-if="settings"
      id="assessment-settings-form"
      class="settings-form"
      novalidate
      @submit.prevent="submit"
    >
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">
          {{ t('settings.assessment.generalUploadsTitle') }}
        </legend>
        <p class="settings-form__note" role="note">
          <AppIcon name="info-circle" />
          {{ t('settings.assessment.generalUploadsNote') }}
        </p>
        <div class="settings-form__grid">
          <FieldInput
            name="generalUploadMaxFileMb"
            :label="t('settings.assessment.maxFileMb')"
            :hint="t('settings.assessment.rangeHint', UPLOAD_BOUNDS.maxFileMb)"
            inputmode="numeric"
            input-dir="ltr"
            required
          />
        </div>
      </fieldset>
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.assessment.awardTitle') }}</legend>
        <p class="settings-form__note" role="note">
          <AppIcon name="info-circle" />
          {{ t('settings.assessment.awardNote') }}
        </p>
        <div class="settings-form__grid">
          <FieldInput
            name="advancedMinEntityAgeYears"
            :label="t('settings.assessment.minEntityAge')"
            :hint="t('settings.assessment.rangeHint', ADVANCED_BOUNDS.minEntityAgeYears)"
            inputmode="numeric"
            input-dir="ltr"
            required
          />
          <FieldInput
            name="advancedDisqualificationScore"
            :label="t('settings.assessment.disqualificationScore')"
            :hint="t('settings.assessment.scoreHint', ADVANCED_BOUNDS.disqualificationScore)"
            inputmode="decimal"
            input-dir="ltr"
            required
          />
        </div>
        <FieldSelect
          name="committeeEntityDataVisibility"
          :label="t('settings.assessment.committeeVisibility')"
          :options="visibilityOptions"
          :hint="t('settings.assessment.committeeVisibilityHint')"
          required
        />
      </fieldset>
      <div class="path-settings__actions">
        <BaseButton type="submit" variant="primary" :loading="saving" icon="save">{{
          t('actions.save')
        }}</BaseButton>
      </div>
    </form>
    <ReasonDialog
      v-model="reasonOpen"
      :title="t('settings.assessment.reasonTitle')"
      :submit-label="t('actions.save')"
      :action="confirmReason"
      action-name="settings.assessment.update"
    />
  </div>
</template>
