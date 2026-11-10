<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import PaymentServiceCell from './PaymentServiceCell.vue'
import PaymentReceiptAction from './PaymentReceiptAction.vue'
import PaymentChannelCell from './PaymentChannelCell.vue'
import { isoDate } from '@/utils/datetime'
import { formatRate } from '@/utils/payments/amountFormat'
import { paymentDateOf } from '@/utils/payments/myPaymentRow'

defineProps({
  rows: { type: Array, required: true },
  busy: { type: Object, required: true },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()

function dateOf(payment) {
  return isoDate(paymentDateOf(payment))
}

function vatLabel(payment) {
  return t('portal.payments.columns.vat', {
    rate: formatRate(payment.pricing.vatRate, locale.value),
  })
}
</script>

<template>
  <div class="my-payments">
    <div class="my-payments__table-wrap">
      <table class="my-payments__table">
        <caption class="my-payments__caption">
          {{
            t('portal.payments.tableCaption')
          }}
        </caption>
        <thead>
          <tr>
            <th scope="col">{{ t('portal.payments.columns.service') }}</th>
            <th scope="col">{{ t('portal.payments.columns.channel') }}</th>
            <th scope="col">{{ t('portal.payments.columns.receipt') }}</th>
            <th scope="col">{{ t('portal.payments.columns.date') }}</th>
            <th scope="col">{{ t('portal.payments.columns.beforeVat') }}</th>
            <th scope="col">{{ t('portal.payments.columns.vatShort') }}</th>
            <th scope="col">{{ t('portal.payments.columns.total') }}</th>
            <th scope="col">{{ t('portal.payments.columns.status') }}</th>
            <th scope="col">
              <span class="my-payments__sr">{{ t('portal.payments.columns.actions') }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="payment in rows" :key="payment.id" class="my-payments__row">
            <td><PaymentServiceCell :payment="payment" /></td>
            <td><PaymentChannelCell :payment="payment" /></td>
            <td>
              <bdi v-if="payment.receipt?.number" class="my-payments__receipt" dir="ltr">{{
                payment.receipt.number
              }}</bdi>
              <Badge v-else-if="payment.receipt" variant="warning">{{
                t('finance.receipt.notProof')
              }}</Badge>
              <span v-else class="my-payments__muted">{{ t('finance.common.none') }}</span>
            </td>
            <td>
              <bdi class="my-payments__date" dir="ltr">{{ dateOf(payment) }}</bdi>
            </td>
            <td>
              <MoneyAmount
                :value="payment.pricing.priceBeforeVat"
                :currency="payment.pricing.currency"
              />
            </td>
            <td :title="vatLabel(payment)">
              <MoneyAmount
                :value="payment.pricing.vatAmount"
                :currency="payment.pricing.currency"
              />
            </td>
            <td>
              <MoneyAmount
                :value="payment.pricing.total"
                :currency="payment.pricing.currency"
                strong
              />
            </td>
            <td><PaymentStatusBadge :status="payment.status" /></td>
            <td>
              <PaymentReceiptAction
                :payment="payment"
                :busy="Boolean(busy[payment.id])"
                @download="emit('download', $event)"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <ul class="my-payments__cards">
      <li v-for="payment in rows" :key="payment.id" class="my-payment-card">
        <div class="my-payment-card__head">
          <PaymentServiceCell :payment="payment" />
          <PaymentStatusBadge :status="payment.status" />
        </div>
        <div class="my-payment-card__total">
          <span class="my-payment-card__label">{{ t('portal.payments.totalWithVat') }}</span>
          <MoneyAmount :value="payment.pricing.total" :currency="payment.pricing.currency" strong />
        </div>
        <p class="my-payment-card__meta">
          <PaymentChannelCell :payment="payment" />
          <span aria-hidden="true">·</span>
          <bdi dir="ltr">{{ dateOf(payment) }}</bdi>
          <template v-if="payment.receipt?.number">
            <span aria-hidden="true">·</span>
            <bdi dir="ltr">{{ payment.receipt.number }}</bdi>
          </template>
        </p>
        <PaymentReceiptAction
          :payment="payment"
          :busy="Boolean(busy[payment.id])"
          @download="emit('download', $event)"
        />
      </li>
    </ul>
  </div>
</template>
