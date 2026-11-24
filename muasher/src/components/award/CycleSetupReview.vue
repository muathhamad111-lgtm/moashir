<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import CycleActionsCard from './CycleActionsCard.vue'
import CycleSetupActions from './CycleSetupActions.vue'
import CycleSetupSummary from './CycleSetupSummary.vue'
import { useCycleSummary } from '@/composables/useCycleSummary'
import { CYCLE_STATUSES } from '@/constants/advanced'

const props = defineProps({
  cycle: { type: Object, required: true },
  regionsTotal: { type: Number, required: true },
  transitions: { type: Object, required: true },
  canReopen: { type: Boolean, default: false },
})

const emit = defineEmits(['edit', 'previous', 'reopen', 'save-draft'])
const { t } = useI18n()
const sections = useCycleSummary(
  () => props.cycle,
  () => props.regionsTotal,
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
    <CycleActionsCard
      :cycle="cycle"
      :busy="transitions.busy.value"
      :open-block="cycle.openBlock"
      :blocked="transitions.blocked.value"
      :can-reopen="canReopen"
      @run="transitions.confirmAndRun"
      @reopen="emit('reopen')"
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
    </CycleSetupActions>
  </div>
</template>
