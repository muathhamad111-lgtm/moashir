import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { awardResultsService } from '@/services/awardResultsService'

function localizedFactory(locale) {
  return function localized(content) {
    if (content === null || content === undefined) return ''
    if (typeof content === 'string') return content
    return content[locale.value] || content.ar || content.en || ''
  }
}

export function useAwardResults() {
  const { locale } = useI18n()
  const localized = localizedFactory(locale)

  const status = ref('loading')
  const errorMessage = ref('')
  const results = ref([])
  const regions = ref([])
  const busy = ref(false)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data, meta } = await awardResultsService.listResults()
      results.value = data ?? []
      regions.value = meta?.regions ?? []
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  const regionGroups = computed(() =>
    regions.value.map((region) => ({
      ...region,
      results: results.value.filter((r) => r.region === region.key),
    })),
  )

  async function setRank(assessmentId, rank) {
    busy.value = true
    try {
      const { data } = await awardResultsService.setRegionalRank(assessmentId, rank)
      const target = results.value.find((r) => r.assessmentId === data.assessmentId)
      if (target) target.regionalRank = data.regionalRank
      await load()
      return data
    } finally {
      busy.value = false
    }
  }

  async function approveRegion(region) {
    busy.value = true
    try {
      await awardResultsService.approveRegion(region)
      await load()
    } finally {
      busy.value = false
    }
  }

  return {
    status,
    errorMessage,
    results,
    regions,
    regionGroups,
    busy,
    localized,
    load,
    setRank,
    approveRegion,
  }
}

export function useNationalAward() {
  const { locale } = useI18n()
  const localized = localizedFactory(locale)

  const status = ref('loading')
  const errorMessage = ref('')
  const candidates = ref([])
  const approved = ref(false)
  const busy = ref(false)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data, meta } = await awardResultsService.listNational()
      candidates.value = data ?? []
      approved.value = meta?.approved ?? false
      status.value = candidates.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  async function setRank(assessmentId, rank) {
    busy.value = true
    try {
      await awardResultsService.setNationalRank(assessmentId, rank)
      await load()
    } finally {
      busy.value = false
    }
  }

  async function approve() {
    busy.value = true
    try {
      await awardResultsService.approveNational()
      await load()
    } finally {
      busy.value = false
    }
  }

  return { status, errorMessage, candidates, approved, busy, localized, load, setRank, approve }
}
