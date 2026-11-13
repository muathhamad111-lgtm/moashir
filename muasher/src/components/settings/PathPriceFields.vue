<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import PricingBreakdown from '@/components/payments/PricingBreakdown.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'

defineProps({
  settings: { type: Object, required: true },
  canEditPrices: { type: Boolean, default: false },
})

const { t } = useI18n()
</script>

<template>
  <fieldset class="settings-form__group">
    <legend class="settings-form__legend">{{ t('settings.paths.priceTitle') }}</legend>
    <PricingBreakdown v-if="settings.price" :pricing="settings.price" />
    <p v-else class="settings-form__note" role="note">
      <AppIcon name="info-circle" />
      {{ t('settings.paths.noPrice') }}
    </p>
    <p class="settings-form__note" role="note" data-testid="path-price-moved">
      <AppIcon name="info-circle" />
      <span>{{ t('settings.paths.priceMoved') }}</span>
      <RouterLink
        v-if="canEditPrices"
        class="settings-form__link"
        :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS_PRICES }"
      >
        {{ t('finance.settings.openPrices') }}
      </RouterLink>
    </p>
  </fieldset>
</template>
