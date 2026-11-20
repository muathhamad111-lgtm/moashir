<script setup>
import { computed } from 'vue'
import MainCriterionForm from '@/components/criteria/MainCriterionForm.vue'
import SubCriterionForm from '@/components/criteria/SubCriterionForm.vue'
import GuidingPointForm from '@/components/criteria/GuidingPointForm.vue'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

// The one edit form of the selected criterion, shown section by section by the sheet's tabs.
const props = defineProps({
  item: { type: Object, required: true },
  index: { type: Map, required: true },
  section: { type: String, required: true },
  saveNode: { type: Function, required: true },
})

const emit = defineEmits(['section-errors', 'dirty'])
const parent = computed(() => props.index.get(props.item.parentKey)?.node ?? null)
</script>

<template>
  <MainCriterionForm
    v-if="item.type === NODE.MAIN"
    :model-value="true"
    inline
    :criterion="item.node"
    :section="section"
    :save="saveNode"
    @section-errors="emit('section-errors', $event)"
    @dirty="emit('dirty', $event)"
  />
  <SubCriterionForm
    v-else-if="item.type === NODE.SUB"
    :model-value="true"
    inline
    :criterion="item.node"
    :main="parent"
    :section="section"
    :save="saveNode"
    @section-errors="emit('section-errors', $event)"
    @dirty="emit('dirty', $event)"
  />
  <GuidingPointForm
    v-else
    :model-value="true"
    inline
    :point="item.node"
    :sub="parent"
    :section="section"
    :save="saveNode"
    @section-errors="emit('section-errors', $event)"
    @dirty="emit('dirty', $event)"
  />
</template>
