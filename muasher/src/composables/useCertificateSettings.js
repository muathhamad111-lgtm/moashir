import { useSettingsEditor } from '@/composables/useSettingsEditor'
import { certificateSettingsService } from '@/services/certificateSettingsService'
import { useCertificateLevelsStore } from '@/stores/certificateLevels'
import {
  certificateSettingsSchema,
  certificateSettingsValues,
  changedCertificateSettings,
} from '@/utils/settings/certificateSettingsForm'

export function useCertificateSettings() {
  const levels = useCertificateLevelsStore()
  return useSettingsEditor({
    read: () => certificateSettingsService.get(),
    write: async (payload) => {
      const envelope = await certificateSettingsService.update(payload)
      levels.$reset()
      return envelope
    },
    schema: (t) => certificateSettingsSchema(t),
    valuesOf: certificateSettingsValues,
    changesOf: changedCertificateSettings,
    messages: {
      saved: 'settings.certificates.saved',
      unchanged: 'settings.assessment.unchanged',
    },
    action: 'settings.certificates.update',
  })
}
