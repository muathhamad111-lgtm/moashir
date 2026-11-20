<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useFormShell } from '@/composables/useFormShell'
import { renameFieldErrors } from '@/services/mappers/fieldErrors'
import AppIcon from '@/components/ui/AppIcon.vue'
import AttachmentsEditor from './AttachmentsEditor.vue'
import { cloneAttachments } from '@/utils/criteriaAttachments'
import * as mainForm from '@/utils/criteriaMainForm'
import BaseRadio from '@/components/form/BaseRadio.vue'
import CriteriaWizardReview from './CriteriaWizardReview.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaFormLayout } from '@/composables/useCriteriaFormLayout'
import { useScoringTypeOptions } from '@/composables/useScoringTypeOptions'
import { MAIN_FIELD_SECTIONS, MAIN_WIZARD } from '@/utils/criteriaFormSections'
import { mainReviewRows } from '@/utils/criteriaWizardReview'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  inline: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  criterion: { type: Object, default: null },
  save: { type: Function, required: true },
  // In the editor sheet: the tab to show (basics | attachments). Empty = every field (create dialog).
  section: { type: String, default: '' },
  // Create only: the fields come in short steps in a drawer (UX-A2).
  wizard: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'section-errors', 'dirty'])
const { t } = useI18n()

const isEdit = computed(() => Boolean(props.criterion))
const attachments = ref(cloneAttachments(props.criterion?.attachments))
const groupOptions = computed(() => mainForm.mainGroupOptions(t))
const schema = computed(() => mainForm.mainFormSchema(t))

const form = useValidatedForm({
  schema,
  initialValues: mainForm.mainFormValues(props.criterion),
  action: 'criteria.mainForm.submit',
  submit: async (values) => {
    try {
      await props.save({
        id: props.criterion?.id ?? null,
        payload: mainForm.mainFormPayload(values, attachments.value, { isEdit: isEdit.value }),
      })
      emit('update:modelValue', false)
    } catch (error) {
      throw renameFieldErrors(error, mainForm.MAIN_FIELD_ALIASES)
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
      form.resetForm({ values: mainForm.mainFormValues(props.criterion) })
      attachments.value = cloneAttachments(props.criterion?.attachments)
    },
  },
  emit,
)

const [nameAr] = form.defineField('nameAr')
const [nameEn] = form.defineField('nameEn')
const [descriptionAr] = form.defineField('descriptionAr')
const [descriptionEn] = form.defineField('descriptionEn')
const [weight] = form.defineField('weight')
const [group] = form.defineField('group')
const [defaultScoringType] = form.defineField('defaultScoringType')
const [distributeEqually] = form.defineField('distributeEqually')
const [active] = form.defineField('active')

const { weight: formatted } = useCriteria()
const { options: scoringOptions, labelOf: typeLabel } = useScoringTypeOptions()
const { sections, steps, shellProps, inWizard } = useCriteriaFormLayout(props, form, emit, {
  sheetSections: MAIN_FIELD_SECTIONS,
  wizard: MAIN_WIZARD,
  attachments,
  record: () => props.criterion,
})
const reviewRows = computed(() => mainReviewRows(form.values, { t, typeLabel, weight: formatted }))
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
          ? t('criteria.main.editTitle')
          : t('criteria.main.createTitle')
    "
    :submit-label="isEdit ? t('actions.save') : t('criteria.main.create')"
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
          name="nameAr"
          :label="t('criteria.fields.nameAr')"
          :error="form.errors.value.nameAr"
          required
        />
        <BaseInput
          v-model="nameEn"
          dir="ltr"
          name="nameEn"
          :label="t('criteria.fields.nameEn')"
          :error="form.errors.value.nameEn"
          required
        />
      </div>
      <div class="criteria-form__grid">
        <BaseInput
          v-show="sections.shows(inWizard() ? 'weight' : 'basics')"
          v-model="weight"
          name="weight"
          :label="t('criteria.fields.weight')"
          :hint="t('criteria.fields.weightHint')"
          :error="form.errors.value.weight"
          required
        />
        <AppSelect
          v-show="sections.shows('basics')"
          v-model="group"
          name="group"
          :label="t('criteria.fields.group')"
          :options="groupOptions"
          :error="form.errors.value.group"
          required
        />
      </div>
      <BaseTextarea
        v-show="sections.shows('basics')"
        v-model="descriptionAr"
        dir="rtl"
        name="descriptionAr"
        :rows="2"
        :label="t('criteria.fields.descriptionAr')"
        :error="form.errors.value.descriptionAr"
      />
      <BaseTextarea
        v-show="sections.shows('basics')"
        v-model="descriptionEn"
        dir="ltr"
        name="descriptionEn"
        :rows="2"
        :label="t('criteria.fields.descriptionEn')"
        :error="form.errors.value.descriptionEn"
      />
      <AttachmentsEditor
        v-show="sections.shows('attachments')"
        v-model="attachments"
        :errors="form.serverErrors.value"
      />
      <BaseRadio
        v-if="!isEdit"
        v-show="sections.shows('evaluation')"
        v-model="defaultScoringType"
        name="mainDefaultScoringType"
        :label="t('criteria.workspace.scoring.label.main')"
        :options="scoringOptions"
      />
      <div
        v-if="!sections.inSheet.value"
        v-show="sections.shows('weight')"
        class="criteria-form__toggles"
      >
        <BaseCheckbox
          v-model="distributeEqually"
          name="distributeEqually"
          :label="t('criteria.fields.distributeEquallySubs')"
        />
        <BaseCheckbox
          v-if="!isEdit"
          v-model="active"
          name="active"
          :label="t('criteria.fields.active')"
        />
      </div>
      <p v-if="isEdit && !sections.inSheet.value" class="criteria-form__note" role="note">
        <AppIcon name="info-circle" />
        <span>{{ t('criteria.fields.orderNote') }}</span>
      </p>
      <CriteriaWizardReview v-if="inWizard() && sections.shows('review')" :rows="reviewRows" />
    </div>
  </component>
</template>
