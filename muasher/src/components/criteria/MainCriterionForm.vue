<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, boolean as yupBoolean, string as yupString } from 'yup'
import FormModal from '@/components/modal/FormModal.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { renameFieldErrors } from '@/services/mappers/fieldErrors'
import { pairedText, requiredText, weightText } from '@/utils/criteriaSchemas'
import { normalizeWeight } from '@/utils/weight'
import {
  CRITERIA_DESCRIPTION_MAX_LENGTH,
  CRITERION_GROUP_ORDER,
  CRITERION_GROUPS,
} from '@/constants/criteria'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  criterion: { type: Object, default: null },
  save: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])
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

function valuesOf(criterion) {
  return {
    nameAr: criterion?.name?.ar ?? '',
    nameEn: criterion?.name?.en ?? '',
    descriptionAr: criterion?.description?.ar ?? '',
    descriptionEn: criterion?.description?.en ?? '',
    weight: criterion?.weight ?? '',
    group: criterion?.group ?? CRITERION_GROUPS.ENABLERS,
    distributeEqually: Boolean(criterion?.distributeEqually),
    active: criterion?.active ?? true,
  }
}

function payloadOf(values) {
  const hasDescription = values.descriptionAr.trim() || values.descriptionEn.trim()
  const payload = {
    name: { ar: values.nameAr.trim(), en: values.nameEn.trim() },
    description: hasDescription
      ? { ar: values.descriptionAr.trim(), en: values.descriptionEn.trim() }
      : null,
    weight: normalizeWeight(values.weight),
    group: values.group,
    distributeEqually: values.distributeEqually,
  }
  return isEdit.value ? payload : { ...payload, active: values.active }
}

const form = useValidatedForm({
  schema,
  initialValues: valuesOf(props.criterion),
  action: 'criteria.mainForm.submit',
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
    if (open) form.resetForm({ values: valuesOf(props.criterion) })
  },
)

const [nameAr] = form.defineField('nameAr')
const [nameEn] = form.defineField('nameEn')
const [descriptionAr] = form.defineField('descriptionAr')
const [descriptionEn] = form.defineField('descriptionEn')
const [weight] = form.defineField('weight')
const [group] = form.defineField('group')
const [distributeEqually] = form.defineField('distributeEqually')
const [active] = form.defineField('active')
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :busy="form.submitting.value"
    :title="isEdit ? t('criteria.main.editTitle') : t('criteria.main.createTitle')"
    :submit-label="isEdit ? t('actions.save') : t('criteria.main.create')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="criteria-form">
      <div class="criteria-form__grid">
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
        v-model="descriptionAr"
        dir="rtl"
        name="descriptionAr"
        :rows="2"
        :label="t('criteria.fields.descriptionAr')"
        :error="form.errors.value.descriptionAr"
      />
      <BaseTextarea
        v-model="descriptionEn"
        dir="ltr"
        name="descriptionEn"
        :rows="2"
        :label="t('criteria.fields.descriptionEn')"
        :error="form.errors.value.descriptionEn"
      />
      <div class="criteria-form__toggles">
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
      <p v-if="isEdit" class="criteria-form__note" role="note">
        <AppIcon name="info-circle" />
        <span>{{ t('criteria.fields.orderNote') }}</span>
      </p>
    </div>
  </FormModal>
</template>
