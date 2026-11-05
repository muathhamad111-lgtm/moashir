<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { PAYMENT_CHANNELS } from '@/constants/payments'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  canStartSelf: { type: Boolean, default: false },
  canRequestAccredited: { type: Boolean, default: false },
  pendingPayment: { type: Object, default: null },
})

const { t } = useI18n()
const paymentKey = computed(() =>
  props.pendingPayment?.channel === PAYMENT_CHANNELS.BANK_TRANSFER ? 'transfer' : 'payment',
)
</script>

<template>
  <ul class="assessment-start">
    <li v-if="pendingPayment" class="assessment-start__card assessment-start__card--warning">
      <AppIcon :name="paymentKey === 'transfer' ? 'refresh-dots' : 'credit-card'" />
      <div class="assessment-start__text">
        <h3 class="assessment-start__title">
          {{ t(`portal.assessments.start.${paymentKey}.title`) }}
        </h3>
        <p class="assessment-start__body">
          {{ t(`portal.assessments.start.${paymentKey}.body`) }}
          <bdi dir="ltr">{{ pendingPayment.reference }}</bdi>
        </p>
      </div>
      <router-link
        class="btn btn--primary"
        :to="{ name: ROUTE_NAMES.PAYMENTS_COMPLETE, params: { id: pendingPayment.paymentId } }"
      >
        {{
          paymentKey === 'transfer'
            ? t('portal.payments.viewDetails')
            : t('portal.payments.complete')
        }}
      </router-link>
    </li>
    <li v-if="canStartSelf" class="assessment-start__card">
      <AppIcon name="doc" />
      <div class="assessment-start__text">
        <h3 class="assessment-start__title">{{ t('portal.assessments.start.self.title') }}</h3>
        <p class="assessment-start__body">{{ t('portal.assessments.start.self.body') }}</p>
      </div>
      <router-link class="btn btn--secondary" :to="{ name: ROUTE_NAMES.ASSESSMENT_SELF }">
        {{ t('portal.assessments.start.self.action') }}
      </router-link>
    </li>
    <li v-if="canRequestAccredited" class="assessment-start__card">
      <AppIcon name="award" />
      <div class="assessment-start__text">
        <h3 class="assessment-start__title">
          {{ t('portal.assessments.start.accredited.title') }}
        </h3>
        <p class="assessment-start__body">{{ t('portal.assessments.start.accredited.body') }}</p>
      </div>
      <router-link class="btn btn--secondary" :to="{ name: ROUTE_NAMES.ASSESSMENT_ACCREDITED }">
        {{ t('portal.assessments.start.accredited.action') }}
      </router-link>
    </li>
  </ul>
</template>
