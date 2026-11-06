<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldSelect from '@/components/form/FieldSelect.vue'
import FieldToggle from '@/components/form/FieldToggle.vue'
import FieldCheckboxGroup from '@/components/form/FieldCheckboxGroup.vue'
import { SCALE_KINDS } from '@/constants/assessment'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  locked: { type: Object, required: true },
  regions: { type: Array, required: true },
  published: { type: Object, default: null },
  versionId: { type: Number, default: null },
  canSetParticipation: { type: Boolean, default: false },
})

const { t, locale } = useI18n()

const regionOptions = computed(() =>
  props.regions.map((region) => ({
    value: region.id,
    label: localizedText(region.name, locale.value),
    disabled: props.locked.has('regionIds'),
  })),
)
const versionOptions = computed(() => {
  if (
    props.published &&
    (props.versionId === null || props.versionId === props.published.versionId)
  ) {
    return [
      {
        value: props.published.versionId,
        label: t('cycles.form.publishedVersion', { number: props.published.versionNumber }),
      },
    ]
  }
  return props.versionId === null
    ? []
    : [{ value: props.versionId, label: t('cycles.form.boundVersion', { id: props.versionId }) }]
})
const scaleOptions = computed(() =>
  Object.values(SCALE_KINDS).map((value) => ({
    value,
    label: t(`assessment.self.scaleNames.${value}`),
  })),
)
</script>

<template>
  <fieldset class="cycle-form__section">
    <legend class="cycle-form__legend">{{ t('cycles.form.sections.assessment') }}</legend>
    <FieldCheckboxGroup
      name="regionIds"
      :label="t('cycles.form.regions')"
      :options="regionOptions"
      :hint="t('cycles.form.regionsHint')"
    />
    <div class="cycle-form__grid">
      <FieldSelect
        name="criteriaVersionId"
        :label="t('cycles.form.criteriaVersion')"
        :options="versionOptions"
        :hint="t('cycles.form.criteriaVersionHint')"
        required
        :disabled="locked.has('criteriaVersionId')"
      />
      <FieldSelect
        name="scaleKind"
        :label="t('cycles.form.scaleKind')"
        :options="scaleOptions"
        required
        :disabled="locked.has('scaleKind')"
      />
    </div>
  </fieldset>
  <fieldset class="cycle-form__section">
    <legend class="cycle-form__legend">{{ t('cycles.form.sections.conditions') }}</legend>
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
        :hint="t('cycles.form.disqualificationScoreHint')"
        required
        :disabled="locked.has('disqualificationScore')"
      />
      <FieldInput
        name="initialReviewSlaDays"
        :label="t('cycles.form.initialReviewSlaDays')"
        inputmode="numeric"
        input-dir="ltr"
        :hint="t('cycles.form.initialReviewSlaDaysHint')"
        required
        :disabled="locked.has('initialReviewSlaDays')"
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
