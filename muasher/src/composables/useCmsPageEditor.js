import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { cmsService } from '@/services/cmsService'
import { localizedText } from '@/utils/localizedText'

export function useCmsPageEditor(pageKey) {
  const { locale } = useI18n()
  const page = ref(null)
  const status = ref('loading')
  const busy = ref(false)

  const sections = computed(() =>
    [...(page.value?.sections ?? [])].sort((a, b) => a.order - b.order),
  )

  function localized(value) {
    return localizedText(value, locale.value)
  }

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await cmsService.getPage(pageKey.value)
      page.value = data
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  async function run(request) {
    busy.value = true
    try {
      return await request()
    } finally {
      busy.value = false
    }
  }

  function markDraft() {
    if (page.value) page.value = { ...page.value, hasUnpublishedChanges: true }
  }

  function replaceSection(section) {
    const others = (page.value?.sections ?? []).filter((entry) => entry.id !== section.id)
    page.value = { ...page.value, sections: [...others, section] }
    markDraft()
    return section
  }

  function dropSection(sectionId) {
    page.value = {
      ...page.value,
      sections: page.value.sections.filter((entry) => entry.id !== sectionId),
    }
    markDraft()
  }

  async function sectionWrite(request) {
    const { data } = await run(request)
    return replaceSection(data)
  }

  async function pageWrite(request) {
    const { data } = await run(request)
    page.value = data
    return data
  }

  watch(pageKey, load)

  return {
    page,
    sections,
    status,
    busy,
    localized,
    load,
    publish: () => pageWrite(() => cmsService.publishPage(pageKey.value)),
    unpublish: () => pageWrite(() => cmsService.unpublishPage(pageKey.value)),
    updateDetails: (payload) => pageWrite(() => cmsService.updatePage(pageKey.value, payload)),
    createSection: (payload) =>
      sectionWrite(() => cmsService.createSection(pageKey.value, payload)),
    updateSection: (id, patch) => sectionWrite(() => cmsService.updateSection(id, patch)),
    toggleSection: (id, enabled) => sectionWrite(() => cmsService.toggleSection(id, enabled)),
    async removeSection(id) {
      await run(() => cmsService.removeSection(id))
      dropSection(id)
    },
    async reorderSections(orderedIds) {
      const { data } = await run(() => cmsService.reorderSections(orderedIds))
      page.value = { ...page.value, sections: data }
      markDraft()
    },
    createItem: (sectionId, payload) =>
      sectionWrite(() => cmsService.createItem(sectionId, payload)),
    updateItem: (sectionId, itemId, patch) =>
      sectionWrite(() => cmsService.updateItem(sectionId, itemId, patch)),
    toggleItem: (sectionId, itemId, enabled) =>
      sectionWrite(() => cmsService.toggleItem(sectionId, itemId, enabled)),
    async removeItem(sectionId, itemId) {
      await run(() => cmsService.removeItem(sectionId, itemId))
      const section = page.value.sections.find((entry) => entry.id === sectionId)
      replaceSection({ ...section, items: section.items.filter((item) => item.id !== itemId) })
    },
    reorderItems: (sectionId, orderedIds) =>
      sectionWrite(() => cmsService.reorderItems(sectionId, orderedIds)),
  }
}
