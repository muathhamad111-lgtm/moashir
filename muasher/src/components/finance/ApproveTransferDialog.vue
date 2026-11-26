<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import DecisionDialog from '@/components/modal/DecisionDialog.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
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
const line = computed(() =>
  isBulk.value ? t('finance.transfers.approve.bulkMessage') : (props.targets[0]?.reference ?? ''),
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
  <DecisionDialog
    :model-value="modelValue"
    :title="title"
    :message="line"
    :confirm-label="t('actions.approve')"
    icon="check-circle"
    :busy="form.submitting.value"
    @update:model-value="emit('update:modelValue', $event)"
    @confirm="form.onSubmit"
  >
    <div v-if="asksReason" class="modal__fields">
      <FieldTextarea
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
  </DecisionDialog>
</template>
