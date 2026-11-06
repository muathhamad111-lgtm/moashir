<script setup>
import { computed, watch } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import BaseRadio from '@/components/form/BaseRadio.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { rejectionSchema } from '@/utils/payments/bankTransferSchemas'
import {
  OTHER_REJECTION_REASON,
  REJECTION_NOTE,
  REJECTION_REASONS,
  SUPER_ADMIN_REASON,
} from '@/constants/bankTransfer'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  targets: { type: Array, required: true },
  asksReason: { type: Boolean, default: false },
  action: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const EMPTY = { rejectionReason: '', note: '', reason: '' }

const isBulk = computed(() => props.targets.length > 1)
const title = computed(() =>
  isBulk.value
    ? t('finance.transfers.reject.bulkTitle', { count: props.targets.length })
    : t('finance.transfers.reject.title'),
)
const references = computed(() => props.targets.map((target) => target.reference).join(' · '))
const options = computed(() =>
  REJECTION_REASONS.map((value) => ({
    value,
    label: t(`finance.transfers.rejectionReasons.${value}`),
  })),
)

const form = useValidatedForm({
  schema: computed(() => rejectionSchema(t, { asksReason: props.asksReason })),
  initialValues: { ...EMPTY },
  action: 'bankTransfers.reject',
  submit: async (values) => {
    await props.action({
      rejectionReason: values.rejectionReason,
      note: values.note?.trim() || null,
      reason: props.asksReason ? values.reason.trim() : null,
    })
    emit('update:modelValue', false)
  },
})

const preset = useField('rejectionReason')
const noteLabel = computed(() =>
  preset.value.value === OTHER_REJECTION_REASON
    ? t('finance.transfers.reject.noteRequiredLabel')
    : t('finance.transfers.reject.noteLabel'),
)

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: { ...EMPTY } })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="title"
    :busy="form.submitting.value"
    :submit-label="
      isBulk
        ? t('finance.transfers.reject.bulkSubmit', { count: targets.length })
        : t('finance.transfers.reject.submit')
    "
    submit-variant="danger"
    size="lg"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="transfer-decision">
      <p class="transfer-decision__references">
        <bdi dir="ltr">{{ references }}</bdi>
      </p>
      <BaseRadio
        v-model="preset.value.value"
        class="transfer-decision__presets"
        name="rejectionReason"
        :label="t('finance.transfers.reject.reasonLabel')"
        :options="options"
        :error="preset.errorMessage.value"
      />
      <FieldTextarea
        name="note"
        :label="noteLabel"
        :hint="t('finance.transfers.reject.noteHint', { max: REJECTION_NOTE.MAX })"
        :rows="4"
        :required="preset.value.value === OTHER_REJECTION_REASON"
      />
      <p class="transfer-decision__notice" role="note">
        <AppIcon name="question-circle" />
        {{
          isBulk ? t('finance.transfers.reject.bulkNotice') : t('finance.transfers.reject.notice')
        }}
      </p>
      <FieldTextarea
        v-if="asksReason"
        name="reason"
        :label="t('finance.transfers.superAdminReason.label')"
        :hint="
          t('finance.transfers.superAdminReason.hint', {
            min: SUPER_ADMIN_REASON.MIN,
            max: SUPER_ADMIN_REASON.MAX,
          })
        "
        required
      />
    </div>
  </FormModal>
</template>
