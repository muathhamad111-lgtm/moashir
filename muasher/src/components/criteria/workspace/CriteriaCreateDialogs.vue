<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import MainCriterionForm from '@/components/criteria/MainCriterionForm.vue'
import SubCriterionForm from '@/components/criteria/SubCriterionForm.vue'
import GuidingPointForm from '@/components/criteria/GuidingPointForm.vue'
import { useUnsavedGuard } from '@/composables/useUnsavedGuard'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

const props = defineProps({
  editing: { type: Object, required: true },
})

const { t } = useI18n()
const creating = computed(() => props.editing.creating.value)
const parentNode = computed(() => creating.value?.parent?.node ?? null)

// Like the editor sheet: closing an add form (button, Esc, backdrop), a route change or a reload with
// entered data asks first. A save in flight is not dirty, so the form closes after it without asking.
const dirty = ref(false)
const { allowLeaving } = useUnsavedGuard(dirty, {
  onDiscard: () => (dirty.value = false),
  title: () => t('criteria.workspace.sheet.discardTitle'),
  message: () => t('criteria.workspace.sheet.discardMessage'),
  confirmLabel: () => t('criteria.workspace.sheet.discardConfirm'),
})

function isCreating(type) {
  return creating.value?.type === type
}

async function close(open) {
  if (open || !(await allowLeaving())) return
  dirty.value = false
  props.editing.cancelCreate()
}
</script>

<template>
  <MainCriterionForm
    v-if="isCreating(NODE.MAIN)"
    :model-value="true"
    wizard
    :save="editing.create"
    @update:model-value="close"
    @dirty="dirty = $event"
  />
  <SubCriterionForm
    v-if="isCreating(NODE.SUB)"
    :model-value="true"
    wizard
    :main="parentNode"
    :save="editing.create"
    @update:model-value="close"
    @dirty="dirty = $event"
  />
  <GuidingPointForm
    v-if="isCreating(NODE.POINT)"
    :model-value="true"
    :sub="parentNode"
    :save="editing.create"
    @update:model-value="close"
    @dirty="dirty = $event"
  />
</template>
