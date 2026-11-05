<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import PaymentStatusBadge from '@/components/payments/PaymentStatusBadge.vue'
import DateStamp from './DateStamp.vue'
import { ACCREDITED_STATUSES } from '@/constants/accredited'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  current: { type: Object, required: true },
})

const { t } = useI18n()
const closed = computed(() => props.current.latestClosed)
const hasResult = computed(() => closed.value?.status === ACCREDITED_STATUSES.COMPLETED)
const closedNote = computed(() =>
  closed.value && !hasResult.value
    ? t(`accredited.current.closedNotes.${closed.value.status}`)
    : '',
)
</script>

<template>
  <Card class="accredited-current">
    <template #header>
      <h2 class="card__title-text">{{ t('accredited.current.title') }}</h2>
    </template>
    <div v-if="current.awaitingPayment" class="accredited-current__block">
      <p class="accredited-current__label">{{ t('accredited.current.awaitingPayment') }}</p>
      <p class="accredited-current__row">
        <bdi dir="ltr">{{ current.awaitingPayment.reference }}</bdi>
        <PaymentStatusBadge :status="current.awaitingPayment.status" />
      </p>
      <p class="accredited-current__meta">
        {{ t('accredited.current.expiresAt') }}
        <DateStamp :value="current.awaitingPayment.expiresAt" />
      </p>
      <RouterLink
        class="accredited-current__link"
        :to="{
          name: ROUTE_NAMES.PAYMENTS_DETAIL,
          params: { id: current.awaitingPayment.paymentId },
        }"
      >
        {{ t('accredited.current.openPayment') }}
      </RouterLink>
    </div>
    <div v-if="current.active" class="accredited-current__block">
      <p class="accredited-current__label">{{ t('accredited.current.active') }}</p>
      <p class="accredited-current__row">
        <bdi dir="ltr">{{ current.active.number }}</bdi>
        <StatusBadge :status="current.active.status" />
      </p>
      <p v-if="current.active.submittedAt" class="accredited-current__meta">
        {{ t('accredited.form.submittedAt') }} <DateStamp :value="current.active.submittedAt" />
      </p>
      <RouterLink
        class="accredited-current__link"
        :to="{ name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM, params: { id: current.active.id } }"
      >
        {{ t('accredited.current.openRequest') }}
      </RouterLink>
    </div>
    <div v-if="closed" class="accredited-current__block">
      <p class="accredited-current__label">{{ t('accredited.current.latestClosed') }}</p>
      <p class="accredited-current__row">
        <bdi dir="ltr">{{ closed.number }}</bdi>
        <StatusBadge :status="closed.status" />
      </p>
      <p v-if="closedNote" class="accredited-current__meta">{{ closedNote }}</p>
      <RouterLink
        v-if="hasResult"
        class="accredited-current__link"
        :to="{ name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_RESULT, params: { id: closed.id } }"
      >
        {{ t('accredited.current.viewResult') }}
      </RouterLink>
    </div>
    <p
      v-if="!current.awaitingPayment && !current.active && !closed"
      class="accredited-current__empty"
    >
      {{ t('accredited.current.empty') }}
    </p>
  </Card>
</template>
