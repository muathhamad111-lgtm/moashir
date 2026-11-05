<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, string as yupString } from 'yup'
import FormModal from '@/components/modal/FormModal.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useCriteria } from '@/composables/useCriteria'
import { useAuthStore } from '@/stores/auth'
import { criteriaService } from '@/services/criteriaService'
import { renameFieldErrors } from '@/services/mappers/fieldErrors'
import { pairedText, requiredText } from '@/utils/criteriaSchemas'
import {
  VERSION_TARGET,
  correctionAttachments,
  correctionFieldAliases,
  correctionValues,
  textCorrectionPayload,
} from '@/utils/criteriaTextCorrection'
import {
  CRITERIA_DESCRIPTION_MAX_LENGTH,
  CRITERIA_NODE_TYPES as NODE,
  CRITERIA_REASON_MIN_LENGTH,
} from '@/constants/criteria'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  version: { type: Object, required: true },
  target: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'corrected'])
const { t } = useI18n()
const { localized } = useCriteria()
const auth = useAuthStore()

const type = computed(() => props.target?.type ?? VERSION_TARGET)
const hasDescription = computed(() => [NODE.MAIN, NODE.SUB].includes(type.value))
const primaryMax = computed(() =>
  type.value === NODE.POINT ? CRITERIA_DESCRIPTION_MAX_LENGTH : undefined,
)
const attachments = ref([])

const schema = computed(() =>
  yupObject({
    primaryAr: requiredText(t, primaryMax.value),
    primaryEn: requiredText(t, primaryMax.value),
    descriptionAr: pairedText(t, 'descriptionEn', CRITERIA_DESCRIPTION_MAX_LENGTH),
    descriptionEn: pairedText(t, 'descriptionAr', CRITERIA_DESCRIPTION_MAX_LENGTH),
    noteAr: pairedText(t, 'noteEn'),
    noteEn: pairedText(t, 'noteAr'),
    reason: yupString()
      .trim()
      .test(
        'reason',
        t('criteria.validation.reasonMin', { min: CRITERIA_REASON_MIN_LENGTH }),
        (value) => !value || value.length >= CRITERIA_REASON_MIN_LENGTH,
      )
      .test(
        'required',
        t('criteria.publish.reasonRequired'),
        (value) => !auth.isSuperAdmin || Boolean(value),
      ),
  }),
)

const form = useValidatedForm({
  schema,
  initialValues: {
    ...correctionValues(props.target ?? { type: VERSION_TARGET, node: props.version }),
    reason: '',
  },
  action: 'criteria.textCorrection.submit',
  submit: async (values) => {
    const target = props.target ?? { type: VERSION_TARGET, node: props.version }
    const payload = textCorrectionPayload(target, values, attachments.value, values.reason)
    try {
      const outcome = await criteriaService.correctTexts(props.version.id, payload)
      emit('corrected', outcome)
      emit('update:modelValue', false)
    } catch (error) {
      throw renameFieldErrors(error, correctionFieldAliases(target))
    }
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    const target = props.target ?? { type: VERSION_TARGET, node: props.version }
    form.resetForm({ values: { ...correctionValues(target), reason: '' } })
    attachments.value = correctionAttachments(target)
  },
)

const title = computed(() => {
  if (type.value === VERSION_TARGET) return t('criteria.correction.versionTitle')
  const node = props.target?.node
  return t('criteria.correction.title', { name: node?.code ?? localized(node?.name) })
})

const primaryLabels = computed(() => {
  if (type.value === VERSION_TARGET) return ['criteria.fields.labelAr', 'criteria.fields.labelEn']
  if (type.value === NODE.POINT)
    return ['criteria.fields.descriptionAr', 'criteria.fields.descriptionEn']
  return ['criteria.fields.nameAr', 'criteria.fields.nameEn']
})

const [primaryAr] = form.defineField('primaryAr')
const [primaryEn] = form.defineField('primaryEn')
const [descriptionAr] = form.defineField('descriptionAr')
const [descriptionEn] = form.defineField('descriptionEn')
const [noteAr] = form.defineField('noteAr')
const [noteEn] = form.defineField('noteEn')
const [reason] = form.defineField('reason')
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :busy="form.submitting.value"
    :title="title"
    :submit-label="t('criteria.correction.submit')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="criteria-form">
      <p class="criteria-form__note criteria-form__note--emphasis" role="note">
        <AppIcon name="pen-line" />
        <span>{{ t('criteria.correction.note', { number: version.versionNumber }) }}</span>
      </p>
      <div class="criteria-form__grid">
        <BaseInput
          v-model="primaryAr"
          dir="rtl"
          name="correctionPrimaryAr"
          :label="t(primaryLabels[0])"
          :error="form.errors.value.primaryAr"
          required
        />
        <BaseInput
          v-model="primaryEn"
          dir="ltr"
          name="correctionPrimaryEn"
          :label="t(primaryLabels[1])"
          :error="form.errors.value.primaryEn"
          required
        />
      </div>
      <template v-if="hasDescription">
        <BaseTextarea
          v-model="descriptionAr"
          dir="rtl"
          name="correctionDescriptionAr"
          :rows="2"
          :label="t('criteria.fields.descriptionAr')"
          :error="form.errors.value.descriptionAr"
        />
        <BaseTextarea
          v-model="descriptionEn"
          dir="ltr"
          name="correctionDescriptionEn"
          :rows="2"
          :label="t('criteria.fields.descriptionEn')"
          :error="form.errors.value.descriptionEn"
        />
      </template>
      <div v-if="type === NODE.SUB" class="criteria-form__grid">
        <BaseInput
          v-model="noteAr"
          dir="rtl"
          name="correctionNoteAr"
          :label="t('criteria.fields.assessorNoteAr')"
          :error="form.errors.value.noteAr"
        />
        <BaseInput
          v-model="noteEn"
          dir="ltr"
          name="correctionNoteEn"
          :label="t('criteria.fields.assessorNoteEn')"
          :error="form.errors.value.noteEn"
        />
      </div>
      <fieldset v-if="attachments.length" class="criteria-form__group">
        <legend class="criteria-form__legend">
          {{ t('criteria.correction.attachmentsTitle') }}
        </legend>
        <div v-for="(item, index) in attachments" :key="item.id" class="criteria-form__grid">
          <BaseInput
            v-model="item.ar"
            dir="rtl"
            :name="`correctionAttachmentAr${index}`"
            :label="t('criteria.attachments.nameAr')"
            required
          />
          <BaseInput
            v-model="item.en"
            dir="ltr"
            :name="`correctionAttachmentEn${index}`"
            :label="t('criteria.attachments.nameEn')"
            required
          />
        </div>
      </fieldset>
      <BaseTextarea
        v-model="reason"
        name="correctionReason"
        :rows="2"
        :label="t('criteria.publish.reason')"
        :hint="
          auth.isSuperAdmin
            ? t('criteria.publish.reasonHintRequired')
            : t('criteria.publish.reasonHint')
        "
        :error="form.errors.value.reason"
        :required="auth.isSuperAdmin"
      />
    </div>
  </FormModal>
</template>
