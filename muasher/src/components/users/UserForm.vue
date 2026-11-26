<script setup>
import { USER_STATUSES } from '@/constants/users'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import FormModal from '@/components/modal/FormModal.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useUsers } from '@/composables/useUsers'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseRadio from '@/components/form/BaseRadio.vue'
import NdaRoleWarning from '@/components/nda/NdaRoleWarning.vue'
import { GENDER_VALUES } from '@/constants/identityChanges'
import { NATIONAL_ID_LENGTH, nationalIdInput } from '@/utils/identity/nationalId'
import {
  acceptsNationalId,
  userFormPayload,
  userFormSchema,
  userFormValues,
} from '@/utils/users/userFormSchema'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  user: { type: Object, default: null },
  save: { type: Function, required: true },
  ndaScopeRoles: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const { roleOptions, roleCapabilities } = useUsers()

const isEdit = computed(() => Boolean(props.user))
const nameLocked = computed(() => isEdit.value && props.user.status !== USER_STATUSES.INVITED)

const schema = computed(() => userFormSchema(t, { user: props.user }))
const takesNationalId = computed(() => acceptsNationalId(props.user))

const form = useValidatedForm({
  schema,
  initialValues: userFormValues(props.user),
  action: 'users.form.submit',
  submit: async (values) => {
    const id = props.user?.id ?? null
    await props.save({ id, payload: userFormPayload(values) })
    emit('update:modelValue', false)
    emit('saved', { id })
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: userFormValues(props.user) })
  },
)

const [name] = form.defineField('name')
const [email] = form.defineField('email')
const [roles] = form.defineField('roles')
const [gender] = form.defineField('gender')
const [nationalId] = form.defineField('nationalId')
const genderOptions = computed(() =>
  GENDER_VALUES.map((value) => ({ value, label: t(`nationalIdentity.gender.${value}`) })),
)

function isRoleSelected(code) {
  return roles.value.includes(code)
}

function toggleRole(code) {
  const next = new Set(roles.value)
  if (next.has(code)) next.delete(code)
  else next.add(code)
  roles.value = [...next]
}

const capabilities = computed(() => {
  const all = []
  for (const code of roles.value) {
    for (const capability of roleCapabilities(code)) {
      if (!all.includes(capability)) all.push(capability)
    }
  }
  return all
})
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="md"
    :busy="form.submitting.value"
    :title="isEdit ? t('users.form.editTitle') : t('users.form.createTitle')"
    :submit-label="isEdit ? t('actions.save') : t('users.form.create')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="user-form">
      <BaseInput
        v-model="name"
        name="name"
        :label="t('users.fields.name')"
        :placeholder="t('users.fields.namePlaceholder')"
        :error="form.errors.value.name"
        :readonly="nameLocked"
        :hint="nameLocked ? t('nationalIdentity.correction.lockedHint') : ''"
        required
      />
      <BaseInput
        v-model="email"
        name="email"
        type="email"
        :label="t('users.fields.email')"
        :placeholder="t('users.fields.emailPlaceholder')"
        :error="form.errors.value.email"
        required
      />
      <BaseRadio
        v-model="gender"
        name="gender"
        :label="t('nationalIdentity.gender.label')"
        :options="genderOptions"
        :error="form.errors.value.gender"
        inline
        required
      />
      <BaseInput
        v-if="takesNationalId"
        v-model="nationalId"
        name="nationalId"
        :label="t('nationalIdentity.field.label')"
        :hint="t('nationalIdentity.field.hint')"
        :error="form.errors.value.nationalId"
        :maxlength="NATIONAL_ID_LENGTH"
        :filter="nationalIdInput"
        inputmode="numeric"
        autocomplete="off"
        input-dir="ltr"
      />
      <p v-else class="user-form__locked-id">
        {{ t('nationalIdentity.field.label') }}:
        <bdi dir="ltr" class="u-ltr">{{ user.nationalIdMasked }}</bdi>
        <span class="user-form__locked-note">{{ t('users.form.nationalIdLocked') }}</span>
      </p>
      <div class="user-form__field">
        <span class="user-form__label">{{ t('users.fields.role') }}</span>
        <div class="user-form__roles" role="group" :aria-label="t('users.fields.role')">
          <BaseCheckbox
            v-for="option in roleOptions"
            :key="option.value"
            :model-value="isRoleSelected(option.value)"
            :name="`role-${option.value}`"
            :label="option.label"
            @update:model-value="toggleRole(option.value)"
          />
        </div>
        <p v-if="form.errors.value.roles" class="user-form__error" role="alert">
          {{ form.errors.value.roles }}
        </p>
        <NdaRoleWarning
          :scope-roles="ndaScopeRoles"
          :initial-roles="user?.roles ?? []"
          :selected-roles="roles"
          :status="user?.ndaStatus ?? null"
          :name="user?.name ?? ''"
        />
      </div>

      <div v-if="capabilities.length" class="user-form__hint" role="note">
        <p class="user-form__hint-title">
          <AppIcon name="shield-info" />
          {{ t('users.form.capabilitiesTitle') }}
        </p>
        <ul class="user-form__hint-list">
          <li v-for="capability in capabilities" :key="capability" class="user-form__hint-item">
            <AppIcon name="check" />
            {{ capability }}
          </li>
        </ul>
      </div>
    </div>
  </FormModal>
</template>
