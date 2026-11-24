<script setup>
import { computed, provide, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import WizardStepper from '@/components/wizard/WizardStepper.vue'
import CycleSetupStepForm from './CycleSetupStepForm.vue'
import CycleSetupActions from './CycleSetupActions.vue'
import CycleSetupReview from './CycleSetupReview.vue'
import CycleStaffPanel from './CycleStaffPanel.vue'
import CycleStepCounter from './CycleStepCounter.vue'
import { useCycleSetup } from '@/composables/useCycleSetup'
import { useCycleStaff } from '@/composables/useCycleStaff'
import { useCycleCommittee } from '@/composables/useCycleCommittee'
import { useCycleCommitteeSelection } from '@/composables/useCycleCommitteeSelection'
import { useCycleAuthority } from '@/composables/useCycleAuthority'
import { useToast } from '@/composables/useToast'
import { CYCLE_STATUSES } from '@/constants/advanced'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { SETUP_STEPS, SETUP_STEP_FIELDS, STEP_COUNTER, isFormStep } from '@/utils/award/cycleSetup'

const props = defineProps({
  editor: { type: Object, required: true },
  reasonExtra: { type: Function, required: true },
})

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const toast = useToast()
const { cycle, regions } = props.editor
const setup = useCycleSetup(cycle, route.query.step)
const roster = useCycleCommittee(cycle)
const committee = useCycleCommitteeSelection({ cycle, roster })
const staff = useCycleStaff({ editor: props.editor, reasonExtra: props.reasonExtra, committee })
const { isSystemAdministrator, canCreate } = useCycleAuthority()

const editable = computed(() =>
  cycle.value
    ? cycle.value.canManage && cycle.value.status !== CYCLE_STATUSES.CLOSED
    : canCreate.value,
)
const locked = computed(() =>
  editable.value ? props.editor.locked.value : new Set(Object.values(SETUP_STEP_FIELDS).flat()),
)
const values = computed(() => ({ ...props.editor.initialValues.value, ...setup.pending.value }))
const formKey = computed(() => `${setup.stepKey.value}:${cycle.value?.updatedAt ?? 'new'}`)
const onTeam = computed(() => setup.stepKey.value === 'team')

provide(
  STEP_COUNTER,
  computed(() =>
    t('cycles.setup.counter', { step: setup.at.value + 1, total: SETUP_STEPS.length }),
  ),
)

watch(
  () => onTeam.value && staff.dirty.value,
  (dirty) => {
    if (onTeam.value) setup.dirty.value = dirty
  },
)
watch(onTeam, (now, before) => {
  if (before && !now) staff.reset()
})

async function saveTeam() {
  if (!editable.value || (await staff.saveAll())) setup.next()
}

function onNext(detail) {
  if (detail) props.editor.apply(detail)
  setup.next()
}

function onCreated(detail) {
  props.editor.apply(detail)
  setup.created()
  router.replace({ name: ROUTE_NAMES.AWARD_CYCLE_SETUP, params: { id: detail.id } })
}

function showCycle() {
  router.push({ name: ROUTE_NAMES.AWARD_CYCLE, params: { id: cycle.value.id } })
}

function saveDraft() {
  toast.success(t('cycles.setup.draftSaved'))
  showCycle()
}
</script>

<template>
  <div class="cycle-setup__wizard">
    <WizardStepper
      class="cycle-setup__stepper"
      :steps="setup.stepItems.value"
      :current-index="setup.at.value"
      @select="setup.goTo"
    />
    <Card v-if="isFormStep(setup.stepKey.value)" class="cycle-setup__card">
      <CycleSetupStepForm
        :key="formKey"
        :step="setup.stepKey.value"
        :cycle="cycle"
        :values="values"
        :locked="locked"
        :regions="regions"
        :can-set-participation="isSystemAdministrator"
        :editable="editable"
        :reason-extra="reasonExtra"
        :server-errors="setup.stepKey.value === 'identity' ? setup.identityErrors.value : {}"
        :shows-previous="setup.at.value > 0"
        @next="onNext"
        @created="onCreated"
        @stash="setup.stash"
        @rejected="setup.reject"
        @previous="setup.previous"
        @dirty="setup.dirty.value = $event"
        @locked="editor.lockFields"
      />
    </Card>
    <div v-else-if="onTeam" class="cycle-setup__team">
      <CycleStaffPanel
        v-if="cycle.revealsStaff"
        :staff="staff"
        :committee="committee"
        :editable="editable"
      />
      <template v-else>
        <h2 class="cycle-summary__title">
          {{ t('cycles.setup.steps.team') }}
          <CycleStepCounter />
        </h2>
        <p class="cycle-form__note">{{ t('cycles.setup.team.hidden') }}</p>
      </template>
      <CycleSetupActions
        :next-label="editable ? t('cycles.setup.saveAndNext') : t('cycles.setup.next')"
        :busy="staff.saving.value"
        @previous="setup.previous"
        @next="saveTeam"
      />
    </div>
    <CycleSetupReview
      v-else
      :cycle="cycle"
      :regions-total="regions.length"
      :committee="roster.names.value"
      @edit="setup.goToStep"
      @previous="setup.previous"
      @save-draft="saveDraft"
      @finish="showCycle"
    />
  </div>
</template>
