<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import CycleSetupActions from './CycleSetupActions.vue'
import CycleSetupSummary from './CycleSetupSummary.vue'
import { useCycleSummary } from '@/composables/useCycleSummary'
import { CYCLE_STATUSES } from '@/constants/advanced'

const props = defineProps({
  cycle: { type: Object, required: true },
  regionsTotal: { type: Number, required: true },
  committee: { type: Array, default: null },
})

const emit = defineEmits(['edit', 'previous', 'save-draft', 'finish'])
const { t } = useI18n()
const sections = useCycleSummary(
  () => props.cycle,
  () => props.regionsTotal,
  () => props.committee,
)
const savesDraft = computed(
  () => props.cycle.canManage && props.cycle.status === CYCLE_STATUSES.DRAFT,
)
</script>

<template>
  <div class="cycle-setup__review">
    <CycleSetupSummary
      :sections="sections"
      :editable="cycle.canManage && cycle.status !== CYCLE_STATUSES.CLOSED"
      @edit="emit('edit', $event)"
    />
    <CycleSetupActions @previous="emit('previous')">
      <BaseButton
        v-if="savesDraft"
        variant="secondary"
        icon="save"
        data-testid="cycle-save-draft"
        @click="emit('save-draft')"
      >
        {{ t('cycles.setup.saveDraft') }}
      </BaseButton>
      <BaseButton
        v-else
        variant="secondary"
        icon="eye"
        data-testid="cycle-finish"
        @click="emit('finish')"
      >
        {{ t('cycles.setup.backToCycle') }}
      </BaseButton>
    </CycleSetupActions>
  </div>
</template>
