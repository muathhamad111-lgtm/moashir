<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import SortableList from '@/components/ui/SortableList.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ItemFormDialog from './ItemFormDialog.vue'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  section: { type: Object, default: null },
  editor: { type: Object, required: true },
  upload: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const { confirm } = useConfirm()
const toast = useToast()

const items = computed(() => [...(props.section?.items ?? [])].sort((a, b) => a.order - b.order))
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
    message: t('cms.items.confirmRemove.message', { name: labelOf(item) }),
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
      <p class="cms-items__hint">{{ t('cms.items.hint') }}</p>
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
            <span v-if="item.icon" class="cms-items__icon" aria-hidden="true"
              ><AppIcon :name="item.icon"
            /></span>
            <span class="cms-items__name">{{ labelOf(item) }}</span>
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
          {{ t('cms.items.add') }}
        </BaseButton>
      </div>
    </div>
    <ItemFormDialog
      v-if="section"
      v-model="formOpen"
      :item="editing"
      :section-type="section.type"
      :save="save"
      :upload="upload"
    />
  </BaseModal>
</template>
