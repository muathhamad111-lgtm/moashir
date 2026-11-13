import { ref } from 'vue'
import { contactInboxService } from '@/services/contactInboxService'

export function useContactAssignees() {
  const assignees = ref([])

  async function load() {
    try {
      assignees.value = (await contactInboxService.assignees()).data ?? []
    } catch {
      assignees.value = []
    }
  }

  return { assignees, load }
}
