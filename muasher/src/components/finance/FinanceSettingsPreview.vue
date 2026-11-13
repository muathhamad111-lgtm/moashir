<script setup>
import { useI18n } from 'vue-i18n'
import PricingBreakdown from '@/components/payments/PricingBreakdown.vue'
import { CART_PURPOSE_VALUES } from '@/constants/payments'

defineProps({
  pricing: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <section class="finance-preview" aria-labelledby="finance-preview-title">
    <h2 id="finance-preview-title" class="finance-preview__title">
      {{ t('finance.settings.previewTitle') }}
    </h2>
    <p class="finance-preview__hint">{{ t('finance.settings.previewHint') }}</p>
    <div class="finance-preview__grid">
      <article v-for="purpose in CART_PURPOSE_VALUES" :key="purpose" class="finance-preview__card">
        <h3 class="finance-preview__service">{{ t(`finance.purposes.${purpose}`) }}</h3>
        <PricingBreakdown v-if="pricing[purpose]" :pricing="pricing[purpose]" />
        <p v-else class="finance-preview__unset">{{ t('finance.settings.priceUnset') }}</p>
      </article>
    </div>
  </section>
</template>
