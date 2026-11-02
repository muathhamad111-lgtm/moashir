<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, boolean as yupBoolean, string as yupString } from 'yup'
import FormModal from '@/components/modal/FormModal.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import BaseRadio from '@/components/form/BaseRadio.vue'
import AttachmentsEditor from './AttachmentsEditor.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useCriteria } from '@/composables/useCriteria'
import { renameFieldErrors } from '@/services/mappers/fieldErrors'
import { pairedText, requiredText, weightText } from '@/utils/criteriaSchemas'
import { cloneAttachments, attachmentsPayload } from '@/utils/criteriaAttachments'
import { compareWeights, normalizeWeight } from '@/utils/weight'
import { CRITERIA_DESCRIPTION_MAX_LENGTH, EVALUATION_TYPES } from '@/constants/criteria'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  criterion: { type: Object, default: null },
  main: { type: Object, required: true },
  save: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const { locale, weight: formatted } = useCriteria()

const FIELD_ALIASES = {
  'name.ar': 'nameAr',
  'name.en': 'nameEn',
  'description.ar': 'descriptionAr',
  'description.en': 'descriptionEn',
  'assessorNoteLabel.ar': 'noteAr',
  'assessorNoteLabel.en': 'noteEn',
}

const isEdit = computed(() => Boolean(props.criterion))
const parentDistributes = computed(() => Boolean(props.main.distributeEqually))
const attachments = ref([])

const available = computed(() => {
  const value = isEdit.value ? props.criterion.availableWeight : props.main.remainingWeight
  return compareWeights(value, '0') < 0 ? '0.00' : value
})

const evaluationOptions = computed(() => [
  { value: EVALUATION_TYPES.GUIDING_POINTS, label: t('criteria.evaluationType.guidingPoints') },
  { value: EVALUATION_TYPES.DIRECT, label: t('criteria.evaluationType.direct') },
])

function weightRule() {
  if (parentDistributes.value) return yupString()
  const capped = weightText(t, { max: available.value, locale: locale.value })
  const free = weightText(t)
  if (isEdit.value) return props.criterion.active ? capped : free
  return yupString().when('active', ([active]) => (active ? capped : free))
}

const schema = computed(() =>
  yupObject({
    nameAr: requiredText(t),
    nameEn: requiredText(t),
    descriptionAr: pairedText(t, 'descriptionEn', CRITERIA_DESCRIPTION_MAX_LENGTH),
    descriptionEn: pairedText(t, 'descriptionAr', CRITERIA_DESCRIPTION_MAX_LENGTH),
    noteAr: pairedText(t, 'noteEn'),
    noteEn: pairedText(t, 'noteAr'),
    weight: weightRule(),
    evaluationType: yupString().required(t('validation.required')),
    distributeEqually: yupBoolean(),
    hasCv: yupBoolean(),
    active: yupBoolean(),
  }),
)

function valuesOf(sub) {
  return {
    nameAr: sub?.name?.ar ?? '',
    nameEn: sub?.name?.en ?? '',
    descriptionAr: sub?.description?.ar ?? '',
    descriptionEn: sub?.description?.en ?? '',
    noteAr: sub?.assessorNoteLabel?.ar ?? '',
    noteEn: sub?.assessorNoteLabel?.en ?? '',
    weight: sub?.weight ?? '',
    evaluationType: sub?.evaluationType ?? EVALUATION_TYPES.GUIDING_POINTS,
    distributeEqually: Boolean(sub?.distributeEqually),
    hasCv: sub?.hasCv ?? true,
    active: sub?.active ?? true,
  }
}

function pairOf(ar, en) {
  return ar.trim() || en.trim() ? { ar: ar.trim(), en: en.trim() } : null
}

function payloadOf(values) {
  const payload = {
    name: { ar: values.nameAr.trim(), en: values.nameEn.trim() },
    description: pairOf(values.descriptionAr, values.descriptionEn),
    assessorNoteLabel: pairOf(values.noteAr, values.noteEn),
    distributeEqually:
      values.evaluationType === EVALUATION_TYPES.GUIDING_POINTS && values.distributeEqually,
    hasCv: values.hasCv,
    attachments: attachmentsPayload(attachments.value),
  }
  if (!parentDistributes.value) payload.weight = normalizeWeight(values.weight)
  if (isEdit.value) return payload
  return {
    ...payload,
    mainCriterionId: props.main.id,
    evaluationType: values.evaluationType,
    active: values.active,
  }
}

