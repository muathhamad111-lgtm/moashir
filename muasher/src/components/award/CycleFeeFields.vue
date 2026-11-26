<script setup>
import { computed } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import CycleStepCounter from './CycleStepCounter.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldToggle from '@/components/form/FieldToggle.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  locked: { type: Object, required: true },
})

const { t } = useI18n()
const { value: paid } = useField('entryFeePaid')
const isLocked = computed(() => props.locked.has('entryFee'))
</script>

<template>
  <fieldset class="cycle-form__section" data-testid="cycle-fee">
    <legend class="cycle-form__legend">
      {{ t('cycles.form.sections.fee') }}
      <CycleStepCounter />
    </legend>
    <FieldToggle
      name="entryFeePaid"
      :label="t('cycles.setup.fee.paid')"
      :description="paid ? t('cycles.setup.fee.paidState') : t('cycles.setup.fee.freeState')"
      :disabled="isLocked"
    />
    <div v-if="paid" class="cycle-form__grid">
      <FieldInput
        name="entryFee"
        :label="t('cycles.form.entryFee')"
        inputmode="decimal"
        input-dir="ltr"
        required
        :disabled="isLocked"
      />
    </div>
    <p v-if="isLocked" class="cycle-form__note" role="note" data-testid="cycle-fee-locked">
      <AppIcon name="lock" />
      {{ t('cycles.form.entryFeeLocked') }}
    </p>
  </fieldset>
</template>
