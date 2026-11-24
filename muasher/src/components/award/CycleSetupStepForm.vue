<script setup>
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import CycleBasicsFields from './CycleBasicsFields.vue'
import CycleWindowFields from './CycleWindowFields.vue'
import CycleConditionFields from './CycleConditionFields.vue'
import CycleDeadlineFields from './CycleDeadlineFields.vue'
import CycleFeeFields from './CycleFeeFields.vue'
import CycleLockNotes from './CycleLockNotes.vue'
import CycleSetupActions from './CycleSetupActions.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useCycleStepSave } from '@/composables/useCycleStepSave'
import { CREATING_STEP, lockReasons, stepSchema, stepValues } from '@/utils/award/cycleSetup'

const props = defineProps({
  step: { type: String, required: true },
  cycle: { type: Object, default: null },
  values: { type: Object, required: true },
  locked: { type: Object, required: true },
  regions: { type: Array, required: true },
  canSetParticipation: { type: Boolean, default: false },
  editable: { type: Boolean, default: true },
  reasonExtra: { type: Function, required: true },
  serverErrors: { type: Object, default: () => ({}) },
  showsPrevious: { type: Boolean, default: true },
})

const emit = defineEmits(['next', 'created', 'stash', 'previous', 'dirty', 'locked', 'rejected'])
const { t } = useI18n()
const save = useCycleStepSave({ props, emit })

const form = useValidatedForm({
  schema: computed(() => stepSchema(t, props.step)),
  initialValues: stepValues(props.values, props.step),
  action: `cycles.setup.${props.step}`,
  submit: save,
})

const notes = computed(() => lockReasons(props.step, { cycle: props.cycle, locked: props.locked }))
const nextLabel = computed(() => {
  if (!props.editable) return t('cycles.setup.next')
  if (!props.cycle)
    return t(props.step === CREATING_STEP ? 'cycles.setup.createDraft' : 'cycles.setup.next')
  return t('cycles.setup.saveAndNext')
})

function submit() {
  if (props.editable) form.onSubmit()
  else emit('next', null)
}

watch(
  () => form.meta.value.dirty,
  (dirty) => emit('dirty', dirty),
  { immediate: true },
)

onMounted(() => {
  if (Object.keys(props.serverErrors).length) form.setErrors(props.serverErrors)
})
</script>

<template>
  <form class="cycle-form cycle-setup__form" :data-step="step" novalidate @submit.prevent="submit">
    <CycleLockNotes :reasons="notes" />
    <CycleBasicsFields v-if="step === 'identity'" :locked="locked" />
    <CycleWindowFields v-else-if="step === 'dates'" :locked="locked" />
    <CycleConditionFields
      v-else-if="step === 'conditions'"
      :locked="locked"
      :regions="regions"
      :locked-region-ids="cycle?.lockedRegionIds ?? []"
      :can-set-participation="canSetParticipation && editable"
      :threshold-locked="cycle?.thresholdLocked === true"
    />
    <CycleDeadlineFields v-else-if="step === 'deadlines'" :locked="locked" />
    <CycleFeeFields v-else :locked="locked" />
    <p v-if="form.formError.value" class="cycle-setup__error" role="alert">
      {{ form.formError.value }}
    </p>
    <CycleSetupActions
      :shows-previous="showsPrevious"
      :next-label="nextLabel"
      next-type="submit"
      :busy="form.submitting.value"
      @previous="emit('previous')"
    />
  </form>
</template>
