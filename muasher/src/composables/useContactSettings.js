import { useSettingsEditor } from '@/composables/useSettingsEditor'
import { contactSettingsService } from '@/services/contactSettingsService'
import {
  changedContactSettings,
  contactSettingsSchema,
  contactSettingsValues,
} from '@/utils/settings/contactSettingsForm'

export function useContactSettings() {
  return useSettingsEditor({
    read: () => contactSettingsService.get(),
    write: (payload) => contactSettingsService.update(payload),
    schema: contactSettingsSchema,
    valuesOf: contactSettingsValues,
    changesOf: changedContactSettings,
    messages: {
      saved: 'settings.contactMessages.saved',
      unchanged: 'settings.assessment.unchanged',
    },
    action: 'settings.contactMessages.update',
  })
}
