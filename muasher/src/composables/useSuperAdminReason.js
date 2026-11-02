import { useAuthStore } from '@/stores/auth'
import { useReasonPrompt } from '@/composables/useReasonPrompt'

export function useSuperAdminReason() {
  const auth = useAuthStore()
  const prompt = useReasonPrompt()

  async function reasonExtra() {
    if (!auth.isSuperAdmin) return {}
    const reason = await prompt.prompt()
    return reason ? { reason } : null
  }

  return { prompt, reasonExtra }
}
