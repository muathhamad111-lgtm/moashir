<script setup>
import CriteriaEvaluationCard from './CriteriaEvaluationCard.vue'
import CriteriaScoringCard from './CriteriaScoringCard.vue'
import { useEvaluationTypeSwitch } from '@/composables/useEvaluationTypeSwitch'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

// The evaluation tab of a main or sub-criterion: method (sub only) and scoring type.
const props = defineProps({
  item: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
  reload: { type: Function, required: true },
})

const { toggleType } = useEvaluationTypeSwitch()
</script>

<template>
  <div class="criteria-sheet__stack">
    <CriteriaEvaluationCard
      v-if="item.type === NODE.SUB"
      :sub="item.node"
      :can-edit="canEdit"
      @switch="toggleType(item.node, props.reload)"
    />
    <CriteriaScoringCard :item="item" :can-edit="canEdit" :reload="reload" />
  </div>
</template>
