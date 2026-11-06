<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import SelectionCheckbox from '@/components/form/SelectionCheckbox.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import TransferBulkBar from '@/components/finance/TransferBulkBar.vue'
import BulkOutcomeNotice from '@/components/finance/BulkOutcomeNotice.vue'
import ApproveTransferDialog from '@/components/finance/ApproveTransferDialog.vue'
import RejectTransferDialog from '@/components/finance/RejectTransferDialog.vue'
import { useTransferSelection } from '@/composables/useTransferSelection'
import { useTransferDecisions } from '@/composables/useTransferDecisions'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'
import { ibanLast4 } from '@/utils/payments/iban'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  blocked: { type: Object, default: null },
  canReview: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'retry', 'decided'])
const { t, n, locale } = useI18n()

const rows = ref([])
const open = computed({
  get: () => props.blocked !== null,
  set: (value) => {
    if (!value) emit('close')
  },
})
const items = computed(() => rows.value.map((row) => ({ ...row, id: row.paymentId })))
const selection = useTransferSelection(items)
const references = computed(() =>
  Object.fromEntries(items.value.map((item) => [item.id, item.reference])),
)

const decisions = useTransferDecisions({
  onDecided: ({ outcome, transfer }) => {
    const decided = outcome?.decided ?? (transfer ? [transfer.id] : [])
    rows.value = rows.value.filter((row) => !decided.includes(row.paymentId))
    selection.clear()
    emit('decided')
  },
})

const title = computed(() =>
  props.blocked ? t(`settings.bankAccounts.blocked.${props.blocked.action}.title`) : '',
)
const subtitle = computed(() =>
  props.blocked
    ? t('settings.bankAccounts.blocked.subtitle', {
        bank: localizedText(props.blocked.account.bankName, locale.value),
        last4: ibanLast4(props.blocked.account.iban),
      })
    : '',
)
const warning = computed(() =>
  t(
    `settings.bankAccounts.blocked.${props.canReview ? 'reviewer' : 'readOnly'}`,
    { count: formatNumber(n, rows.value.length) },
    rows.value.length,
  ),
)
const cleared = computed(() => props.blocked !== null && rows.value.length === 0)

watch(
  () => props.blocked,
  (blocked) => {
    rows.value = blocked ? [...blocked.transfers] : []
    decisions.clearOutcome()
  },
  { immediate: true },
)
</script>

<template>
  <BaseModal v-model="open" size="lg" :title="title">
    <div v-if="blocked" class="pending-transfers">
      <p class="pending-transfers__subtitle">
        <bdi>{{ subtitle }}</bdi>
      </p>
      <p v-if="cleared" class="pending-transfers__cleared" role="status">
        <AppIcon name="check-circle" />
        {{ t(`settings.bankAccounts.blocked.${blocked.action}.cleared`) }}
      </p>
      <template v-else>
        <p class="pending-transfers__warning" role="alert">
          <AppIcon :name="canReview ? 'warning-triangle' : 'lock'" />
          {{ warning }}
        </p>
        <BulkOutcomeNotice
          v-if="decisions.outcome.value"
          :outcome="decisions.outcome.value"
          :references="references"
          @dismiss="decisions.clearOutcome"
        />
        <div class="pending-transfers__table-wrap">
          <table class="table pending-transfers__table">
            <thead class="table__head">
              <tr>
                <th v-if="canReview" class="table__cell" scope="col">
                  <SelectionCheckbox
                    :model-value="selection.allSelected.value"
                    :indeterminate="selection.someSelected.value"
                    :label="t('finance.transfers.selection.selectAll')"
                    @update:model-value="selection.toggleAll($event)"
                  />
                </th>
                <th class="table__cell" scope="col">
                  {{ t('finance.transfers.fields.reference') }}
                </th>
                <th class="table__cell" scope="col">{{ t('finance.transfers.fields.entity') }}</th>
                <th class="table__cell" scope="col">{{ t('finance.transfers.columns.amount') }}</th>
                <th class="table__cell" scope="col">
                  {{ t('finance.transfers.fields.submittedAt') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in items"
                :key="item.id"
                class="table__row"
                :class="{ 'is-selected': canReview && selection.isSelected(item.id) }"
              >
                <td v-if="canReview" class="table__cell">
                  <SelectionCheckbox
                    :model-value="selection.isSelected(item.id)"
                    :label="t('finance.transfers.selection.select', { reference: item.reference })"
                    @update:model-value="selection.toggle(item.id, $event)"
                  />
                </td>
                <td class="table__cell">
                  <bdi dir="ltr">{{ item.reference }}</bdi>
                </td>
                <td class="table__cell">{{ localizedText(item.entityName, locale) }}</td>
                <td class="table__cell">
                  <MoneyAmount :value="item.total" :currency="item.currency" strong />
                </td>
                <td class="table__cell">{{ formatRiyadhDateTime(item.submittedAt, locale) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <TransferBulkBar
          v-if="canReview && selection.count.value > 0"
          :count="selection.count.value"
          :total="items.length"
          :over-limit="selection.overLimit.value"
          :clearable="false"
          @approve="decisions.openApprove(selection.selected.value)"
          @reject="decisions.openReject(selection.selected.value)"
        />
      </template>
    </div>
    <template #footer>
      <BaseButton variant="secondary" @click="emit('close')">{{ t('actions.close') }}</BaseButton>
      <BaseButton v-if="cleared" variant="danger" @click="emit('retry')">
        {{ t(`settings.bankAccounts.blocked.${blocked.action}.retry`) }}
      </BaseButton>
    </template>
  </BaseModal>

  <ApproveTransferDialog
    v-model="decisions.approveOpen.value"
    :targets="decisions.approveTargets.value"
    :asks-reason="decisions.asksReason.value"
    :action="decisions.approve"
  />
  <RejectTransferDialog
    v-model="decisions.rejectOpen.value"
    :targets="decisions.rejectTargets.value"
    :asks-reason="decisions.asksReason.value"
    :action="decisions.reject"
  />
</template>
