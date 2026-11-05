<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { object as yupObject, string as yupString } from 'yup'
import { ROUTE_NAMES } from '@/constants/routeNames'
import DataTable from '@/components/data-list/DataTable.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import FormModal from '@/components/modal/FormModal.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import HomepageItemsEditor from '@/components/settings/HomepageItemsEditor.vue'
import SiteSettingsEditor from '@/components/settings/SiteSettingsEditor.vue'
import PublicationControl from '@/components/settings/PublicationControl.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useSiteStatusStore } from '@/stores/siteStatus'
import { useHomeAdmin } from '@/composables/useHomeContent'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useToast } from '@/composables/useToast'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const siteStatus = useSiteStatusStore()

const {
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
} = useHomeAdmin()

const ITEM_SECTION_KEYS = new Set([
  'hero',
  'evaluation-paths',
  'excellence-model',
  'how-it-works',
  'fast-excellence-kit',
  'analytics-lenses',
  'partners',
  'kpi-promo',
  'accredited-intro',
  'advanced-intro',
])

function hasItems(section) {
  return ITEM_SECTION_KEYS.has(section.key)
}

onMounted(async () => {
  await load()
  await loadSettings()
})

const togglingId = ref('')

const columns = computed(() => [
  { key: 'key', label: t('settings.homepage.columns.section') },
  { key: 'order', label: t('settings.homepage.columns.order') },
  { key: 'enabled', label: t('settings.homepage.columns.status') },
  { key: 'actions', label: t('settings.homepage.columns.actions') },
])

function sectionName(section) {
  return localized(section.title) || t(`settings.homepage.keys.${section.key}`, section.key)
}

async function onToggle(section, enabled) {
  togglingId.value = section.id
  try {
    await toggleSection(section.id, enabled)
    toast.success(
      enabled ? t('settings.homepage.toasts.enabled') : t('settings.homepage.toasts.disabled'),
    )
  } catch {
    toast.error(t('settings.homepage.toasts.toggleFailed'))
  } finally {
    togglingId.value = ''
  }
}

const publishing = ref(false)

async function confirmPublicationChange(next) {
  if (next) return true
  return confirm({
    title: t('settings.homepage.publication.confirmTitle'),
    message: t('settings.homepage.publication.confirmMessage'),
    confirmLabel: t('settings.homepage.publication.unpublish'),
    variant: 'danger',
  })
}

async function onPublicationChange(next) {
  if (!(await confirmPublicationChange(next))) return
  publishing.value = true
  try {
    const result = await setPublished(next)
    siteStatus.markPublished(result)
    toast.success(
      result
        ? t('settings.homepage.publication.publishedToast')
        : t('settings.homepage.publication.unpublishedToast'),
    )
  } catch {
    toast.error(t('settings.homepage.publication.failed'))
  } finally {
    publishing.value = false
  }
}

async function move(section, direction) {
  const list = orderedSections.value
  const index = list.findIndex((item) => item.id === section.id)
  const target = index + direction
  if (index === -1 || target < 0 || target >= list.length) return
  const ids = list.map((item) => item.id)
  ;[ids[index], ids[target]] = [ids[target], ids[index]]
  try {
    await reorderSections(ids)
    toast.success(t('settings.homepage.toasts.reordered'))
  } catch {
    toast.error(t('settings.homepage.toasts.reorderFailed'))
  }
}

const editOpen = ref(false)
const activeSection = ref(null)

const schema = computed(() =>
  yupObject({
    titleAr: yupString().trim().required(t('validation.required')),
    titleEn: yupString().trim().required(t('validation.required')),
    subtitleAr: yupString().trim(),
    subtitleEn: yupString().trim(),
    descriptionAr: yupString().trim(),
    descriptionEn: yupString().trim(),
    ctaLabelAr: yupString().trim(),
    ctaLabelEn: yupString().trim(),
    ctaUrl: yupString().trim(),
    image: yupString().trim(),
    icon: yupString().trim(),
    background: yupString().trim(),
  }),
)

const form = useValidatedForm({
  schema,
  initialValues: {
    titleAr: '',
    titleEn: '',
    subtitleAr: '',
    subtitleEn: '',
    descriptionAr: '',
    descriptionEn: '',
    ctaLabelAr: '',
    ctaLabelEn: '',
    ctaUrl: '',
    image: '',
    icon: '',
    background: '',
  },
  action: 'homepage.section.update',
  submit: async (values) => {
    if (!activeSection.value) return
    await updateSection(activeSection.value.id, {
      title: { ar: values.titleAr, en: values.titleEn },
      subtitle: { ar: values.subtitleAr, en: values.subtitleEn },
      description: { ar: values.descriptionAr, en: values.descriptionEn },
      ctaLabel: { ar: values.ctaLabelAr, en: values.ctaLabelEn },
      ctaUrl: values.ctaUrl,
      image: values.image,
      icon: values.icon,
      background: values.background,
    })
    editOpen.value = false
    toast.success(t('settings.homepage.toasts.saved'))
  },
})

