<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import PricingBreakdown from '@/components/payments/PricingBreakdown.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  locked: { type: Object, required: true },
  quote: { type: Object, default: null },
  isNew: { type: Boolean, default: false },
})

const { t } = useI18n()
const isLocked = computed(() => props.locked.has('entryFee'))
const hint = computed(() =>
  t(props.isNew ? 'cycles.form.entryFeeDefaultHint' : 'cycles.form.entryFeeHint'),
)
</script>

<template>
  <fieldset class="cycle-form__section" data-testid="cycle-fee">
    <legend class="cycle-form__legend">{{ t('cycles.form.sections.fee') }}</legend>
    <div class="cycle-form__grid">
      <FieldInput
        name="entryFee"
        :label="t('cycles.form.entryFee')"
        :hint="hint"
        inputmode="decimal"
        input-dir="ltr"
        :readonly="isLocked"
        :disabled="isLocked"
      />
    </div>
    <p v-if="isLocked" class="cycle-form__note" role="note" data-testid="cycle-fee-locked">
      <AppIcon name="lock" />
      {{ t('cycles.form.entryFeeLocked') }}
    </p>
    <PricingBreakdown v-if="quote" :pricing="quote" />
  </fieldset>
</template>
