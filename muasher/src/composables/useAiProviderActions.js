import { computed } from 'vue'
import { aiIntegrationService } from '@/services/aiIntegrationService'
import { useProviderAction } from '@/composables/useProviderAction'

export function useAiProviderActions(provider, { onSettled } = {}) {
  const models = useProviderAction(() => aiIntegrationService.listModels(provider))
  const answer = useProviderAction(() => aiIntegrationService.testAnswer(provider), { onSettled })
  const loadedModels = computed(() => models.result.value ?? [])
  const runTest = () => aiIntegrationService.testConnection(provider)

  return { models, answer, loadedModels, runTest }
}