const form = useValidatedForm({
  schema,
  initialValues: valuesOf(props.criterion),
  action: 'criteria.subForm.submit',
  submit: async (values) => {
    try {
      await props.save({ id: props.criterion?.id ?? null, payload: payloadOf(values) })
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
    form.resetForm({ values: valuesOf(props.criterion) })
    attachments.value = cloneAttachments(props.criterion?.attachments)
  },
)

const [nameAr] = form.defineField('nameAr')
const [nameEn] = form.defineField('nameEn')
const [descriptionAr] = form.defineField('descriptionAr')
const [descriptionEn] = form.defineField('descriptionEn')
const [noteAr] = form.defineField('noteAr')
const [noteEn] = form.defineField('noteEn')
const [weight] = form.defineField('weight')
const [evaluationType] = form.defineField('evaluationType')
const [distributeEqually] = form.defineField('distributeEqually')
const [hasCv] = form.defineField('hasCv')
const [active] = form.defineField('active')

const isGuidingPoints = computed(() => evaluationType.value === EVALUATION_TYPES.GUIDING_POINTS)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :busy="form.submitting.value"
    :title="isEdit ? t('criteria.sub.editTitle') : t('criteria.sub.createTitle')"
    :submit-label="isEdit ? t('actions.save') : t('criteria.sub.create')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="criteria-form">
      <div class="criteria-form__grid">
        <BaseInput
          v-model="nameAr"
          dir="rtl"
          name="subNameAr"
          :label="t('criteria.fields.nameAr')"
          :error="form.errors.value.nameAr"
          required
        />
        <BaseInput
          v-model="nameEn"
          dir="ltr"
          name="subNameEn"
          :label="t('criteria.fields.nameEn')"
          :error="form.errors.value.nameEn"
          required
        />
      </div>
      <BaseInput
        v-if="!parentDistributes"
        v-model="weight"
        name="subWeight"
        :label="t('criteria.fields.weight')"
        :hint="t('criteria.fields.availableWeight', { weight: formatted(available) })"
        :error="form.errors.value.weight"
        required
      />
      <p v-else class="criteria-form__note" role="note">
        <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
        <span>{{ t('criteria.fields.equalWeightNote') }}</span>
      </p>

      <template v-if="!isEdit">
        <BaseRadio
          v-model="evaluationType"
          name="evaluationType"
          :label="t('criteria.fields.evaluationType')"
          :options="evaluationOptions"
          :error="form.errors.value.evaluationType"
        />
        <p class="criteria-form__note" role="note">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          <span>{{
            isGuidingPoints
              ? t('criteria.evaluationType.guidingHint')
              : t('criteria.evaluationType.directHint')
          }}</span>
        </p>
      </template>
      <p v-else class="criteria-form__note" role="note">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span>{{ t('criteria.evaluationType.editNote') }}</span>
      </p>

      <BaseTextarea
        v-model="descriptionAr"
        dir="rtl"
        name="subDescriptionAr"
        :rows="2"
        :label="t('criteria.fields.descriptionAr')"
        :error="form.errors.value.descriptionAr"
      />
      <BaseTextarea
        v-model="descriptionEn"
        dir="ltr"
        name="subDescriptionEn"
        :rows="2"
        :label="t('criteria.fields.descriptionEn')"
        :error="form.errors.value.descriptionEn"
      />
      <div class="criteria-form__grid">
        <BaseInput
          v-model="noteAr"
          dir="rtl"
          name="subNoteAr"
          :label="t('criteria.fields.assessorNoteAr')"
          :hint="t('criteria.fields.assessorNoteHint')"
          :error="form.errors.value.noteAr"
        />
        <BaseInput
          v-model="noteEn"
          dir="ltr"
          name="subNoteEn"
          :label="t('criteria.fields.assessorNoteEn')"
          :error="form.errors.value.noteEn"
        />
      </div>

      <AttachmentsEditor v-model="attachments" />

      <div class="criteria-form__toggles">
        <BaseCheckbox
          v-if="isGuidingPoints"
          v-model="distributeEqually"
          name="subDistributeEqually"
          :label="t('criteria.fields.distributeEquallyPoints')"
        />
        <BaseCheckbox v-model="hasCv" name="subHasCv" :label="t('criteria.fields.hasCv')" />
        <BaseCheckbox
          v-if="!isEdit"
          v-model="active"
          name="subActive"
          :label="t('criteria.fields.active')"
        />
      </div>
    </div>
  </FormModal>
</template>
