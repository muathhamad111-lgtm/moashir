<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldCheckboxGroup from '@/components/form/FieldCheckboxGroup.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useAuthStore } from '@/stores/auth'
import { usersService } from '@/services/usersService'
import { ASSESSOR_QUALIFICATIONS } from '@/constants/paths'
import {
  qualificationsPayload,
  qualificationsSchema,
  qualificationsValuesOf,
} from '@/utils/users/pathAccessForms'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  user: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const auth = useAuthStore()

const options = computed(() =>
  Object.values(ASSESSOR_QUALIFICATIONS).map((value) => ({
    value,
    label: t(`users.pathAccess.qualifications.${value}`),
  })),
)

const form = useValidatedForm({
  schema: computed(() => qualificationsSchema(t, auth.isSuperAdmin)),
  initialValues: qualificationsValuesOf(props.user),
  action: 'users.assessorQualifications.submit',
  submit: async (values) => {
    const { data } = await usersService.updateAssessorQualifications(
      props.user.id,
      qualificationsPayload(values),
    )
    emit('update:modelValue', false)
    emit('saved', data)
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: qualificationsValuesOf(props.user) })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t('users.pathAccess.qualificationsTitle')"
    :subtitle="user?.name ?? ''"
    :busy="form.submitting.value"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="path-access-form">
      <FieldCheckboxGroup
        name="qualifications"
        :label="t('users.pathAccess.qualificationsLabel')"
        :options="options"
        :hint="t('users.pathAccess.qualificationsHint')"
      />
      <FieldTextarea
        name="reason"
        :label="t('entities.reason.label')"
        :hint="
          auth.isSuperAdmin
            ? t('users.pathAccess.reasonRequiredHint')
            : t('entities.reason.optionalHint')
        "
        :required="auth.isSuperAdmin"
      />
    </div>
  </FormModal>
</template>
