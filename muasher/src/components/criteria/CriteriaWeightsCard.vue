<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import WeightDistributionEditor from './WeightDistributionEditor.vue'
import { useWeightDistribution } from '@/composables/useWeightDistribution'
import { sumWeights, weightBudget } from '@/utils/weight'
import { weightChanges } from '@/utils/criteriaOrder'

const props = defineProps({
  title: { type: String, required: true },
  parentWeight: { type: String, required: true },
  distributeEqually: { type: Boolean, default: false },
  items: { type: Array, required: true },
  saveMode: { type: Function, required: true },
  saveWeights: { type: Function, required: true },
  reload: { type: Function, required: true },
  level: { type: String, default: 'subs' },
})

const { t } = useI18n()
const edited = ref([])
const distribution = useWeightDistribution({
  saveParentMode: props.saveMode,
  saveChildWeights: props.saveWeights,
  reload: props.reload,
})

function reset() {
  edited.value = props.items.map((item) => ({ ...item }))
}

watch(() => props.items, reset, { immediate: true })

const mode = computed(() => (props.distributeEqually ? 'equal' : 'manual'))
const dirty = computed(() => weightChanges(edited.value, props.items).length > 0)
const overAllocated = computed(() => {
  const active = edited.value.filter((item) => item.active !== false).map((item) => item.weight)
  return weightBudget(props.parentWeight, sumWeights(active)).state === 'over'
})
</script>

<template>
  <Card :title="title">
    <WeightDistributionEditor
      v-model:children="edited"
      :mode="mode"
      :parent-weight="parentWeight"
      :level="level"
      :disabled="distribution.saving.value"
      @update:mode="distribution.changeMode"
    />
    <slot />
    <template v-if="mode === 'manual'" #footer>
      <div class="criteria-page__weight-actions">
        <BaseButton
          variant="ghost"
          :disabled="!dirty || distribution.saving.value"
          @click="reset"
          >{{ t('criteria.weights.reset') }}</BaseButton
        >
        <BaseButton
          variant="primary"
          :loading="distribution.saving.value"
          :disabled="!dirty || overAllocated"
          @click="distribution.saveWeights(edited, items)"
        >
          {{ t('criteria.weights.save') }}
        </BaseButton>
      </div>
    </template>
  </Card>
</template>
