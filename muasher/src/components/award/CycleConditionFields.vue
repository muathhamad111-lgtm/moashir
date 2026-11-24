<script setup>
import { useI18n } from 'vue-i18n'
import CycleStepCounter from './CycleStepCounter.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldToggle from '@/components/form/FieldToggle.vue'
import CycleRegionsField from './CycleRegionsField.vue'

defineProps({
  locked: { type: Object, required: true },
  regions: { type: Array, required: true },
  lockedRegionIds: { type: Array, default: () => [] },
  canSetParticipation: { type: Boolean, default: false },
  thresholdLocked: { type: Boolean, default: false },
})

const { t } = useI18n()
</script>

<template>
  <fieldset class="cycle-form__section">
    <legend class="cycle-form__legend">
      {{ t('cycles.form.sections.conditions') }}
      <CycleStepCounter />
    </legend>
    <CycleRegionsField
      :regions="regions"
      :locked-region-ids="lockedRegionIds"
      :disabled="locked.has('regionIds')"
    />
    <div class="cycle-form__grid">
      <FieldInput
        name="minEntityAgeYears"
        :label="t('cycles.form.minEntityAgeYears')"
        inputmode="numeric"
        input-dir="ltr"
        required
        :disabled="locked.has('minEntityAgeYears')"
      />
      <FieldInput
        name="disqualificationScore"
        :label="t('cycles.form.disqualificationScore')"
        inputmode="decimal"
        input-dir="ltr"
        :hint="thresholdLocked ? t('cycles.form.thresholdLockedHint') : ''"
        required
        :disabled="locked.has('disqualificationScore')"
      />
    </div>
    <FieldToggle
      name="participationCertificateEnabled"
      :label="t('cycles.form.participationCertificate')"
      :description="
        canSetParticipation
          ? t('cycles.form.participationCertificateHint')
          : t('cycles.form.participationReserved')
      "
      :disabled="!canSetParticipation || locked.has('participationCertificateEnabled')"
    />
  </fieldset>
</template>
