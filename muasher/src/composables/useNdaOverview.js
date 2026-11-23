import { onMounted, ref } from 'vue'
import { ndaService } from '@/services/ndaService'

export function useNdaOverview() {
  const overview = ref(null)
  const summary = ref(null)
  const status = ref('loading')
  const errorMessage = ref('')

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const [settings, register] = await Promise.all([
        ndaService.overview(),
        ndaService.acceptances({ perPage: 1 }),
      ])
      overview.value = settings.data
      summary.value = register.meta.summary
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  onMounted(load)

  return { overview, summary, status, errorMessage, load }
}
