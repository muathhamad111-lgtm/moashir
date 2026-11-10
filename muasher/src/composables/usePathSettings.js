import { useSettingsEditor } from '@/composables/useSettingsEditor'
import { usePermissions } from '@/composables/usePermissions'
import { pathSettingsService } from '@/services/pathSettingsService'
import { PERMISSIONS } from '@/constants/permissionKeys'
import {
  changedPathSettings,
  pathSettingsSchema,
  pathSettingsValues,
} from '@/utils/settings/pathSettingsForm'

export function usePathSettings(path) {
  const { can } = usePermissions()
  const editor = useSettingsEditor({
    read: () => pathSettingsService.get(path),
    write: (payload) => pathSettingsService.update(path, payload),
    schema: (t, settings) => pathSettingsSchema(t, settings),
    valuesOf: pathSettingsValues,
    changesOf: (settings, values) => changedPathSettings(settings, values),
    messages: { saved: 'settings.paths.saved', unchanged: 'settings.assessment.unchanged' },
    action: `settings.assessment.paths.${path}.update`,
  })
  return { ...editor, canEditPrices: can(PERMISSIONS.SETTINGS_PRICES_MANAGE) }
}
