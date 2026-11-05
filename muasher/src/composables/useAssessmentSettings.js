import { useSettingsEditor } from '@/composables/useSettingsEditor'
import { assessmentSettingsService } from '@/services/assessmentSettingsService'
import {
  assessmentSettingsSchema,
  changedAssessmentSettings,
  settingsFormValues,
} from '@/utils/accredited/assessmentSettingsForm'

export function useAssessmentSettings() {
  return useSettingsEditor({
    read: () => assessmentSettingsService.get(),
    write: (payload) => assessmentSettingsService.update(payload),
    schema: (t) => assessmentSettingsSchema(t),
    valuesOf: settingsFormValues,
    changesOf: changedAssessmentSettings,
    messages: { saved: 'settings.assessment.saved', unchanged: 'settings.assessment.unchanged' },
    action: 'settings.assessment.update',
  })
}
