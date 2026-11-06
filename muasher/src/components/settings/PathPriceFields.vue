<script setup>
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import PricingBreakdown from '@/components/payments/PricingBreakdown.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  settings: { type: Object, required: true },
  editable: { type: Boolean, default: false },
})

const { t } = useI18n()
</script>

<template>
  <fieldset class="settings-form__group">
    <legend class="settings-form__legend">{{ t('settings.paths.priceTitle') }}</legend>
    <p class="settings-form__note" role="note">
      <AppIcon name="info-circle" />
      {{ t('settings.paths.priceNote') }}
    </p>
    <div class="settings-form__grid">
      <FieldInput
        name="price"
        :label="t('settings.paths.priceBeforeVat')"
        :hint="t('settings.paths.priceHint')"
        inputmode="decimal"
        input-dir="ltr"
        :readonly="!editable"
        :required="editable && Boolean(settings.price)"
      />
    </div>
    <PricingBreakdown v-if="settings.price" :pricing="settings.price" />
    <p v-else class="settings-form__note" role="note">
      <AppIcon name="warning-triangle" />
      {{ t('settings.paths.noPrice') }}
    </p>
  </fieldset>
</template>
