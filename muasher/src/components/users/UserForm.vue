<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, string as yupString, array as yupArray } from 'yup'
import FormModal from '@/components/modal/FormModal.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useUsers } from '@/composables/useUsers'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  user: { type: Object, default: null },
  save: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const { roleOptions, roleCapabilities } = useUsers()

const isEdit = computed(() => Boolean(props.user))

const schema = computed(() =>
  yupObject({
    name: yupString()
      .required(t('validation.required'))
      .min(3, t('validation.minChars', { min: 3 })),
    email: yupString().required(t('validation.required')).email(t('validation.email')),
    roles: yupArray().of(yupString()).min(1, t('users.validation.roleRequired')),
  }),
)

function emptyValues() {
  return { name: '', email: '', roles: [] }
}

function fromUser(user) {
  if (!user) return emptyValues()
  return {
    name: user.name ?? '',
    email: user.email ?? '',
    roles: Array.isArray(user.roles) ? [...user.roles] : [],
  }
}

const form = useValidatedForm({
  schema,
  initialValues: fromUser(props.user),
  action: 'users.form.submit',
  submit: async (values) => {
    const payload = { name: values.name.trim(), email: values.email.trim(), roles: values.roles }
    const id = props.user?.id ?? null
    await props.save({ id, payload })
    emit('update:modelValue', false)
    emit('saved', { id })
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) form.resetForm({ values: fromUser(props.user) })
  },
)

const [name] = form.defineField('name')
const [email] = form.defineField('email')
const [roles] = form.defineField('roles')

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
        <p class="user-form__hint-note">{{ t('users.form.capabilitiesNote') }}</p>
      </div>
    </div>
  </FormModal>
</template>
