<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AVAILABILITY } from '@/constants/entityPaths'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'

const props = defineProps({
  pricing: { type: Object, default: null },
  detailed: { type: Boolean, default: false },
})

const { t } = useI18n()
const unavailable = computed(
  () => props.pricing && props.pricing.availability !== AVAILABILITY.AVAILABLE,
)
const quote = computed(() => props.pricing?.price ?? null)
</script>

<template>
  <div class="path-price" data-testid="path-price">
    <span v-if="!pricing" class="path-price__muted">{{ t('finance.common.none') }}</span>
    <span v-else-if="unavailable" class="path-price__muted">{{
      t('paths.price.unavailable')
    }}</span>
    <span v-else-if="pricing.isFree || !quote" class="path-price__free">
      {{ t('paths.price.free') }}
    </span>
    <template v-else>
      <span class="path-price__line">
        <MoneyAmount :value="quote.priceBeforeVat" :currency="quote.currency" />
        <span>{{ t('paths.price.beforeVat') }}</span>
      </span>
      <template v-if="detailed">
        <span class="path-price__line">
          <span>{{ t('paths.price.vat') }}</span>
          <MoneyAmount :value="quote.vatAmount" :currency="quote.currency" />
        </span>
        <span class="path-price__line path-price__line--total">
          <span>{{ t('paths.price.total') }}</span>
          <MoneyAmount :value="quote.total" :currency="quote.currency" strong />
        </span>
      </template>
    </template>
  </div>
</template>
