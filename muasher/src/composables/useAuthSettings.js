import { useI18n } from 'vue-i18n'
import { useSettingsEditor } from '@/composables/useSettingsEditor'
import { useConfirm } from '@/composables/useConfirm'
import { authSettingsService } from '@/services/authSettingsService'
import {
  authSettingsSchema,
  authSettingsValues,
  changedAuthSettings,
  turnsSecondFactorOff,
} from '@/utils/settings/authSettingsForm'

export function useAuthSettings() {
  const { t } = useI18n()
  const { confirm } = useConfirm()
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

  async function save() {
    if (turnsSecondFactorOff(editor.settings.value, editor.form.values)) {
      const accepted = await confirm({
        title: t('settings.signIn.confirmOff.title'),
        message: t('settings.signIn.confirmOff.message'),
        confirmLabel: t('settings.signIn.confirmOff.confirm'),
        variant: 'danger',
      })
      if (!accepted) return
    }
    await editor.submit()
  }

  return { ...editor, save }
}
