import { computed } from 'vue'
import { useSettingsEditor } from '@/composables/useSettingsEditor'
import { authSettingsService } from '@/services/authSettingsService'
import {
  authSettingsSchema,
  authSettingsValues,
  changedAuthSettings,
  smsChannelLocked,
} from '@/utils/settings/authSettingsForm'

export function useAuthSettings() {
  const editor = useSettingsEditor({
    read: () => authSettingsService.get(),
    write: (payload) => authSettingsService.update(payload),
    schema: authSettingsSchema,
    valuesOf: authSettingsValues,
    changesOf: changedAuthSettings,
    messages: {
      saved: 'settings.signIn.saved',
      unchanged: 'settings.signIn.unchanged',
    },
    action: 'settings.auth.update',
  })

  const smsLocked = computed(
    () => Boolean(editor.settings.value) && smsChannelLocked(editor.settings.value),
  )

  return { ...editor, smsLocked }
}
