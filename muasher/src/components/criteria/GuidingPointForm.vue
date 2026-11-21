<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, boolean as yupBoolean, string as yupString } from 'yup'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import BaseRadio from '@/components/form/BaseRadio.vue'
import AttachmentsEditor from './AttachmentsEditor.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useFormShell } from '@/composables/useFormShell'
import { useCriteria } from '@/composables/useCriteria'
import { useScoringTypeOptions } from '@/composables/useScoringTypeOptions'
import { renameFieldErrors } from '@/services/mappers/fieldErrors'
import { requiredText, weightText } from '@/utils/criteriaSchemas'
import { attachmentsPayload, cloneAttachments } from '@/utils/criteriaAttachments'
import { compareWeights, normalizeWeight, sumWeights } from '@/utils/weight'
import { CRITERIA_DESCRIPTION_MAX_LENGTH, SCORING_TYPES } from '@/constants/criteria'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useFormSections } from '@/composables/useFormSections'
import { POINT_FIELD_SECTIONS } from '@/utils/criteriaFormSections'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  inline: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  point: { type: Object, default: null },
  sub: { type: Object, required: true },
  save: { type: Function, required: true },
  // In the editor sheet: the tab to show (basics | evaluation | attachments). Empty = every field.
  // The sheet edits the weight in the sub-criterion's split only, so this form neither shows nor sends it.
  section: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'section-errors', 'dirty'])
const { t } = useI18n()
const { locale, weight: formatted } = useCriteria()

const FIELD_ALIASES = {
  'description.ar': 'descriptionAr',
  'description.en': 'descriptionEn',
  description: 'descriptionAr',
}

const isEdit = computed(() => Boolean(props.point))
const distributes = computed(() => Boolean(props.sub.distributeEqually))
const weightElsewhere = computed(() => distributes.value || Boolean(props.section))
const attachments = ref([])
const { options: scoringOptions } = useScoringTypeOptions()

const remaining = computed(() => {
  const ownShare = isEdit.value && props.point.active ? props.point.weight : '0'
  const value = sumWeights([props.sub.remainingPointWeight, ownShare])
  return compareWeights(value, '0') < 0 ? '0.00' : value
})

function weightRule() {
  if (weightElsewhere.value) return yupString()
  const capped = weightText(t, { max: remaining.value, locale: locale.value })
  if (isEdit.value) return props.point.active ? capped : weightText(t)
  return yupString().when('active', ([active]) => (active ? capped : weightText(t)))
}

const schema = computed(() =>
  yupObject({
    descriptionAr: requiredText(t, CRITERIA_DESCRIPTION_MAX_LENGTH),
    descriptionEn: requiredText(t, CRITERIA_DESCRIPTION_MAX_LENGTH),
    weight: weightRule(),
    scoringType: yupString().required(t('validation.required')),
    active: yupBoolean(),
  }),
)

function valuesOf(point) {
  return {
    descriptionAr: point?.description?.ar ?? '',
    descriptionEn: point?.description?.en ?? '',
    weight: point?.weight ?? (distributes.value ? '' : remaining.value),
    scoringType: point?.scoringType ?? props.sub.defaultPointScoringType ?? SCORING_TYPES.SCORE,
    active: point?.active ?? true,
  }
}

function payloadOf(values) {
  const payload = {
    description: { ar: values.descriptionAr.trim(), en: values.descriptionEn.trim() },
    scoringType: values.scoringType,
    attachments: attachmentsPayload(attachments.value),
  }
  if (!weightElsewhere.value) payload.weight = normalizeWeight(values.weight)
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

const formShell = useFormShell(
  {
    inline: () => props.inline,
    open: () => props.modelValue,
    record: () => props.point,
    reset: () => {
      form.resetForm({ values: valuesOf(props.point) })
      attachments.value = cloneAttachments(props.point?.attachments)
    },
  },
  emit,
)
const shellProps = computed(() =>
  props.inline ? { readonly: props.readonly } : { modelValue: props.modelValue, size: 'lg' },
)

const [descriptionAr] = form.defineField('descriptionAr')
const [descriptionEn] = form.defineField('descriptionEn')
const [weight] = form.defineField('weight')
const [scoringType] = form.defineField('scoringType')
const [active] = form.defineField('active')

const sections = useFormSections(
  {
    section: () => props.section,
    fieldSections: POINT_FIELD_SECTIONS,
    attachments,
    record: () => props.point,
  },
  form,
  emit,
)
</script>

<template>
  <component
    :is="formShell.shell.value"
    v-bind="shellProps"
    :busy="form.submitting.value"
    :title="
      inline
        ? sections.title.value
        : isEdit
          ? t('criteria.point.editTitle')
          : t('criteria.point.createTitle')
    "
    :submit-label="isEdit ? t('actions.save') : t('criteria.point.create')"
    @update:model-value="formShell.toggle"
    @submit="form.onSubmit"
  >
    <div class="criteria-form">
      <BaseTextarea
        v-show="sections.shows('basics')"
        v-model="descriptionAr"
        dir="rtl"
        name="pointDescriptionAr"
        :rows="2"
        :label="t('criteria.fields.descriptionAr')"
        :error="form.errors.value.descriptionAr"
        required
      />
      <BaseTextarea
        v-show="sections.shows('basics')"
        v-model="descriptionEn"
        dir="ltr"
        name="pointDescriptionEn"
        :rows="2"
        :label="t('criteria.fields.descriptionEn')"
        :error="form.errors.value.descriptionEn"
        required
      />
      <BaseInput
        v-if="!weightElsewhere"
        v-model="weight"
        name="pointWeight"
        :label="t('criteria.fields.weight')"
        :hint="t('criteria.fields.remainingWeight', { weight: formatted(remaining) })"
        :error="form.errors.value.weight"
        required
      />
      <p v-else-if="!sections.inSheet.value" class="criteria-form__note" role="note">
        <AppIcon name="scale" />
        <span>{{ t('criteria.point.equalNote') }}</span>
      </p>

      <BaseRadio
        v-show="sections.shows('evaluation')"
        v-model="scoringType"
        name="pointScoringType"
        :label="t('criteria.fields.scoringType')"
        :options="scoringOptions"
        :error="form.errors.value.scoringType"
      />

      <AttachmentsEditor
        v-show="sections.shows('attachments')"
        v-model="attachments"
        :errors="form.serverErrors.value"
      />

      <BaseCheckbox
        v-if="!isEdit"
        v-model="active"
        name="pointActive"
        :label="t('criteria.fields.active')"
      />
    </div>
  </component>
</template>
