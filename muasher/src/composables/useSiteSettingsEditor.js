import { onMounted, ref } from 'vue'
import { siteSettingsService } from '@/services/siteSettingsService'
import { SITE_SETTINGS_LIMITS } from '@/constants/cms'

function bilingual(value) {
  return { ar: value?.ar ?? '', en: value?.en ?? '' }
}

function trimmed(value) {
  return { ar: value.ar.trim(), en: value.en.trim() }
}

export function draftOf(settings) {
  return {
    social: (settings?.social ?? []).map((row) => ({ platform: row.platform, url: row.url ?? '' })),
    contact: {
      email: settings?.contact?.email ?? '',
      phone: settings?.contact?.phone ?? '',
      address: bilingual(settings?.contact?.address),
    },
    footerNote: bilingual(settings?.footerNote),
    quickLinks: (settings?.quickLinks ?? []).map((row) => ({
      label: bilingual(row.label),
      url: row.url ?? '',
    })),
    seo: {
      title: bilingual(settings?.seo?.title),
      description: bilingual(settings?.seo?.description),
    },
    featuredKpiLimit: settings?.featuredKpiLimit ?? SITE_SETTINGS_LIMITS.featuredMax,
    showFeaturedKpis: settings?.showFeaturedKpis ?? true,
  }
}

export function payloadOf(draft) {
  return {
    social: draft.social.map((row) => ({ platform: row.platform, url: row.url.trim() })),
    contact: {
      email: draft.contact.email.trim() || null,
      phone: draft.contact.phone.trim() || null,
      address: trimmed(draft.contact.address),
    },
    footerNote: trimmed(draft.footerNote),
    quickLinks: draft.quickLinks.map((row) => ({ label: trimmed(row.label), url: row.url.trim() })),
    seo: { title: trimmed(draft.seo.title), description: trimmed(draft.seo.description) },
    featuredKpiLimit: Number(draft.featuredKpiLimit),
    showFeaturedKpis: Boolean(draft.showFeaturedKpis),
  }
}

export function useSiteSettingsEditor({ auto = true } = {}) {
  const status = ref('loading')
  const draft = ref(draftOf(null))
  const saving = ref(false)
  const fieldErrors = ref({})

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await siteSettingsService.get()
      draft.value = draftOf(data)
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  async function save() {
    saving.value = true
    fieldErrors.value = {}
    try {
      const { data } = await siteSettingsService.update(payloadOf(draft.value))
      draft.value = draftOf(data)
      return data
    } catch (error) {
      fieldErrors.value = error?.normalized?.fieldErrors ?? {}
      throw error
    } finally {
      saving.value = false
    }
  }

  function errorOf(path) {
    const messages = fieldErrors.value[path]
    return Array.isArray(messages) ? messages[0] : (messages ?? '')
  }

  if (auto) onMounted(load)

  return { status, draft, saving, fieldErrors, load, save, errorOf }
}
