<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import CriteriaWeightsCard from '@/components/criteria/CriteriaWeightsCard.vue'
import WeightBudget from '@/components/criteria/WeightBudget.vue'
import CriteriaShare from './CriteriaShare.vue'
import { useCriteria } from '@/composables/useCriteria'
import { criteriaService } from '@/services/criteriaService'
import { nodeLabel, scoresPoints } from '@/utils/criteriaTree'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

// The weight tab: the item's own share, and the split over its children (edited here, in one place).
const props = defineProps({
  item: { type: Object, required: true },
  index: { type: Map, required: true },
  modelWeight: { type: String, required: true },
  canEdit: { type: Boolean, default: false },
  reload: { type: Function, required: true },
})

const emit = defineEmits(['open'])
const { t } = useI18n()
const { localized } = useCriteria()

const node = computed(() => props.item.node)
const parent = computed(() => props.index.get(props.item.parentKey) ?? null)
const isMain = computed(() => props.item.type === NODE.MAIN)
const splitsChildren = computed(
  () => isMain.value || (props.item.type === NODE.SUB && scoresPoints(node.value)),
)
const children = computed(() => props.item.children.map((key) => props.index.get(key)))
const weightItems = computed(() =>
  children.value.map((child) => ({
    id: child.node.id,
    label: localized(nodeLabel(child)),
    weight: child.node.weight,
    active: child.node.active,
  })),
)

const LEVELS = {
  [NODE.MAIN]: {
    title: 'criteria.workspace.details.subWeightsTitle',
    level: 'subs',
    capacity: 'criteria.budget.mainCapacity',
    allocated: 'allocatedWeight',
  },
  [NODE.SUB]: {
    title: 'criteria.workspace.details.pointWeightsTitle',
    level: 'points',
    capacity: 'criteria.budget.subCapacity',
    allocated: 'allocatedPointWeight',
  },
}
const level = computed(() => LEVELS[props.item.type])

function saveMode(distributeEqually) {
  const update = isMain.value ? criteriaService.updateMain : criteriaService.updateSub
  return update(node.value.id, { distributeEqually })
}

function saveWeights(weights) {
  return isMain.value
    ? criteriaService.saveSubWeights(node.value.id, weights)
    : criteriaService.savePointWeights(node.value.id, weights)
}
</script>

<template>
  <div class="criteria-sheet__stack">
    <div class="criteria-sheet__facts">
      <CriteriaShare
        :weight="node.weight"
        :parent-weight="parent ? parent.node.weight : modelWeight"
        :caption="
          parent
            ? t('criteria.workspace.board.shareOfParent')
            : t('criteria.workspace.board.shareOfModel')
        "
      />
    </div>

    <div v-if="parent && canEdit" class="criteria-sheet__note" role="note">
      <AppIcon name="info-circle" />
      <span>{{ t('criteria.workspace.sheet.weightInParent') }}</span>
      <BaseButton variant="secondary" size="sm" @click="emit('open', parent.key, 'weight')">{{
        t('criteria.workspace.sheet.openParent', { name: localized(nodeLabel(parent)) })
      }}</BaseButton>
    </div>

    <template v-if="splitsChildren">
      <CriteriaWeightsCard
        v-if="canEdit && children.length"
        :level="level.level"
        :title="t(level.title)"
        :parent-weight="node.weight"
        :distribute-equally="node.distributeEqually"
        :items="weightItems"
        :save-mode="saveMode"
        :save-weights="saveWeights"
        :reload="reload"
      />
      <WeightBudget
        v-else
        :capacity="node.weight"
        :allocated="node[level.allocated]"
        :capacity-label="t(level.capacity)"
        :equal-distribution="node.distributeEqually"
      />
    </template>
  </div>
</template>
