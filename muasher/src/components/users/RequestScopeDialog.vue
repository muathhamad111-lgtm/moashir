<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import FieldRadio from '@/components/form/FieldRadio.vue'
import FieldCheckboxGroup from '@/components/form/FieldCheckboxGroup.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useAuthStore } from '@/stores/auth'
import { usersService } from '@/services/usersService'
import { REVIEWED_PATHS } from '@/constants/paths'
import {
  SCOPE_CHOICES,
  requestScopePayload,
  requestScopeSchema,
  requestScopeValuesOf,
} from '@/utils/users/pathAccessForms'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  user: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const auth = useAuthStore()

const scopeOptions = computed(() =>
  Object.values(SCOPE_CHOICES).map((value) => ({
    value,
    label: t(`users.pathAccess.scopeChoices.${value}`),
  })),
)
const pathOptions = computed(() =>
  REVIEWED_PATHS.map((value) => ({ value, label: t(`paths.labels.${value}`) })),
)

const form = useValidatedForm({
  schema: computed(() => requestScopeSchema(t, auth.isSuperAdmin)),
  initialValues: requestScopeValuesOf(props.user),
  action: 'users.requestScope.submit',
  submit: async (values) => {
    const { data } = await usersService.updateRequestScope(
      props.user.id,
      requestScopePayload(values),
    )
    emit('update:modelValue', false)
    emit('saved', data)
  },
})

const limited = computed(() => form.values.scope === SCOPE_CHOICES.SELECTED)

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: requestScopeValuesOf(props.user) })
  },
)
</script>

<template>
  <FormModal
    :model-value="modelValue"
    :title="t('users.pathAccess.scopeTitle')"
    :subtitle="user?.name ?? ''"
    :busy="form.submitting.value"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="path-access-form">
      <FieldRadio name="scope" :label="t('users.pathAccess.scopeLabel')" :options="scopeOptions" />
      <FieldCheckboxGroup
        v-if="limited"
        name="paths"
        :label="t('users.pathAccess.pathsLabel')"
        :options="pathOptions"
        :hint="t('users.pathAccess.pathsHint')"
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
