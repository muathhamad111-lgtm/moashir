<script setup>
import { computed, reactive, watch } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import FieldRadio from '@/components/form/FieldRadio.vue'
import CompletionItemPicker from './CompletionItemPicker.vue'
import CompletionSelectionSummary from './CompletionSelectionSummary.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { assessorReviewService } from '@/services/assessorReviewService'
import { ACCREDITED_LIMITS, EXPIRY_OUTCOMES } from '@/constants/accredited'
import {
  completionPayload,
  completionSchema,
  deadlineBounds,
  itemOptions,
} from '@/utils/accredited/completionForm'
import { formatRiyadhDate } from '@/utils/datetime'

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
})

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
  initialValues: { notes: '', deadline: '', expiryOutcome: EXPIRY_OUTCOMES.CONTINUE, items: [] },
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
const { value: items, errorMessage: itemsError } = useField('items')
const { value: deadline, errorMessage: deadlineError } = useField('deadline')

const allOptions = computed(() => itemOptions(props.criteria))
const groups = computed(() =>
  props.criteria.mains.map((main) => ({
    main,
    options: allOptions.value.filter((option) => main.subs.some((sub) => sub.id === option.sub.id)),
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

function toggle(key, checked) {
  const others = (items.value ?? []).filter((item) => item !== key)
  items.value = checked ? [...others, key] : others
}

function toggleAll(keys, checked) {
  const others = (items.value ?? []).filter((item) => !keys.includes(item))
  items.value = checked ? [...others, ...keys] : others
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    Object.keys(notes).forEach((key) => delete notes[key])
    form.resetForm({
      values: { notes: '', deadline: '', expiryOutcome: EXPIRY_OUTCOMES.CONTINUE, items: [] },
    })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="xl"
    :title="t(`${copy}.title`)"
    :busy="form.submitting.value"
    :submit-label="t(`${copy}.submit`)"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="completion-dialog">
      <CompletionItemPicker
        v-if="modelValue"
        :groups="groups"
        :selected="items ?? []"
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
      <section class="completion-dialog__details" aria-labelledby="completion-details-title">
        <h3 id="completion-details-title" class="completion-dialog__details-title">
          {{ t('review.completion.detailsTitle') }}
        </h3>
        <FieldTextarea
          class="completion-dialog__notes"
          name="notes"
          :label="t('review.completion.notes')"
          :hint="
            t('review.completion.notesHint', {
              max: ACCREDITED_LIMITS.COMPLETION_NOTES_MAX,
            })
          "
          :rows="3"
          required
        />
        <BaseDatePicker
          v-model="deadline"
          name="deadline"
          :label="t('review.completion.deadline')"
          :min="bounds.min"
          :max="bounds.max"
          :hint="
            t(`${copy}.deadlineHint`, {
              days: ACCREDITED_LIMITS.COMPLETION_DEADLINE_MAX_DAYS,
              date: maxLabel,
            })
          "
          :error="deadlineError"
          required
        />
        <FieldRadio
          name="expiryOutcome"
          :label="t('review.completion.expiryOutcome')"
          :options="outcomeOptions"
        />
        <p class="completion-dialog__outcome-hint" role="note">
          {{ t('review.completion.expiryHint') }}
        </p>
      </section>
    </div>
  </FormModal>
</template>
