<script setup>
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import SelectionCheckbox from '@/components/form/SelectionCheckbox.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import TransferFlags from './TransferFlags.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatRiyadhDateTime } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'

defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
  selectable: { type: Boolean, default: false },
  selection: { type: Object, default: null },
  isDecidable: { type: Function, default: () => false },
  emptyTitle: { type: String, required: true },
  emptyMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, locale } = useI18n()

function submittedOf(item) {
  return formatRiyadhDateTime(item.submittedAt, locale.value)
}

function reviewRoute(item) {
  return { name: ROUTE_NAMES.FINANCE_TRANSFER, params: { id: item.id } }
}
</script>

<template>
  <div class="transfer-queue">
    <Skeleton v-if="status === 'loading'" variant="text" :count="6" />
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="emit('retry')" />
    <EmptyState
      v-else-if="status === 'empty'"
      icon="inbox"
      :title="emptyTitle"
      :message="emptyMessage"
    />
    <template v-else>
      <div v-if="selectable" class="transfer-queue__select-all">
        <SelectionCheckbox
          :model-value="selection.allSelected.value"
          :indeterminate="selection.someSelected.value"
          :label="t('finance.transfers.selection.selectAll')"
          show-label
          @update:model-value="selection.toggleAll($event)"
        />
        <span class="transfer-queue__count" aria-live="polite">{{
          t('finance.transfers.selection.count', { count: selection.count.value })
        }}</span>
      </div>
      <div class="transfer-queue__table-wrap">
        <table class="table transfer-queue__table">
          <thead class="table__head">
            <tr>
              <th v-if="selectable" class="table__cell transfer-queue__check" scope="col">
                <SelectionCheckbox
                  :model-value="selection.allSelected.value"
                  :indeterminate="selection.someSelected.value"
                  :label="t('finance.transfers.selection.selectAll')"
                  @update:model-value="selection.toggleAll($event)"
                />
              </th>
              <th class="table__cell" scope="col">{{ t('finance.transfers.columns.request') }}</th>
              <th class="table__cell" scope="col">{{ t('finance.transfers.columns.entity') }}</th>
              <th class="table__cell" scope="col">{{ t('finance.transfers.columns.amount') }}</th>
              <th class="table__cell" scope="col">{{ t('finance.transfers.columns.bank') }}</th>
              <th class="table__cell" scope="col">{{ t('finance.transfers.columns.status') }}</th>
              <th class="table__cell" scope="col">{{ t('finance.transfers.columns.flags') }}</th>
              <th class="table__cell" scope="col">
                <span class="transfer-queue__sr">{{ t('finance.transfers.columns.actions') }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in items"
              :key="item.id"
              class="table__row transfer-queue__row"
              :class="{ 'is-selected': selectable && selection.isSelected(item.id) }"
            >
              <td v-if="selectable" class="table__cell transfer-queue__check">
                <SelectionCheckbox
                  :model-value="selection.isSelected(item.id)"
                  :label="t('finance.transfers.selection.select', { reference: item.reference })"
                  :disabled="!isDecidable(item)"
                  @update:model-value="selection.toggle(item.id, $event)"
                />
              </td>
              <td class="table__cell">
                <span class="transfer-queue__reference">
                  <bdi dir="ltr">{{ item.reference }}</bdi>
                  <span class="transfer-queue__muted">{{ submittedOf(item) }}</span>
                </span>
              </td>
              <td class="table__cell">
                <span class="transfer-queue__entity">
                  <span>{{ localizedText(item.entity.name, locale) }}</span>
                  <span class="transfer-queue__muted">{{
                    t(`finance.purposes.${item.purpose}`)
                  }}</span>
                </span>
              </td>
              <td class="table__cell">
                <MoneyAmount
                  :value="item.pricing?.total"
                  :currency="item.pricing?.currency"
                  strong
                />
              </td>
              <td class="table__cell">{{ localizedText(item.bankAccount.bankName, locale) }}</td>
              <td class="table__cell"><PaymentStatusBadge :status="item.status" /></td>
              <td class="table__cell"><TransferFlags :transfer="item" /></td>
              <td class="table__cell">
                <RouterLink class="transfer-queue__link" :to="reviewRoute(item)">
                  {{ t('finance.transfers.review') }}
                  <span class="transfer-queue__sr">{{ item.reference }}</span>
                  <AppIcon name="chevron-right" />
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <ul class="transfer-queue__cards">
        <li
          v-for="item in items"
          :key="item.id"
          class="transfer-card"
          :class="{ 'is-selected': selectable && selection.isSelected(item.id) }"
        >
          <div class="transfer-card__head">
            <SelectionCheckbox
              v-if="selectable"
              :model-value="selection.isSelected(item.id)"
              :label="t('finance.transfers.selection.select', { reference: item.reference })"
              :disabled="!isDecidable(item)"
              @update:model-value="selection.toggle(item.id, $event)"
            />
            <span class="transfer-card__title">
              <bdi dir="ltr" class="transfer-card__reference">{{ item.reference }}</bdi>
              <span>{{ localizedText(item.entity.name, locale) }}</span>
              <span class="transfer-queue__muted">{{ t(`finance.purposes.${item.purpose}`) }}</span>
            </span>
          </div>
          <p class="transfer-card__amount">
            <span>{{ t('finance.transfers.columns.amount') }}</span>
            <MoneyAmount :value="item.pricing?.total" :currency="item.pricing?.currency" strong />
          </p>
          <p class="transfer-card__meta">
            <span>{{ localizedText(item.bankAccount.bankName, locale) }}</span>
            <TransferFlags :transfer="item" />
          </p>
          <RouterLink class="btn btn--secondary transfer-card__action" :to="reviewRoute(item)">
            {{ t('finance.transfers.review') }}
            <span class="transfer-queue__sr">{{ item.reference }}</span>
          </RouterLink>
        </li>
      </ul>
    </template>
  </div>
</template>
