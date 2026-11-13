<script setup>
import { onMounted, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import PublicationControl from '@/components/settings/PublicationControl.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import SectionList from './SectionList.vue'
import SectionFormDialog from './SectionFormDialog.vue'
import SectionItemsDialog from './SectionItemsDialog.vue'
import PageDetailsDialog from './PageDetailsDialog.vue'
import { useCmsPageEditor } from '@/composables/useCmsPageEditor'
import { useCmsPublication } from '@/composables/useCmsPublication'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { cmsService } from '@/services/cmsService'
import { sectionLabel } from '@/utils/cmsPayload'

const props = defineProps({
  pageKey: { type: String, required: true },
})

const { t } = useI18n()
const { confirm } = useConfirm()
const toast = useToast()
const pageKey = toRef(props, 'pageKey')
const editor = useCmsPageEditor(pageKey)
const { page, sections, status, busy, localized } = editor
const publication = useCmsPublication(editor, pageKey)

const sectionFormOpen = ref(false)
const editingSection = ref(null)
const itemsOpen = ref(false)
const itemsSectionId = ref(null)
const detailsOpen = ref(false)
const upload = (file) => cmsService.uploadImage(file)

const itemsSection = () =>
  sections.value.find((section) => section.id === itemsSectionId.value) ?? null

function openSectionForm(section = null) {
  editingSection.value = section
  sectionFormOpen.value = true
}

function openItems(section) {
  itemsSectionId.value = section.id
  itemsOpen.value = true
}

async function saveSection(payload) {
  if (editingSection.value) await editor.updateSection(editingSection.value.id, payload)
  else await editor.createSection(payload)
  toast.success(t('cms.sections.toasts.saved'))
}

async function saveDetails(values) {
  await editor.updateDetails(values)
  toast.success(t('cms.page.detailsSaved'))
}

async function attempt(request, successKey) {
  try {
    await request()
    toast.success(t(successKey))
  } catch (error) {
    toast.error(error?.normalized?.message || t('cms.toasts.failed'))
  }
}

async function removeSection(section) {
  const accepted = await confirm({
    title: t('cms.sections.confirmRemove.title'),
    message: sectionLabel(section, localized),
    confirmLabel: t('cms.sections.remove'),
    variant: 'danger',
  })
  if (accepted) await attempt(() => editor.removeSection(section.id), 'cms.sections.toasts.removed')
}

onMounted(editor.load)
</script>

<template>
  <div class="cms-page-editor">
    <div v-if="status === 'loading'" class="cms-page-editor__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="t('cms.loadError')" @retry="editor.load" />
    <template v-else-if="page">
      <PublicationControl
        :page="page"
        :busy="busy"
        @publish="publication.publish"
        @unpublish="publication.unpublish"
        @details="detailsOpen = true"
      />
      <div class="cms-page-editor__toolbar">
        <h2 class="cms-page-editor__title">{{ t('cms.sections.title') }}</h2>
        <BaseButton
          variant="primary"
          size="sm"
          icon="plus"
          :disabled="busy"
          @click="openSectionForm()"
        >
          {{ t('cms.sections.add') }}
        </BaseButton>
      </div>
      <p class="cms-page-editor__hint">{{ t('cms.sections.hint') }}</p>
      <SectionList
        v-if="sections.length"
        :sections="sections"
        :localized="localized"
        :busy="busy"
        @reorder="
          (ids) => attempt(() => editor.reorderSections(ids), 'cms.sections.toasts.reordered')
        "
        @toggle="
          (section, enabled) =>
            attempt(
              () => editor.toggleSection(section.id, enabled),
              enabled ? 'cms.sections.toasts.enabled' : 'cms.sections.toasts.disabled',
            )
        "
        @edit="openSectionForm"
        @items="openItems"
        @remove="removeSection"
      />
      <EmptyState
        v-else
        icon="doc"
        :title="t('cms.sections.empty.title')"
        :message="t('cms.sections.empty.message')"
      />
    </template>

    <SectionFormDialog
      v-model="sectionFormOpen"
      :section="editingSection"
      :save="saveSection"
      :upload="upload"
    />
    <SectionItemsDialog
      v-model="itemsOpen"
      :section="itemsSection()"
      :editor="editor"
      :upload="upload"
    />
    <PageDetailsDialog v-model="detailsOpen" :page="page" :save="saveDetails" />
  </div>
</template>
