<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  pricing: { type: Object, default: null },
  label: { type: String, default: '' },
  freeLabel: { type: String, default: '' },
  note: { type: String, default: '' },
})

const { t, n } = useI18n()
const price = computed(() => {
  const quote = props.pricing?.price
  const amount = Number(quote?.priceBeforeVat)
  return Number.isFinite(amount) && amount > 0 ? quote : null
})
const money = (value) => formatNumber(n, value, 'money')
const vatRate = computed(() => formatNumber(n, Number(price.value?.vatRate ?? 0)))
</script>

<template>
  <div class="lp-path__cost">
    <span class="lp-path__cost-label">{{ label }}</span>
    <template v-if="pricing?.isFree">
      <b>{{ freeLabel }}</b>
    </template>
    <template v-else-if="price">
      <b>
        <bdi dir="ltr">{{ money(price.priceBeforeVat) }}</bdi>
        {{ t('landing.paths.currency') }}
        <small>{{ t('landing.paths.beforeVat') }}</small>
      </b>
      <small class="lp-path__vat">
        {{
          t('landing.paths.vatLine', {
            rate: vatRate,
            vat: money(price.vatAmount),
            total: money(price.total),
          })
        }}
      </small>
    </template>
    <b v-else>{{ note }}</b>
  </div>
</template>