const [titleAr] = form.defineField('titleAr')
const [titleEn] = form.defineField('titleEn')
const [subtitleAr] = form.defineField('subtitleAr')
const [subtitleEn] = form.defineField('subtitleEn')
const [descriptionAr] = form.defineField('descriptionAr')
const [descriptionEn] = form.defineField('descriptionEn')
const [ctaLabelAr] = form.defineField('ctaLabelAr')
const [ctaLabelEn] = form.defineField('ctaLabelEn')
const [ctaUrl] = form.defineField('ctaUrl')
const [image] = form.defineField('image')
const [icon] = form.defineField('icon')
const [background] = form.defineField('background')

function openEdit(section) {
  activeSection.value = section
  form.resetForm({
    values: {
      titleAr: section.title?.ar ?? '',
      titleEn: section.title?.en ?? '',
      subtitleAr: section.subtitle?.ar ?? '',
      subtitleEn: section.subtitle?.en ?? '',
      descriptionAr: section.description?.ar ?? '',
      descriptionEn: section.description?.en ?? '',
      ctaLabelAr: section.ctaLabel?.ar ?? '',
      ctaLabelEn: section.ctaLabel?.en ?? '',
      ctaUrl: section.ctaUrl ?? '',
      image: section.image ?? '',
      icon: section.icon ?? '',
      background: section.background ?? '',
    },
  })
  editOpen.value = true
}

const itemsOpen = ref(false)
const itemsSectionId = ref('')

const itemsSection = computed(
  () => orderedSections.value.find((section) => section.id === itemsSectionId.value) ?? null,
)

function openItems(section) {
  itemsSectionId.value = section.id
  itemsOpen.value = true
}

async function onUpsertItem(payload) {
  try {
    await upsertItem(itemsSectionId.value, payload)
    toast.success(t('settings.homepage.items.toasts.saved'))
  } catch {
    toast.error(t('settings.homepage.items.toasts.saveFailed'))
  }
}

async function onRemoveItem(itemId) {
  try {
    await removeItem(itemsSectionId.value, itemId)
    toast.success(t('settings.homepage.items.toasts.removed'))
  } catch {
    toast.error(t('settings.homepage.items.toasts.removeFailed'))
  }
}

async function onReorderItems(orderedIds) {
  try {
    await reorderItems(itemsSectionId.value, orderedIds)
    toast.success(t('settings.homepage.items.toasts.reordered'))
  } catch {
    toast.error(t('settings.homepage.items.toasts.reorderFailed'))
  }
}

const settingsStatus = ref('loading')

async function loadSettings() {
  settingsStatus.value = 'loading'
  try {
    await loadSiteSettings()
    settingsStatus.value = 'success'
  } catch {
    settingsStatus.value = 'error'
  }
}

async function onSaveSettings(patch) {
  try {
    await updateSiteSettings(patch)
    toast.success(t('settings.homepage.siteSettings.toasts.saved'))
  } catch {
    toast.error(t('settings.homepage.siteSettings.toasts.saveFailed'))
  }
}
</script>

