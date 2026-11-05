import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { contentService } from '@/services/contentService'

function resolve(content, locale) {
  if (content === null || content === undefined) return ''
  if (typeof content === 'string') return content
  return content[locale] || content.ar || content.en || ''
}

export function usePublicHome({ auto = true } = {}) {
  const { locale } = useI18n()
  const sections = ref([])
  const siteSettings = ref(null)
  const status = ref('loading')

  function localized(value) {
    return resolve(value, locale.value)
  }

  const orderedSections = computed(() =>
    [...sections.value].filter((section) => section.enabled).sort((a, b) => a.order - b.order),
  )

  function sectionByKey(key) {
    return orderedSections.value.find((section) => section.key === key) ?? null
  }

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await contentService.getHome()
      if (!data || data.published === false) {
        sections.value = []
        siteSettings.value = null
        status.value = 'empty'
        return
      }
      sections.value = data.sections ?? []
      siteSettings.value = data.siteSettings ?? null
      status.value = sections.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  if (auto) onMounted(load)

  return { sections, orderedSections, siteSettings, status, load, localized, sectionByKey }
}

export function useHomeAdmin({ auto = false } = {}) {
  const { locale } = useI18n()
  const sections = ref([])
  const siteSettings = ref(null)
  const published = ref(false)
  const status = ref('loading')
  const saving = ref(false)

  function localized(value) {
    return resolve(value, locale.value)
  }

  const orderedSections = computed(() => [...sections.value].sort((a, b) => a.order - b.order))

  function applySection(updated) {
    const index = sections.value.findIndex((section) => section.id === updated.id)
    if (index === -1) sections.value.push(updated)
    else sections.value.splice(index, 1, updated)
  }

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await contentService.getHomeAdmin()
      sections.value = data.sections ?? []
      siteSettings.value = data.siteSettings ?? null
      published.value = Boolean(data.published)
      status.value = sections.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  async function updateSection(id, patch) {
    saving.value = true
    try {
      const { data } = await contentService.updateSection(id, patch)
      applySection(data)
      return data
    } finally {
      saving.value = false
    }
  }

  async function toggleSection(id, enabled) {
    const { data } = await contentService.toggleSection(id, enabled)
    applySection(data)
    return data
  }

  async function reorderSections(orderedIds) {
    saving.value = true
    try {
      const { data } = await contentService.reorderSections(orderedIds)
      sections.value = data ?? []
      return data
    } finally {
      saving.value = false
    }
  }

  async function upsertItem(sectionId, itemPayload) {
    saving.value = true
    try {
      const { data } = await contentService.upsertItem(sectionId, itemPayload)
      applySection(data)
      return data
    } finally {
      saving.value = false
    }
  }

  async function removeItem(sectionId, itemId) {
    saving.value = true
    try {
      const { data } = await contentService.removeItem(sectionId, itemId)
      applySection(data)
      return data
    } finally {
      saving.value = false
    }
  }

  async function reorderItems(sectionId, orderedIds) {
    saving.value = true
    try {
      const { data } = await contentService.reorderItems(sectionId, orderedIds)
      applySection(data)
      return data
    } finally {
      saving.value = false
    }
  }

  async function loadSiteSettings() {
    const { data } = await contentService.getSiteSettings()
    siteSettings.value = data
    return data
  }

  async function updateSiteSettings(patch) {
    saving.value = true
    try {
      const { data } = await contentService.updateSiteSettings(patch)
      siteSettings.value = data
      return data
    } finally {
      saving.value = false
    }
  }

  async function setPublished(next) {
    saving.value = true
    try {
      const request = next ? contentService.publishPage : contentService.unpublishPage
      const { data } = await request('home')
      published.value = Boolean(data?.published)
      return published.value
    } finally {
      saving.value = false
    }
  }

  if (auto) onMounted(load)

  return {
    sections,
    orderedSections,
    siteSettings,
    published,
    setPublished,
    status,
    saving,
    localized,
    load,
    updateSection,
    toggleSection,
    reorderSections,
    upsertItem,
    removeItem,
    reorderItems,
    loadSiteSettings,
    updateSiteSettings,
  }
}
