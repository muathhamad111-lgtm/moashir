import { useSettingsEditor } from '@/composables/useSettingsEditor'
import { pathSettingsService } from '@/services/pathSettingsService'
import {
  changedPathSettings,
  pathSettingsSchema,
  pathSettingsValues,
} from '@/utils/settings/pathSettingsForm'

export function usePathSettings(path, { onSaved } = {}) {
  return useSettingsEditor({
    read: () => pathSettingsService.get(path),
    write: (payload) => pathSettingsService.update(path, payload),
    schema: (t, settings) => pathSettingsSchema(t, settings),
    valuesOf: pathSettingsValues,
    changesOf: (settings, values) => changedPathSettings(settings, values),
    messages: { saved: 'settings.paths.saved', unchanged: 'settings.assessment.unchanged' },
    action: `settings.assessment.paths.${path}.update`,
    onSaved,
  })
}
