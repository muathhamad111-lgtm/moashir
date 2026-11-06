<script setup>
import { toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EntityIdentityFields from '@/components/entities/form/EntityIdentityFields.vue'
import EntityClassificationFields from '@/components/entities/form/EntityClassificationFields.vue'
import EntityLocationFields from '@/components/entities/form/EntityLocationFields.vue'
import EntityMetricsFields from '@/components/entities/form/EntityMetricsFields.vue'
import { useEntityEditOnBehalf } from '@/composables/useEntityEditOnBehalf'
import { useRegions } from '@/composables/useRegions'
import { REASON_LENGTH } from '@/constants/entityEnums'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  entity: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const regions = useRegions()
const { form, start, unifiedNumberRequired } = useEntityEditOnBehalf({
  entity: toRef(props, 'entity'),
  onSaved: (data) => {
    emit('update:modelValue', false)
    emit('saved', data)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    start()
    if (regions.status.value !== 'success') regions.load()
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :title="t('entities.edit.title')"
    :busy="form.submitting.value"
    :submit-label="t('entities.edit.submit')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="entity-form">
      <p class="entity-form__hint">{{ t('entities.edit.intro') }}</p>
      <Skeleton v-if="regions.status.value === 'loading'" variant="text" :count="4" />
      <fieldset v-else class="entity-form__sections" :disabled="form.submitting.value">
        <EntityIdentityFields :unified-number-required="unifiedNumberRequired" />
        <EntityClassificationFields />
        <EntityLocationFields
          :region-options="regions.regionOptions.value"
          :city-options-for="regions.cityOptionsFor"
        />
        <EntityMetricsFields />
        <FieldTextarea
          name="reason"
          :label="t('entities.reason.label')"
          :hint="t('entities.reason.hint', { min: REASON_LENGTH.MIN })"
          required
        />
      </fieldset>
    </div>
  </FormModal>
</template>
