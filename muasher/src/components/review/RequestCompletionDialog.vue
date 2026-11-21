<script setup>
import { computed, reactive, watch } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FormSteps from '@/components/modal/FormSteps.vue'
import CompletionItemPicker from './CompletionItemPicker.vue'
import CompletionSelectionSummary from './CompletionSelectionSummary.vue'
import CompletionReviewStep from './CompletionReviewStep.vue'
import CompletionMessageStep from './CompletionMessageStep.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useFormSteps } from '@/composables/useFormSteps'
import { assessorReviewService } from '@/services/assessorReviewService'
import { ACCREDITED_LIMITS, EXPIRY_OUTCOMES } from '@/constants/accredited'
import {
  completionPayload,
  completionSchema,
  completionStepOf,
  deadlineBounds,
  itemOptions,
  suggestedItemKeys,
} from '@/utils/accredited/completionForm'
import { formatRiyadhDate } from '@/utils/datetime'

// A completion request as a side sheet in three steps (design §B.3.3): the items (the weak ones
// suggested), the message and the deadline, then a review of what the entity will receive.
const props = defineProps({
  modelValue: { type: Boolean, required: true },
  assessmentId: { type: Number, required: true },
  criteria: { type: Object, required: true },
  askReason: { type: Function, required: true },
  send: { type: Function, default: null },
  bounds: { type: Object, default: null },
  variant: { type: String, default: 'assessor' },
  // The criterion the assessor is on opens first in the picker.
  activeMainId: { type: Number, default: null },
  // The saved scores: items not achieved or below 50% are suggested.
  scores: { type: Object, default: null },
})

const STEPS = ['items', 'message', 'review']
const STEP_FIELDS = { items: ['items'], message: ['notes', 'deadline', 'expiryOutcome'] }

const emit = defineEmits(['update:modelValue', 'done'])
const { t, locale } = useI18n()
const bounds = props.bounds ?? deadlineBounds()
const copy = props.variant === 'follower' ? 'requests.return' : 'review.completion'
const maxLabel = computed(() => formatRiyadhDate(`${bounds.max}T12:00:00+03:00`, locale.value))
const deadlineErrorKey =
  props.variant === 'follower'
    ? 'requests.return.deadlineError'
    : 'review.completion.validation.deadline'
const notes = reactive({})
const suggested = computed(() => suggestedItemKeys(props.criteria, props.scores))
const initialValues = () => ({
  notes: '',
  deadline: '',
  expiryOutcome: EXPIRY_OUTCOMES.CONTINUE,
  items: [...suggested.value],
})

const form = useValidatedForm({
  schema: computed(() =>
    completionSchema(
      t,
      bounds,
      t(deadlineErrorKey, {
        days: ACCREDITED_LIMITS.COMPLETION_DEADLINE_MAX_DAYS,
        date: maxLabel.value,
      }),
    ),
  ),
  initialValues: initialValues(),
  action: 'review.requestCompletion',
  submit: async (values) => {
    const reason = await props.askReason()
    const payload = { ...completionPayload(values, notes), ...(reason ? { reason } : {}) }
    const send = props.send ?? assessorReviewService.requestCompletion
    const { data } = await send(props.assessmentId, payload)
    emit('update:modelValue', false)
    emit('done', data)
  },
})
const steps = useFormSteps(form, { steps: STEPS, stepFields: STEP_FIELDS })
const { value: items, errorMessage: itemsError } = useField('items')
const { value: deadline, errorMessage: deadlineError } = useField('deadline')
const { value: message } = useField('notes')
const { value: expiryOutcome } = useField('expiryOutcome')

const allOptions = computed(() => itemOptions(props.criteria))
const groups = computed(() =>
  props.criteria.mains.map((main) => ({
    main,
    options: allOptions.value.filter((option) =>
      option.sub ? main.subs.some((sub) => sub.id === option.sub.id) : option.main.id === main.id,
    ),
  })),
)
const selectedOptions = computed(() =>
  allOptions.value.filter((option) => (items.value ?? []).includes(option.key)),
)
const outcomeOptions = computed(() =>
  Object.values(EXPIRY_OUTCOMES).map((value) => ({
    value,
    label: t(`accredited.completion.outcomes.${value}`),
  })),
)
const outcomeLabel = computed(
  () => outcomeOptions.value.find((option) => option.value === expiryOutcome.value)?.label ?? '',
)
const deadlineHint = computed(() =>
  t(`${copy}.deadlineHint`, {
    days: ACCREDITED_LIMITS.COMPLETION_DEADLINE_MAX_DAYS,
    date: maxLabel.value,
  }),
)
const stepItems = computed(() =>
  STEPS.map((value) => ({ value, label: t(`review.completion.steps.${value}`) })),
)

function toggle(key, checked) {
  const others = (items.value ?? []).filter((item) => item !== key)
  items.value = checked ? [...others, key] : others
}

function toggleAll(keys, checked) {
  const others = (items.value ?? []).filter((item) => !keys.includes(item))
  items.value = checked ? [...others, ...keys] : others
}

watch(
  () => Object.keys(form.errors.value),
  (fields) => steps.showErrors(fields.map(completionStepOf)),
)

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    Object.keys(notes).forEach((key) => delete notes[key])
    form.resetForm({ values: initialValues() })
    steps.reset()
  },
)
</script>

<template>
  <FormSteps
    :model-value="modelValue"
    :title="t(`${copy}.title`)"
    :steps="stepItems"
    :step="steps.step.value"
    :busy="form.submitting.value"
    :submit-label="t(`${copy}.submit`)"
    :error="form.formError.value"
    @update:model-value="emit('update:modelValue', $event)"
    @next="steps.next"
    @back="steps.back"
    @submit="form.onSubmit"
  >
    <div v-show="steps.step.value === 'items'" class="completion-dialog">
      <p v-if="suggested.length" class="completion-dialog__suggested" role="note">
        {{ t('review.completion.suggested', { count: suggested.length }, suggested.length) }}
      </p>
      <CompletionItemPicker
        v-if="modelValue"
        :groups="groups"
        :selected="items ?? []"
        :suggested="suggested"
        :open-main-id="activeMainId"
        @toggle="toggle"
        @toggle-many="toggleAll"
      />
      <CompletionSelectionSummary
        :options="selectedOptions"
        :notes="notes"
        :error="itemsError"
        @remove="toggle($event, false)"
        @note="(key, value) => (notes[key] = value)"
      />
    </div>
    <CompletionMessageStep
      v-show="steps.step.value === 'message'"
      v-model:deadline="deadline"
      :deadline-error="deadlineError"
      :bounds="bounds"
      :deadline-hint="deadlineHint"
      :outcome-options="outcomeOptions"
    />
    <CompletionReviewStep
      v-if="steps.step.value === 'review'"
      :options="selectedOptions"
      :notes="notes"
      :message="message ?? ''"
      :deadline="deadline ?? ''"
      :outcome-label="outcomeLabel"
    />
  </FormSteps>
</template>
