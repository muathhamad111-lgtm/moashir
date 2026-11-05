<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, boolean as yupBoolean, string as yupString } from 'yup'
import FormModal from '@/components/modal/FormModal.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import AttachmentsEditor from './AttachmentsEditor.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useCriteria } from '@/composables/useCriteria'
import { renameFieldErrors } from '@/services/mappers/fieldErrors'
import { requiredText, weightText } from '@/utils/criteriaSchemas'
import { attachmentsPayload, cloneAttachments } from '@/utils/criteriaAttachments'
import { compareWeights, normalizeWeight, sumWeights } from '@/utils/weight'
import { CRITERIA_DESCRIPTION_MAX_LENGTH } from '@/constants/criteria'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  point: { type: Object, default: null },
  sub: { type: Object, required: true },
  save: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const { locale, weight: formatted } = useCriteria()

const FIELD_ALIASES = {
  'description.ar': 'descriptionAr',
  'description.en': 'descriptionEn',
  description: 'descriptionAr',
}

const isEdit = computed(() => Boolean(props.point))
const distributes = computed(() => Boolean(props.sub.distributeEqually))
const attachments = ref([])

const remaining = computed(() => {
  const ownShare = isEdit.value && props.point.active ? props.point.weight : '0'
  const value = sumWeights([props.sub.remainingPointWeight, ownShare])
  return compareWeights(value, '0') < 0 ? '0.00' : value
})

function weightRule() {
  if (distributes.value) return yupString()
  const capped = weightText(t, { max: remaining.value, locale: locale.value })
  if (isEdit.value) return props.point.active ? capped : weightText(t)
  return yupString().when('active', ([active]) => (active ? capped : weightText(t)))
}

const schema = computed(() =>
  yupObject({
    descriptionAr: requiredText(t, CRITERIA_DESCRIPTION_MAX_LENGTH),
    descriptionEn: requiredText(t, CRITERIA_DESCRIPTION_MAX_LENGTH),
    weight: weightRule(),
    active: yupBoolean(),
  }),
)

function valuesOf(point) {
  return {
    descriptionAr: point?.description?.ar ?? '',
    descriptionEn: point?.description?.en ?? '',
    weight: point?.weight ?? (distributes.value ? '' : remaining.value),
    active: point?.active ?? true,
  }
}

function payloadOf(values) {
  const payload = {
    description: { ar: values.descriptionAr.trim(), en: values.descriptionEn.trim() },
    attachments: attachmentsPayload(attachments.value),
  }
  if (!distributes.value) payload.weight = normalizeWeight(values.weight)
  return isEdit.value ? payload : { ...payload, active: values.active }
}

const form = useValidatedForm({
  schema,
  initialValues: valuesOf(props.point),
  action: 'criteria.pointForm.submit',
  submit: async (values) => {
    try {
      await props.save({ id: props.point?.id ?? null, payload: payloadOf(values) })
      emit('update:modelValue', false)
    } catch (error) {
      throw renameFieldErrors(error, FIELD_ALIASES)
    }
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    form.resetForm({ values: valuesOf(props.point) })
    attachments.value = cloneAttachments(props.point?.attachments)
  },
)

const [descriptionAr] = form.defineField('descriptionAr')
const [descriptionEn] = form.defineField('descriptionEn')
const [weight] = form.defineField('weight')
const [active] = form.defineField('active')
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :busy="form.submitting.value"
    :title="isEdit ? t('criteria.point.editTitle') : t('criteria.point.createTitle')"
    :submit-label="isEdit ? t('actions.save') : t('criteria.point.create')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="criteria-form">
      <BaseTextarea
        v-model="descriptionAr"
        dir="rtl"
        name="pointDescriptionAr"
        :rows="2"
        :label="t('criteria.fields.descriptionAr')"
        :error="form.errors.value.descriptionAr"
        required
      />
      <BaseTextarea
        v-model="descriptionEn"
        dir="ltr"
        name="pointDescriptionEn"
        :rows="2"
        :label="t('criteria.fields.descriptionEn')"
        :error="form.errors.value.descriptionEn"
        required
      />
      <BaseInput
        v-if="!distributes"
        v-model="weight"
        name="pointWeight"
        :label="t('criteria.fields.weight')"
        :hint="t('criteria.fields.remainingWeight', { weight: formatted(remaining) })"
        :error="form.errors.value.weight"
        required
      />
      <p v-else class="criteria-form__note" role="note">
        <AppIcon name="scale" />
        <span>{{ t('criteria.point.equalNote') }}</span>
      </p>

      <AttachmentsEditor v-model="attachments" />

      <BaseCheckbox
        v-if="!isEdit"
        v-model="active"
        name="pointActive"
        :label="t('criteria.fields.active')"
      />
    </div>
  </FormModal>
</template>
