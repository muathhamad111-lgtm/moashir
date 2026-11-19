<script setup>
import { computed } from 'vue'
import MainCriterionForm from '@/components/criteria/MainCriterionForm.vue'
import SubCriterionForm from '@/components/criteria/SubCriterionForm.vue'
import GuidingPointForm from '@/components/criteria/GuidingPointForm.vue'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

const props = defineProps({
  editing: { type: Object, required: true },
})

const creating = computed(() => props.editing.creating.value)
const parentNode = computed(() => creating.value?.parent?.node ?? null)

function isCreating(type) {
  return creating.value?.type === type
}

function close(open) {
  if (!open) props.editing.cancelCreate()
}
</script>

<template>
  <MainCriterionForm
    :model-value="isCreating(NODE.MAIN)"
    :save="editing.create"
    @update:model-value="close"
  />
  <SubCriterionForm
    v-if="isCreating(NODE.SUB)"
    :model-value="true"
    :main="parentNode"
    :save="editing.create"
    @update:model-value="close"
  />
  <GuidingPointForm
    v-if="isCreating(NODE.POINT)"
    :model-value="true"
    :sub="parentNode"
    :save="editing.create"
    @update:model-value="close"
  />
</template>
