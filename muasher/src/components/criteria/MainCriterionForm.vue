<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, boolean as yupBoolean, string as yupString } from 'yup'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useFormShell } from '@/composables/useFormShell'
import { renameFieldErrors } from '@/services/mappers/fieldErrors'
import { pairedText, requiredText, weightText } from '@/utils/criteriaSchemas'
import { CRITERIA_DESCRIPTION_MAX_LENGTH, CRITERION_GROUP_ORDER } from '@/constants/criteria'
import AppIcon from '@/components/ui/AppIcon.vue'
import AttachmentsEditor from './AttachmentsEditor.vue'
import { cloneAttachments } from '@/utils/criteriaAttachments'
import { mainFormPayload, mainFormValues } from '@/utils/criteriaMainForm'
import { useFormSections } from '@/composables/useFormSections'
import { MAIN_FIELD_SECTIONS } from '@/utils/criteriaFormSections'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  inline: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  criterion: { type: Object, default: null },
  save: { type: Function, required: true },
  // In the editor sheet: the tab to show (basics | attachments). Empty = every field (create dialog).
  section: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'section-errors', 'dirty'])
const { t } = useI18n()

const FIELD_ALIASES = {
  'name.ar': 'nameAr',
  'name.en': 'nameEn',
  name: 'nameAr',
  'description.ar': 'descriptionAr',
  'description.en': 'descriptionEn',
  description: 'descriptionAr',
}

const isEdit = computed(() => Boolean(props.criterion))
const attachments = ref(cloneAttachments(props.criterion?.attachments))
const groupOptions = computed(() =>
  CRITERION_GROUP_ORDER.map((group) => ({ value: group, label: t(`criteria.groups.${group}`) })),
)

const schema = computed(() =>
  yupObject({
    nameAr: requiredText(t),
    nameEn: requiredText(t),
    descriptionAr: pairedText(t, 'descriptionEn', CRITERIA_DESCRIPTION_MAX_LENGTH),
    descriptionEn: pairedText(t, 'descriptionAr', CRITERIA_DESCRIPTION_MAX_LENGTH),
    weight: weightText(t),
    group: yupString().required(t('validation.required')),
    distributeEqually: yupBoolean(),
    active: yupBoolean(),
  }),
)

const form = useValidatedForm({
  schema,
  initialValues: mainFormValues(props.criterion),
  action: 'criteria.mainForm.submit',
  submit: async (values) => {
    try {
      await props.save({
        id: props.criterion?.id ?? null,
        payload: mainFormPayload(values, attachments.value, { isEdit: isEdit.value }),
      })
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
    reset: () => {
      form.resetForm({ values: mainFormValues(props.criterion) })
      attachments.value = cloneAttachments(props.criterion?.attachments)
    },
  },
  emit,
)
const shellProps = computed(() =>
  props.inline ? { readonly: props.readonly } : { modelValue: props.modelValue, size: 'lg' },
)

const [nameAr] = form.defineField('nameAr')
const [nameEn] = form.defineField('nameEn')
const [descriptionAr] = form.defineField('descriptionAr')
const [descriptionEn] = form.defineField('descriptionEn')
const [weight] = form.defineField('weight')
const [group] = form.defineField('group')
const [distributeEqually] = form.defineField('distributeEqually')
const [active] = form.defineField('active')

const sections = useFormSections(
  {
    section: () => props.section,
    fieldSections: MAIN_FIELD_SECTIONS,
    attachments,
    record: () => props.criterion,
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
          ? t('criteria.main.editTitle')
          : t('criteria.main.createTitle')
    "
    :submit-label="isEdit ? t('actions.save') : t('criteria.main.create')"
    @update:model-value="formShell.toggle"
    @submit="form.onSubmit"
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
        <BaseInput
          v-model="weight"
          name="weight"
          :label="t('criteria.fields.weight')"
          :hint="t('criteria.fields.weightHint')"
          :error="form.errors.value.weight"
          required
        />
        <AppSelect
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
      <div v-if="!sections.inSheet.value" class="criteria-form__toggles">
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
    </div>
  </component>
</template>
