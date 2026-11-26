<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import FieldRadio from '@/components/form/FieldRadio.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useAuthStore } from '@/stores/auth'
import { COMMITTEE_LIMITS, DISCUSSION_MODES } from '@/constants/committee'
import { riyadhDateKey } from '@/utils/datetime'
import { DECISION_DEFAULTS, decisionPayload, decisionSchema } from '@/utils/award/secretaryDecision'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  kind: { type: String, required: true },
  subject: { type: String, default: '' },
  send: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue', 'done'])
const { t } = useI18n()
const auth = useAuthStore()
const text = (key, params = {}) => t(`award.secretary.dialogs.${props.kind}.${key}`, params)
const modeOptions = computed(() =>
  Object.values(DISCUSSION_MODES).map((value) => ({
    value,
    label: t(`award.discussion.modes.${value}`),
  })),
)
const today = computed(() => riyadhDateKey(new Date().toISOString()))

const form = useValidatedForm({
  schema: computed(() => decisionSchema(t, props.kind, auth.isSuperAdmin)),
  initialValues: { ...DECISION_DEFAULTS[props.kind], reason: '' },
  action: `secretary.${props.kind}`,
  submit: async (values) => {
    const result = await props.send(decisionPayload(props.kind, values))
    emit('update:modelValue', false)
    emit('done', result)
  },
})

const [mode] = form.defineField('mode')
const [deadlineDate] = form.defineField('deadlineDate')

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: { ...DECISION_DEFAULTS[props.kind], reason: '' } })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="text('title')"
    :subtitle="subject"
    :busy="form.submitting.value"
    :submit-label="text('submit')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="secretary-decision">
      <FieldTextarea
        v-if="kind === 'approve' || kind === 'approveSubstitution'"
        name="note"
        :label="t('award.secretary.fields.note')"
        :hint="t('award.common.optional')"
        :rows="3"
      />
      <FieldTextarea
        v-if="kind === 'returnToCommittee'"
        name="generalNote"
        :label="t('award.secretary.fields.generalNote')"
        :hint="t('award.secretary.fields.generalNoteHint')"
        :rows="5"
        required
      />
      <FieldTextarea
        v-if="kind === 'closeDiscussion'"
        name="summary"
        :label="t('award.secretary.fields.summary')"
        :hint="t('award.common.optional')"
        :rows="4"
      />
      <template v-if="kind === 'openDiscussion'">
        <FieldRadio name="mode" :label="t('award.secretary.fields.mode')" :options="modeOptions" />
        <p class="secretary-decision__hint">{{ t(`award.secretary.fields.modeHints.${mode}`) }}</p>
        <BaseDatePicker
          v-if="mode === DISCUSSION_MODES.SESSION"
          v-model="deadlineDate"
          name="deadlineDate"
          :label="t('award.secretary.fields.deadline')"
          :min="today"
          :error="form.errors.value.deadlineDate ?? form.errors.value.deadlineAt"
          required
        />
        <FieldTextarea
          v-else
          name="agreedOutcome"
          :label="t('award.secretary.fields.agreedOutcome')"
          :rows="5"
          required
        />
      </template>
      <FieldTextarea
        v-if="auth.isSuperAdmin"
        name="reason"
        :label="t('award.common.reason')"
        :hint="t('award.common.reasonRequired', { min: COMMITTEE_LIMITS.REASON_MIN })"
        :rows="3"
        required
      />
    </div>
  </FormModal>
</template>
