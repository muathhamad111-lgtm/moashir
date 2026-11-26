<script setup>
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue'
import PaymentEventsPanel from '@/components/finance/PaymentEventsPanel.vue'

// «سجل أحداث الدفع»: the full log of what the gateway and the verification jobs reported, for
// support. Opened from the payment settings; each transaction shows its own events on its page.
const { t } = useI18n()

useHead({ title: () => t('routes.finance.paymentEvents.title') })
</script>

<template>
  <section class="finance-page">
    <FinancePageHeader
      :eyebrow="t('finance.eyebrow')"
      :title="t('routes.finance.paymentEvents.title')"
      :subtitle="t('finance.events.pageSubtitle')"
    />
    <PaymentEventsPanel />
  </section>
</template>
