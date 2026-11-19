<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import SubCriterionForm from '@/components/criteria/SubCriterionForm.vue'
import CriteriaWeightsCard from '@/components/criteria/CriteriaWeightsCard.vue'
import WeightBudget from '@/components/criteria/WeightBudget.vue'
import CriteriaEvaluationCard from './CriteriaEvaluationCard.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaAction } from '@/composables/useCriteriaAction'
import { useEvaluationTypeSwitch } from '@/composables/useEvaluationTypeSwitch'
import { criteriaService } from '@/services/criteriaService'
import { scoresPoints } from '@/utils/criteriaTree'
import { compareWeights } from '@/utils/weight'

const props = defineProps({
  item: { type: Object, required: true },
  main: { type: Object, required: true },
  children: { type: Array, required: true },
  canEdit: { type: Boolean, default: false },
  saveNode: { type: Function, required: true },
  reload: { type: Function, required: true },
})

const emit = defineEmits(['add'])
const { t } = useI18n()
const { localized } = useCriteria()
const { toggleType } = useEvaluationTypeSwitch()
const { run } = useCriteriaAction(() => props.reload())

const sub = computed(() => props.item.node)
const onPoints = computed(() => scoresPoints(sub.value))
const weightItems = computed(() =>
  props.children.map(({ node }) => ({
    id: node.id,
    label: localized(node.description),
    weight: node.weight,
    active: node.active,
  })),
)
const isFull = computed(
  () => !sub.value.distributeEqually && compareWeights(sub.value.remainingPointWeight, '0') <= 0,
)

const saveMode = (distributeEqually) =>
  criteriaService.updateSub(sub.value.id, { distributeEqually })
const saveWeights = (weights) => criteriaService.savePointWeights(sub.value.id, weights)

async function shareAndAdd() {
  const shared = await run(() => saveMode(true), { success: 'criteria.weights.modeSaved' })
  if (shared) emit('add')
}
</script>

<template>
  <SubCriterionForm
    :model-value="true"
    inline
    :readonly="!canEdit"
    :criterion="sub"
    :main="main"
    :save="saveNode"
  />

  <CriteriaEvaluationCard :sub="sub" :can-edit="canEdit" @switch="toggleType(sub, reload)" />

  <template v-if="onPoints">
    <CriteriaWeightsCard
      v-if="canEdit && children.length"
      level="points"
      :title="t('criteria.workspace.details.pointWeightsTitle')"
      :parent-weight="sub.weight"
      :distribute-equally="sub.distributeEqually"
      :items="weightItems"
      :save-mode="saveMode"
      :save-weights="saveWeights"
      :reload="reload"
    />
    <WeightBudget
      v-else
      :capacity="sub.weight"
      :allocated="sub.allocatedPointWeight"
      :capacity-label="t('criteria.budget.subCapacity')"
      :equal-distribution="sub.distributeEqually"
    />
    <div v-if="canEdit" class="criteria-details__add">
      <p v-if="isFull" class="criteria-details__note" role="note">
        {{ t('criteria.workspace.details.subFull') }}
      </p>
      <BaseButton v-if="isFull" variant="secondary" icon="plus" @click="shareAndAdd">
        {{ t('criteria.workspace.details.shareAndAddPoint') }}
      </BaseButton>
      <BaseButton v-else variant="secondary" icon="plus" @click="emit('add')">
        {{ t('criteria.workspace.details.addPoint') }}
      </BaseButton>
    </div>
  </template>
</template>
