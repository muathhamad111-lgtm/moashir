<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import TransferTargetsSummary from './TransferTargetsSummary.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { approvalSchema } from '@/utils/payments/bankTransferSchemas'
import { SUPER_ADMIN_REASON } from '@/constants/bankTransfer'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  targets: { type: Array, required: true },
  asksReason: { type: Boolean, default: false },
  action: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const isBulk = computed(() => props.targets.length > 1)
const title = computed(() =>
  isBulk.value
    ? t('finance.transfers.approve.bulkTitle', { count: props.targets.length })
    : t('finance.transfers.approve.title'),
)
const submitLabel = computed(() =>
  isBulk.value
    ? t('finance.transfers.approve.bulkSubmit', { count: props.targets.length })
    : t('finance.transfers.approve.submit'),
)

const form = useValidatedForm({
  schema: computed(() => approvalSchema(t, { asksReason: props.asksReason })),
  initialValues: { reason: '' },
  action: 'bankTransfers.approve',
  submit: async ({ reason }) => {
    await props.action(props.asksReason ? reason.trim() : null)
    emit('update:modelValue', false)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: { reason: '' } })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="title"
    :busy="form.submitting.value"
    :submit-label="submitLabel"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="transfer-decision">
      <TransferTargetsSummary :targets="targets" />
      <p class="transfer-decision__message">
        {{
          isBulk
            ? t('finance.transfers.approve.bulkMessage')
            : t('finance.transfers.approve.message')
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
