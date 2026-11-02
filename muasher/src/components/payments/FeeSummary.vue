<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { withLatinNumerals } from '@/utils/numerals'

const props = defineProps({
  amount: { type: Number, required: true },
  currency: { type: String, default: 'SAR' },
  purpose: { type: String, default: 'accredited' },
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  status: { type: String, default: '' },
  lineItems: { type: Array, default: () => [] },
})

const { t, locale } = useI18n()

const formattedAmount = computed(() =>
  new Intl.NumberFormat(withLatinNumerals(locale.value === 'ar' ? 'ar-SA' : 'en-US')).format(
    props.amount ?? 0,
  ),
)
</script>

<template>
  <Card>
    <div class="fee-summary">
      <div class="fee-summary__head">
        <div>
          <p class="fee-summary__eyebrow">{{ t('payment.feeSummary.eyebrow') }}</p>
          <h2 class="fee-summary__title">{{ title || t('payment.feeSummary.title') }}</h2>
        </div>
        <StatusBadge v-if="status" :status="status" />
      </div>

      <p v-if="description" class="fee-summary__description">{{ description }}</p>

      <ul v-if="lineItems.length" class="fee-summary__lines">
        <li v-for="(item, index) in lineItems" :key="index" class="fee-summary__line">
          <span>{{ item.label }}</span>
          <span class="fee-summary__line-value">{{ item.value }}</span>
        </li>
      </ul>

      <div class="fee-summary__total">
        <span class="fee-summary__total-label">{{ t('payment.feeSummary.total') }}</span>
        <span class="fee-summary__total-amount">
          <span class="fee-summary__amount-value">{{ formattedAmount }}</span>
          <span class="fee-summary__amount-currency">{{ t(`payment.currency.${currency}`) }}</span>
        </span>
      </div>

      <p class="fee-summary__vat-note">{{ t('payment.feeSummary.vatNote') }}</p>

      <div v-if="$slots.action" class="fee-summary__action">
        <slot name="action" />
      </div>
    </div>
  </Card>
</template>
