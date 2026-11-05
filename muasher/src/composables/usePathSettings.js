import { computed } from 'vue'
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
  const priceEditable = computed(() => can(PERMISSIONS.SETTINGS_ASSESSMENT_MANAGE))
  const editor = useSettingsEditor({
    read: () => pathSettingsService.get(path),
    write: (payload) => pathSettingsService.update(path, payload),
    schema: (t, settings) =>
      pathSettingsSchema(t, settings, { priceEditable: priceEditable.value }),
    valuesOf: pathSettingsValues,
    changesOf: (settings, values) =>
      changedPathSettings(settings, values, { priceEditable: priceEditable.value }),
    messages: { saved: 'settings.paths.saved', unchanged: 'settings.assessment.unchanged' },
    action: `settings.assessment.paths.${path}.update`,
  })
  return { ...editor, priceEditable }
}