<template>
  <section class="homepage-cms">
    <header class="homepage-cms__header">
      <RouterLink :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS }" class="homepage-cms__back">
        <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>
        {{ t('settings.hub.backToHub') }}
      </RouterLink>
      <h1 class="homepage-cms__title">{{ t('settings.homepage.title') }}</h1>
      <p class="homepage-cms__desc">{{ t('settings.homepage.description') }}</p>
    </header>

    <PublicationControl
      v-if="status !== 'loading' && status !== 'error'"
      :published="published"
      :busy="publishing"
      @change="onPublicationChange"
    />

    <div v-if="status === 'loading'" class="homepage-cms__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>

    <DataTable
      v-else
      :columns="columns"
      :items="orderedSections"
      :status="status"
      :empty-title="t('settings.homepage.empty.title')"
      :empty-message="t('settings.homepage.empty.message')"
      :error-message="t('settings.homepage.error')"
      @retry="load"
    >
      <template #cell-key="{ item }">
        <div class="homepage-cms__section">
          <span class="homepage-cms__section-name">{{ sectionName(item) }}</span>
          <span class="homepage-cms__section-key">{{ item.key }}</span>
        </div>
      </template>

      <template #cell-order="{ item }">
        <span class="homepage-cms__order">{{ item.order }}</span>
      </template>

      <template #cell-enabled="{ item }">
        <div class="homepage-cms__toggle">
          <ToggleSwitch
            :model-value="item.enabled"
            :name="`section-${item.id}`"
            :label="item.enabled ? t('settings.homepage.enabled') : t('settings.homepage.disabled')"
            :disabled="togglingId === item.id"
            @update:model-value="onToggle(item, $event)"
          />
          <StatusBadge :status="item.enabled ? 'active' : 'inactive'" />
        </div>
      </template>

      <template #cell-actions="{ item }">
        <div class="homepage-cms__actions">
          <BaseButton
            variant="ghost"
            size="sm"
            icon="fa-solid fa-arrow-up-long"
            icon-only
            :disabled="saving || item.order === orderedSections[0]?.order"
            :aria-label="t('settings.homepage.moveUp')"
            @click="move(item, -1)"
          />
          <BaseButton
            variant="ghost"
            size="sm"
            icon="fa-solid fa-arrow-down-long"
            icon-only
            :disabled="saving || item.order === orderedSections[orderedSections.length - 1]?.order"
            :aria-label="t('settings.homepage.moveDown')"
            @click="move(item, 1)"
          />
          <BaseButton
            v-if="hasItems(item)"
            variant="ghost"
            size="sm"
            icon="fa-solid fa-layer-group"
            :disabled="saving"
            @click="openItems(item)"
          >
            {{ t('settings.homepage.items.manage') }}
          </BaseButton>
          <BaseButton variant="secondary" size="sm" icon="fa-solid fa-pen" @click="openEdit(item)">
            {{ t('actions.edit') }}
          </BaseButton>
        </div>
      </template>
    </DataTable>

    <FormModal
      v-model="editOpen"
      size="lg"
      :busy="form.submitting.value"
      :title="
        activeSection
          ? t('settings.homepage.editTitle', { section: sectionName(activeSection) })
          : ''
      "
      :submit-label="t('actions.save')"
      @submit="form.onSubmit"
    >
      <div class="homepage-cms__form">
        <fieldset class="homepage-cms__group">
          <legend class="homepage-cms__legend">{{ t('settings.homepage.groups.text') }}</legend>
          <div class="homepage-cms__grid">
            <BaseInput
              v-model="titleAr"
              name="titleAr"
              :label="t('settings.homepage.fields.titleAr')"
              :error="form.errors.value.titleAr"
              required
            />
            <BaseInput
              v-model="titleEn"
              name="titleEn"
              :label="t('settings.homepage.fields.titleEn')"
              :error="form.errors.value.titleEn"
              required
            />
            <BaseInput
              v-model="subtitleAr"
              name="subtitleAr"
              :label="t('settings.homepage.fields.subtitleAr')"
              :error="form.errors.value.subtitleAr"
            />
            <BaseInput
              v-model="subtitleEn"
              name="subtitleEn"
              :label="t('settings.homepage.fields.subtitleEn')"
              :error="form.errors.value.subtitleEn"
            />
          </div>
          <BaseTextarea
            v-model="descriptionAr"
            name="descriptionAr"
            :rows="3"
            :label="t('settings.homepage.fields.descriptionAr')"
            :error="form.errors.value.descriptionAr"
          />
          <BaseTextarea
            v-model="descriptionEn"
            name="descriptionEn"
            :rows="3"
            :label="t('settings.homepage.fields.descriptionEn')"
            :error="form.errors.value.descriptionEn"
          />
        </fieldset>

        <fieldset class="homepage-cms__group">
          <legend class="homepage-cms__legend">{{ t('settings.homepage.groups.cta') }}</legend>
          <div class="homepage-cms__grid">
            <BaseInput
              v-model="ctaLabelAr"
              name="ctaLabelAr"
              :label="t('settings.homepage.fields.ctaLabelAr')"
              :error="form.errors.value.ctaLabelAr"
            />
            <BaseInput
              v-model="ctaLabelEn"
              name="ctaLabelEn"
              :label="t('settings.homepage.fields.ctaLabelEn')"
              :error="form.errors.value.ctaLabelEn"
            />
          </div>
          <BaseInput
            v-model="ctaUrl"
            name="ctaUrl"
            :label="t('settings.homepage.fields.ctaUrl')"
            :hint="t('settings.homepage.fields.ctaUrlHint')"
            :error="form.errors.value.ctaUrl"
          />
        </fieldset>

        <fieldset class="homepage-cms__group">
          <legend class="homepage-cms__legend">{{ t('settings.homepage.groups.media') }}</legend>
          <div class="homepage-cms__grid">
            <BaseInput
              v-model="image"
              name="image"
              :label="t('settings.homepage.fields.image')"
              :hint="t('settings.homepage.fields.imageHint')"
              :error="form.errors.value.image"
            />
            <BaseInput
              v-model="icon"
              name="icon"
              :label="t('settings.homepage.fields.icon')"
              :hint="t('settings.homepage.fields.iconHint')"
              :error="form.errors.value.icon"
            />
            <BaseInput
              v-model="background"
              name="background"
              :label="t('settings.homepage.fields.background')"
              :hint="t('settings.homepage.fields.backgroundHint')"
              :error="form.errors.value.background"
            />
          </div>
        </fieldset>
      </div>
    </FormModal>

    <HomepageItemsEditor
      v-model="itemsOpen"
      :section="itemsSection"
      :items="itemsSection?.items ?? []"
      :busy="saving"
      :localized="localized"
      @upsert="onUpsertItem"
      @remove="onRemoveItem"
      @reorder="onReorderItems"
    />

    <SiteSettingsEditor
      :site-settings="siteSettings"
      :status="settingsStatus"
      :busy="saving"
      @save="onSaveSettings"
      @retry="loadSettings"
    />
  </section>
</template>
