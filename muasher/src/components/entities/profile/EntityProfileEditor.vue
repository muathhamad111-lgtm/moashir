<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import FieldCheckbox from '@/components/form/FieldCheckbox.vue'
import EntityIdentityFields from '@/components/entities/form/EntityIdentityFields.vue'
import EntityClassificationFields from '@/components/entities/form/EntityClassificationFields.vue'
import EntityLocationFields from '@/components/entities/form/EntityLocationFields.vue'
import EntityMetricsFields from '@/components/entities/form/EntityMetricsFields.vue'
import ProfileChangeReview from './ProfileChangeReview.vue'
import { EDITOR_STEPS, useEntityProfileEditor } from '@/composables/useEntityProfileEditor'
import { useProfileChangeLabels } from '@/composables/useProfileChangeLabels'

const props = defineProps({
  profile: { type: Object, required: true },
  regionOptions: { type: Array, required: true },
  cityOptionsFor: { type: Function, required: true },
})

const emit = defineEmits(['saved', 'cancel'])

const { t } = useI18n()
const { form, step, changes, confirmSave, backToEdit, regionIdOf } = useEntityProfileEditor({
  profile: props.profile,
  onSaved: () => emit('saved'),
})
const { fieldLabel, valueLabel } = useProfileChangeLabels({
  regionOptions: toRef(props, 'regionOptions'),
  cityOptionsFor: props.cityOptionsFor,
  regionIdOf,
})
</script>

<template>
  <form class="entity-form" novalidate @submit.prevent="form.onSubmit">
    <fieldset v-show="step === EDITOR_STEPS.EDIT" class="entity-form__sections" :disabled="form.submitting.value">
      <EntityIdentityFields />
      <EntityClassificationFields />
      <EntityLocationFields :region-options="regionOptions" :city-options-for="cityOptionsFor" />
      <EntityMetricsFields :hint="t('entities.hints.metricsProfile')" />
      <fieldset class="entity-form__group">
        <legend class="entity-form__legend">{{ t('entities.sections.communication') }}</legend>
        <FieldCheckbox name="marketingConsent" :label="t('entities.consent.marketing')" />
        <p class="entity-form__hint">{{ t('entities.hints.marketingConsent') }}</p>
      </fieldset>
    </fieldset>

    <ProfileChangeReview
      v-if="step === EDITOR_STEPS.REVIEW"
      :changes="changes"
      :field-label="fieldLabel"
      :value-label="valueLabel"
    />

    <div class="entity-form__actions">
      <BaseButton
        v-if="step === EDITOR_STEPS.EDIT"
        variant="ghost"
        :disabled="form.submitting.value"
        @click="emit('cancel')"
      >
        {{ t('actions.cancel') }}
      </BaseButton>
      <BaseButton v-else variant="ghost" :disabled="form.submitting.value" @click="backToEdit">
        {{ t('entities.profile.backToEdit') }}
      </BaseButton>
      <BaseButton v-if="step === EDITOR_STEPS.EDIT" type="submit" variant="primary" :loading="form.submitting.value">
        {{ t('entities.profile.reviewChanges') }}
      </BaseButton>
      <BaseButton v-else variant="primary" :loading="form.submitting.value" @click="confirmSave">
        {{ t('entities.profile.save') }}
      </BaseButton>
    </div>
  </form>
</template>
