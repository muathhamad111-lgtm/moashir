<script setup>
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import SortableList from '@/components/ui/SortableList.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { useKpiReorder } from '@/composables/useKpiReorder'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  localized: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const toast = useToast()
const { entries, status, saving, changed, load, arrange, save } = useKpiReorder()

function labelOf(entry) {
  return props.localized(entry.name)
}

async function onSave() {
  try {
    await save()
    toast.success(t('kpis.admin.toasts.reordered'))
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    toast.error(error?.normalized?.message || t('kpis.admin.toasts.failed'))
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (open) load()
  },
)
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="md"
    :title="t('kpis.admin.reorder.title')"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="kpi-reorder">
      <p class="kpi-reorder__hint">{{ t('kpis.admin.reorder.hint') }}</p>
      <Skeleton v-if="status === 'loading'" variant="text" :count="6" />
      <ErrorState
        v-else-if="status === 'error'"
        :message="t('kpis.admin.reorder.loadError')"
        @retry="load"
      />
      <SortableList
        v-else-if="entries.length"
        :items="entries"
        :label-of="labelOf"
        :list-label="t('kpis.admin.reorder.listLabel')"
        :disabled="saving"
        @reorder="arrange"
      >
        <template #item="{ item, index }">
          <div class="kpi-reorder__row">
            <span class="kpi-reorder__position">{{ index + 1 }}</span>
            <span class="kpi-reorder__name">{{ labelOf(item) }}</span>
            <StatusBadge :status="item.published ? 'published' : 'unpublished'" />
          </div>
        </template>
      </SortableList>
      <div class="kpi-reorder__footer">
        <BaseButton
          variant="secondary"
          size="sm"
          :disabled="saving"
          @click="emit('update:modelValue', false)"
          >{{ t('actions.cancel') }}</BaseButton
        >
        <BaseButton
          variant="primary"
          size="sm"
          icon="save"
          :loading="saving"
          :disabled="!changed"
          @click="onSave"
        >
          {{ t('kpis.admin.reorder.save') }}
        </BaseButton>
      </div>
    </div>
  </BaseModal>
</template>
