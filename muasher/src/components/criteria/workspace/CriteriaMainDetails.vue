<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import MainCriterionForm from '@/components/criteria/MainCriterionForm.vue'
import CriteriaWeightsCard from '@/components/criteria/CriteriaWeightsCard.vue'
import WeightBudget from '@/components/criteria/WeightBudget.vue'
import CriteriaScoringCard from './CriteriaScoringCard.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaAction } from '@/composables/useCriteriaAction'
import { criteriaService } from '@/services/criteriaService'
import { compareWeights } from '@/utils/weight'

const props = defineProps({
  item: { type: Object, required: true },
  children: { type: Array, required: true },
  canEdit: { type: Boolean, default: false },
  saveNode: { type: Function, required: true },
  reload: { type: Function, required: true },
})

const emit = defineEmits(['add'])
const { t } = useI18n()
const { localized } = useCriteria()
const { run } = useCriteriaAction(() => props.reload())

const main = computed(() => props.item.node)
const weightItems = computed(() =>
  props.children.map(({ node }) => ({
    id: node.id,
    label: localized(node.name),
    weight: node.weight,
    active: node.active,
  })),
)
const isFull = computed(
  () => !main.value.distributeEqually && compareWeights(main.value.remainingWeight, '0') <= 0,
)

const saveMode = (distributeEqually) =>
  criteriaService.updateMain(main.value.id, { distributeEqually })
const saveWeights = (weights) => criteriaService.saveSubWeights(main.value.id, weights)

async function shareAndAdd() {
  const shared = await run(() => saveMode(true), { success: 'criteria.weights.modeSaved' })
  if (shared) emit('add')
}
</script>

<template>
  <MainCriterionForm
    :model-value="true"
    inline
    :readonly="!canEdit"
    :criterion="main"
    :save="saveNode"
  />

  <CriteriaScoringCard :item="item" :can-edit="canEdit" :reload="reload" />

  <CriteriaWeightsCard
    v-if="canEdit && children.length"
    :title="t('criteria.workspace.details.subWeightsTitle')"
    :parent-weight="main.weight"
    :distribute-equally="main.distributeEqually"
    :items="weightItems"
    :save-mode="saveMode"
    :save-weights="saveWeights"
    :reload="reload"
  />
  <WeightBudget
    v-else
    :capacity="main.weight"
    :allocated="main.allocatedWeight"
    :capacity-label="t('criteria.budget.mainCapacity')"
    :equal-distribution="main.distributeEqually"
  />

  <div v-if="canEdit" class="criteria-details__add">
    <p v-if="isFull" class="criteria-details__note" role="note">
      {{ t('criteria.workspace.details.mainFull') }}
    </p>
    <BaseButton v-if="isFull" variant="secondary" icon="plus" @click="shareAndAdd">
      {{ t('criteria.workspace.details.shareAndAddSub') }}
    </BaseButton>
    <BaseButton v-else variant="secondary" icon="plus" @click="emit('add')">
      {{ t('criteria.main.addSub') }}
    </BaseButton>
  </div>
</template>
