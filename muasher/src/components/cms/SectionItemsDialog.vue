<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import SortableList from '@/components/ui/SortableList.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import Badge from '@/components/ui/Badge.vue'
import ItemFormDialog from './ItemFormDialog.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import AppIcon from '@/components/ui/AppIcon.vue'
import { CMS_LOGO_SECTION_TYPES } from '@/constants/cms'
import { contentImageUrl } from '@/utils/contentImage'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  section: { type: Object, default: null },
  editor: { type: Object, required: true },
  upload: { type: Function, required: true },
  uploadLogo: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const { confirm } = useConfirm()
const toast = useToast()

const items = computed(() => [...(props.section?.items ?? [])].sort((a, b) => a.order - b.order))
const isLogoSection = computed(() => CMS_LOGO_SECTION_TYPES.includes(props.section?.type))
const hasUnpublishedChanges = computed(() =>
  Boolean(props.editor.page.value?.hasUnpublishedChanges),
)
const formOpen = ref(false)
const editing = ref(null)

function labelOf(item) {
  return props.editor.localized(item.title) || t('cms.items.untitled')
}

function openForm(item = null) {
  editing.value = item
  formOpen.value = true
}

async function save(payload) {
  const sectionId = props.section.id
  if (editing.value) await props.editor.updateItem(sectionId, editing.value.id, payload)
  else await props.editor.createItem(sectionId, payload)
  toast.success(t('cms.items.toasts.saved'))
}

async function attempt(request, successKey) {
  try {
    await request()
    toast.success(t(successKey))
  } catch (error) {
    toast.error(error?.normalized?.message || t('cms.toasts.failed'))
  }
}

function toggle(item, enabled) {
  return attempt(
    () => props.editor.toggleItem(props.section.id, item.id, enabled),
    enabled ? 'cms.items.toasts.enabled' : 'cms.items.toasts.disabled',
  )
}

function reorder(orderedIds) {
  return attempt(
    () => props.editor.reorderItems(props.section.id, orderedIds),
    'cms.items.toasts.reordered',
  )
}

async function remove(item) {
  const accepted = await confirm({
    title: t('cms.items.confirmRemove.title'),
    message: labelOf(item),
    confirmLabel: t('cms.items.remove'),
    variant: 'danger',
  })
  if (accepted)
    await attempt(
      () => props.editor.removeItem(props.section.id, item.id),
      'cms.items.toasts.removed',
    )
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="lg"
    :title="
      section
        ? t('cms.items.title', { section: editor.localized(section.title) || section.key })
        : ''
    "
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="cms-items" :aria-busy="editor.busy.value || undefined">
      <p class="cms-items__hint">
        {{ isLogoSection ? t('cms.partners.itemsHint') : t('cms.items.hint') }}
      </p>
      <div class="cms-items__draft" role="note">
        <AppIcon name="info-circle" />
        <span class="cms-items__draft-text">{{ t('cms.items.draftNotice') }}</span>
        <Badge v-if="hasUnpublishedChanges" variant="info">{{
          t('cms.items.unpublishedChanges')
        }}</Badge>
      </div>
      <SortableList
        v-if="items.length"
        :items="items"
        :label-of="labelOf"
        :list-label="t('cms.items.listLabel')"
        :disabled="editor.busy.value"
        @reorder="reorder"
      >
        <template #item="{ item }">
          <div class="cms-items__row">
            <template v-if="isLogoSection">
              <img
                v-if="item.image"
                class="cms-items__logo"
                :src="contentImageUrl(item.image)"
                alt=""
              />
              <span v-else class="cms-items__logo cms-items__logo--missing" aria-hidden="true"
                ><AppIcon name="warning-triangle"
              /></span>
            </template>
            <span v-else-if="item.icon" class="cms-items__icon" aria-hidden="true"
              ><AppIcon :name="item.icon"
            /></span>
            <span class="cms-items__name">{{ labelOf(item) }}</span>
            <Badge v-if="isLogoSection && !item.image" variant="warning">{{
              t('cms.partners.noLogo')
            }}</Badge>
            <ToggleSwitch
              :model-value="item.enabled"
              :name="`item-enabled-${item.id}`"
              :label="item.enabled ? t('cms.enabled') : t('cms.disabled')"
              :disabled="editor.busy.value"
              @update:model-value="toggle(item, $event)"
            />
            <BaseButton
              variant="secondary"
              size="sm"
              icon="pen"
              :disabled="editor.busy.value"
              @click="openForm(item)"
            >
              {{ t('actions.edit') }}
            </BaseButton>
            <BaseButton
              variant="ghost"
              size="sm"
              icon-only
              icon="trash"
              :aria-label="t('cms.items.removeNamed', { name: labelOf(item) })"
              :disabled="editor.busy.value"
              @click="remove(item)"
            />
          </div>
        </template>
      </SortableList>
      <EmptyState
        v-else
        icon="layers"
        :title="t('cms.items.empty.title')"
        :message="t('cms.items.empty.message')"
      />
      <div class="cms-items__footer">
        <BaseButton
          variant="primary"
          size="sm"
          icon="plus"
          :disabled="editor.busy.value"
          @click="openForm()"
        >
          {{ isLogoSection ? t('cms.partners.add') : t('cms.items.add') }}
        </BaseButton>
      </div>
    </div>
    <ItemFormDialog
      v-if="section"
      v-model="formOpen"
      :item="editing"
      :section-type="section.type"
      :save="save"
      :upload="isLogoSection ? uploadLogo : upload"
    />
  </BaseModal>
</template>
