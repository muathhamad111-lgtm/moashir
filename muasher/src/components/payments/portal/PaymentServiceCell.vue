<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { paymentReferenceOf, paymentYearOf } from '@/utils/payments/myPaymentRow'

const props = defineProps({
  payment: { type: Object, required: true },
})

const { t } = useI18n()
const reference = computed(() => paymentReferenceOf(props.payment))
const year = computed(() => paymentYearOf(props.payment))
</script>

<template>
  <span class="payment-service">
    <router-link
      class="payment-service__name"
      :to="{ name: ROUTE_NAMES.PAYMENTS_DETAIL, params: { id: payment.id } }"
    >
      {{ t('portal.payments.service', { service: t(`finance.purposes.${payment.purpose}`) }) }}
      <bdi dir="ltr">{{ year }}</bdi>
    </router-link>
    <bdi class="payment-service__reference" dir="ltr">{{ reference }}</bdi>
  </span>
</template>
