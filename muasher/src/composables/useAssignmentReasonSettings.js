import { useSettingsEditor } from '@/composables/useSettingsEditor'
import { assignmentReasonsService } from '@/services/assignmentReasonsService'
import {
  assignmentReasonsSchema,
  assignmentReasonsValues,
  changedAssignmentReasons,
} from '@/utils/settings/assignmentReasonsForm'

export function useAssignmentReasonSettings() {
  return useSettingsEditor({
    read: () => assignmentReasonsService.get(),
    write: (payload) => assignmentReasonsService.update(payload),
    schema: assignmentReasonsSchema,
    valuesOf: assignmentReasonsValues,
    changesOf: changedAssignmentReasons,
    messages: {
      saved: 'settings.assignmentReasons.saved',
      unchanged: 'settings.assessment.unchanged',
    },
    action: 'settings.assignmentReasons.update',
  })
}
