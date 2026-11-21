<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import PricingBreakdown from '@/components/payments/PricingBreakdown.vue'
import { PRICE_AVAILABILITY_VARIANTS } from '@/constants/prices'
import { formatRiyadhDate, formatRiyadhDateTime } from '@/utils/datetime'

const props = defineProps({
  version: { type: Object, default: null },
  title: { type: String, required: true },
  testId: { type: String, required: true },
})

const { t, locale } = useI18n()
const author = computed(() => props.version?.setBy?.name ?? t('settings.prices.system'))
const setAt = computed(() => formatRiyadhDateTime(props.version?.createdAt, locale.value))
const effectiveOn = computed(() => formatRiyadhDate(props.version?.effectiveFrom, locale.value))
</script>

<template>
  <section class="path-price-version" :data-testid="testId">
    <header class="path-price-version__head">
      <h3 class="path-price-version__title">{{ title }}</h3>
      <Badge v-if="version" :variant="PRICE_AVAILABILITY_VARIANTS[version.availability]">
        {{ t(`settings.prices.availability.${version.availability}`) }}
      </Badge>
    </header>
    <template v-if="version">
      <PricingBreakdown v-if="version.quote" :pricing="version.quote" />
      <p v-if="version.isScheduled" class="path-price-version__meta">
        {{ t('settings.prices.effectiveOn', { date: effectiveOn }) }}
      </p>
      <p class="path-price-version__meta">
        {{ t('settings.prices.setBy', { name: author, date: setAt }) }}
      </p>
      <slot />
    </template>
    <p v-else class="path-price-version__meta">{{ t('settings.prices.unset') }}</p>
  </section>
</template>
