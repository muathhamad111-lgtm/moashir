<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, boolean as yupBoolean, string as yupString } from 'yup'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import BaseRadio from '@/components/form/BaseRadio.vue'
import AttachmentsEditor from './AttachmentsEditor.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useFormShell } from '@/composables/useFormShell'
import { useCriteria } from '@/composables/useCriteria'
import { renameFieldErrors } from '@/services/mappers/fieldErrors'
import { pairedText, requiredText, weightText } from '@/utils/criteriaSchemas'
import { attachmentsPayload, cloneAttachments } from '@/utils/criteriaAttachments'
import { compareWeights, normalizeWeight } from '@/utils/weight'
import {
  CRITERIA_DESCRIPTION_MAX_LENGTH,
  EVALUATION_TYPES,
  SCORING_TYPES,
} from '@/constants/criteria'
import AppIcon from '@/components/ui/AppIcon.vue'
import CriteriaWizardReview from './CriteriaWizardReview.vue'
import { useCriteriaFormLayout } from '@/composables/useCriteriaFormLayout'
import { useScoringTypeOptions } from '@/composables/useScoringTypeOptions'
import { SUB_FIELD_SECTIONS, SUB_WIZARD } from '@/utils/criteriaFormSections'
import { subReviewRows } from '@/utils/criteriaWizardReview'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  inline: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  criterion: { type: Object, default: null },
  main: { type: Object, required: true },
  save: { type: Function, required: true },
  // In the editor sheet: the tab to show (basics | attachments | advanced). Empty = every field.
  // The sheet edits the weight in the parent's split only, so this form neither shows nor sends it.
  section: { type: String, default: '' },
  // Create only: the fields come in short steps in a drawer (UX-A2).
  wizard: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'section-errors', 'dirty'])
const { t } = useI18n()
const { locale, weight: formatted } = useCriteria()
const { options: scoringOptions, labelOf: typeLabel } = useScoringTypeOptions()

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
const weightElsewhere = computed(() => parentDistributes.value || Boolean(props.section))
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
  if (weightElsewhere.value) return yupString()
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
    scoringType: yupString(),
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
    scoringType: sub?.scoringType ?? props.main.defaultScoringType ?? SCORING_TYPES.SCORE,
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
  if (!weightElsewhere.value) payload.weight = normalizeWeight(values.weight)
  if (isEdit.value) return payload
  return {
    ...payload,
    mainCriterionId: props.main.id,
    evaluationType: values.evaluationType,
    // A direct sub-criterion is scored with this type; one on indicators gives it to new indicators.
    [values.evaluationType === EVALUATION_TYPES.DIRECT ? 'scoringType' : 'defaultPointScoringType']:
      values.scoringType,
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

const formShell = useFormShell(
  {
    inline: () => props.inline,
    open: () => props.modelValue,
    record: () => props.criterion,
    wizard: () => props.wizard,
    reset: () => {
      form.resetForm({ values: valuesOf(props.criterion) })
      attachments.value = cloneAttachments(props.criterion?.attachments)
    },
  },
  emit,
)

const [nameAr] = form.defineField('nameAr')
const [nameEn] = form.defineField('nameEn')
const [descriptionAr] = form.defineField('descriptionAr')
const [descriptionEn] = form.defineField('descriptionEn')
const [noteAr] = form.defineField('noteAr')
const [noteEn] = form.defineField('noteEn')
const [weight] = form.defineField('weight')
const [evaluationType] = form.defineField('evaluationType')
const [scoringType] = form.defineField('scoringType')
const [distributeEqually] = form.defineField('distributeEqually')
const [hasCv] = form.defineField('hasCv')
const [active] = form.defineField('active')

const isGuidingPoints = computed(() => evaluationType.value === EVALUATION_TYPES.GUIDING_POINTS)

const { sections, steps, shellProps, inWizard } = useCriteriaFormLayout(props, form, emit, {
  sheetSections: SUB_FIELD_SECTIONS,
  wizard: SUB_WIZARD,
  attachments,
  record: () => props.criterion,
})
const reviewRows = computed(() =>
  subReviewRows(form.values, {
    t,
    typeLabel,
    weight: formatted,
    parentDistributes: parentDistributes.value,
    attachmentCount: attachments.value.length,
  }),
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
          ? t('criteria.sub.editTitle')
          : t('criteria.sub.createTitle')
    "
    :submit-label="isEdit ? t('actions.save') : t('criteria.sub.create')"
    @update:model-value="formShell.toggle"
    @submit="form.onSubmit"
    @next="steps.next"
    @back="steps.back"
  >
    <div class="criteria-form">
      <div v-show="sections.shows('basics')" class="criteria-form__grid">
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
      <div v-show="sections.shows('weight')" class="criteria-form__group">
        <BaseInput
          v-if="!weightElsewhere"
          v-model="weight"
          name="subWeight"
          :label="t('criteria.fields.weight')"
          :hint="t('criteria.fields.availableWeight', { weight: formatted(available) })"
          :error="form.errors.value.weight"
          required
        />
        <p v-else-if="!sections.inSheet.value" class="criteria-form__note" role="note">
          <AppIcon name="scale" />
          <span>{{ t('criteria.fields.equalWeightNote') }}</span>
        </p>
      </div>

      <div v-if="!isEdit" v-show="sections.shows('evaluation')" class="criteria-form__group">
        <BaseRadio
          v-model="evaluationType"
          name="evaluationType"
          :label="t('criteria.fields.evaluationType')"
          :options="evaluationOptions"
          :error="form.errors.value.evaluationType"
        />
        <p class="criteria-form__note" role="note">
          <AppIcon name="info-circle" />
          <span>{{
            isGuidingPoints
              ? t('criteria.evaluationType.guidingHint')
              : t('criteria.evaluationType.directHint')
          }}</span>
        </p>
        <BaseRadio
          v-model="scoringType"
          name="subScoringType"
          :label="
            isGuidingPoints
              ? t('criteria.workspace.scoring.label.points')
              : t('criteria.workspace.scoring.label.direct')
          "
          :options="scoringOptions"
          :error="form.errors.value.scoringType"
        />
      </div>
      <p v-else-if="!sections.inSheet.value" class="criteria-form__note" role="note">
        <AppIcon name="info-circle" />
        <span>{{ t('criteria.evaluationType.editNote') }}</span>
      </p>

      <BaseTextarea
        v-show="sections.shows('basics')"
        v-model="descriptionAr"
        dir="rtl"
        name="subDescriptionAr"
        :rows="2"
        :label="t('criteria.fields.descriptionAr')"
        :error="form.errors.value.descriptionAr"
      />
      <BaseTextarea
        v-show="sections.shows('basics')"
        v-model="descriptionEn"
        dir="ltr"
        name="subDescriptionEn"
        :rows="2"
        :label="t('criteria.fields.descriptionEn')"
        :error="form.errors.value.descriptionEn"
      />
      <div v-show="sections.shows('advanced')" class="criteria-form__grid">
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

      <AttachmentsEditor
        v-show="sections.shows('attachments')"
        v-model="attachments"
        :errors="form.serverErrors.value"
      />

      <div v-show="sections.shows('advanced')" class="criteria-form__toggles">
        <BaseCheckbox
          v-if="isGuidingPoints && !sections.inSheet.value"
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
      <CriteriaWizardReview v-if="inWizard() && sections.shows('review')" :rows="reviewRows" />
    </div>
  </component>
</template>
