import { computed, onMounted, ref } from 'vue'
import { assessmentsService } from '@/services/assessmentsService'
import { accreditedService } from '@/services/accreditedService'
import { criteriaComparison, resultSeries, seriesDelta } from '@/utils/accreditedPerformance'

const COMPLETED_ACCREDITED = {
  'filter[path]': 'accredited',
  'filter[status]': 'completed',
  perPage: 6,
}
const COMPARED_RESULTS = 2

function mainsOf(result) {
  return (result?.main ?? []).map((main) => ({
    criterionId: main.criterionId ?? null,
    code: main.code ?? '',
    name: main.name ?? null,
    percent: main.percent ?? null,
  }))
}

export function useAccreditedPerformance({ auto = true } = {}) {
  const series = ref([])
  const results = ref([])
  const status = ref('idle')

  async function loadResults(latest) {
    const responses = await Promise.all(
      latest.map((item) => accreditedService.getResult(item.id).then(({ data }) => data)),
    )
    results.value = responses.filter((result) => result?.available)
  }

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await assessmentsService.list(COMPLETED_ACCREDITED)
      series.value = resultSeries(data)
      await loadResults(series.value.slice(-COMPARED_RESULTS).reverse())
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  const criteria = computed(() =>
    criteriaComparison(mainsOf(results.value[0]), mainsOf(results.value[1])),
  )
  const latest = computed(() => series.value.at(-1) ?? null)
  const delta = computed(() => seriesDelta(series.value))
  const hasPrevious = computed(() => results.value.length > 1)

  if (auto) onMounted(load)

  return { status, series, criteria, latest, delta, hasPrevious, load }
}
