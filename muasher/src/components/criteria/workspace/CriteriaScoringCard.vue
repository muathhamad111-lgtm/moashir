<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseRadio from '@/components/form/BaseRadio.vue'
import { useCriteriaAction } from '@/composables/useCriteriaAction'
import { useScoringTypeOptions } from '@/composables/useScoringTypeOptions'
import { criteriaService } from '@/services/criteriaService'
import { scoresPoints } from '@/utils/criteriaTree'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

const props = defineProps({
  item: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
  reload: { type: Function, required: true },
})

const { t } = useI18n()
const { options } = useScoringTypeOptions()
const { run } = useCriteriaAction(() => props.reload())

const FIELDS = {
  main: 'defaultScoringType',
  points: 'defaultPointScoringType',
  direct: 'scoringType',
}

const node = computed(() => props.item.node)
const mode = computed(() => {
  if (props.item.type === NODE.MAIN) return 'main'
  return scoresPoints(node.value) ? 'points' : 'direct'
})
const current = computed(() => node.value[FIELDS[mode.value]])
const chosen = ref(current.value)

watch(current, (value) => (chosen.value = value))

function send(payload) {
  if (mode.value === 'main') return criteriaService.updateMain(node.value.id, payload)
  return criteriaService.updateSub(node.value.id, payload)
}

function save() {
  return run(() => send({ [FIELDS[mode.value]]: chosen.value }), {
    success: 'criteria.workspace.scoring.saved',
  })
}

function applyToAll() {
  return run(() => criteriaService.applyPointScoringType(node.value.id, chosen.value), {
    success: 'criteria.workspace.scoring.applied',
  })
}
</script>

<template>
  <Card :title="t(`criteria.workspace.scoring.title.${mode}`)">
    <p class="criteria-details__note">{{ t(`criteria.workspace.scoring.hint.${mode}`) }}</p>
    <BaseRadio
      v-model="chosen"
      :name="`scoringType-${item.key}`"
      :label="t(`criteria.workspace.scoring.label.${mode}`)"
      :options="options"
      :disabled="!canEdit"
    />
    <template v-if="canEdit" #footer>
      <div class="criteria-details__add">
        <BaseButton variant="primary" @click="save">{{ t('actions.save') }}</BaseButton>
        <BaseButton v-if="mode === 'points'" variant="secondary" @click="applyToAll">
          {{ t('criteria.workspace.scoring.applyToAll') }}
        </BaseButton>
      </div>
    </template>
  </Card>
</template>
