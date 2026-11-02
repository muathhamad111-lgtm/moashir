<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { object as yupObject, string as yupString } from 'yup'
import FormModal from '@/components/modal/FormModal.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { usePermissionsCatalog } from '@/composables/usePermissionsCatalog'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  permission: { type: Object, default: null },
  editableRoles: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const { roleLabel, groupLabel } = usePermissionsCatalog()

const selectedRoles = ref([])

const isLocked = computed(() => (props.permission?.lockedToRoles ?? []).length > 0)
const roleItems = computed(() => props.editableRoles.map((role) => ({ value: role, label: roleLabel(role) })))

const schema = computed(() =>
  yupObject({
    nameAr: yupString().required(t('validation.required')).min(2, t('validation.minChars', { min: 2 })),
    nameEn: yupString().required(t('validation.required')).min(2, t('validation.minChars', { min: 2 })),
    descriptionAr: yupString().default(''),
    descriptionEn: yupString().default(''),
  }),
)

function fromPermission(permission) {
  return {
    nameAr: permission?.label?.ar ?? '',
    nameEn: permission?.label?.en ?? '',
    descriptionAr: permission?.description?.ar ?? '',
    descriptionEn: permission?.description?.en ?? '',
  }
}

function syncRoles(permission) {
  selectedRoles.value = [...(permission?.roles ?? [])]
}

syncRoles(props.permission)

const form = useValidatedForm({
  schema,
  initialValues: fromPermission(props.permission),
  action: 'permissions.form.submit',
  submit: async (values) => {
    emit('saved', {
      label: { ar: values.nameAr.trim(), en: values.nameEn.trim() },
      description: { ar: (values.descriptionAr ?? '').trim(), en: (values.descriptionEn ?? '').trim() },
      roles: [...selectedRoles.value],
    })
  },
})

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    form.resetForm({ values: fromPermission(props.permission) })
    syncRoles(props.permission)
  },
)

const [nameAr] = form.defineField('nameAr')
const [nameEn] = form.defineField('nameEn')
const [descriptionAr] = form.defineField('descriptionAr')
const [descriptionEn] = form.defineField('descriptionEn')

function toggleRole(value, checked) {
  const next = new Set(selectedRoles.value)
  if (checked) next.add(value)
  else next.delete(value)
  selectedRoles.value = [...next]
}
</script>

<template>
  <FormModal
    :model-value="modelValue"
    size="lg"
    :busy="form.submitting.value"
    :title="t('permissions.form.editTitle')"
    :submit-label="t('actions.save')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="form.onSubmit"
  >
    <div class="permission-form">
      <fieldset class="permission-form__section">
        <legend class="permission-form__legend">{{ t('permissions.form.identityGroup') }}</legend>
        <dl class="permission-form__facts">
          <div class="permission-form__fact">
            <dt>{{ t('permissions.fields.key') }}</dt>
            <dd><code class="permissions-page__key">{{ permission?.key }}</code></dd>
          </div>
          <div class="permission-form__fact">
            <dt>{{ t('permissions.fields.group') }}</dt>
            <dd>{{ groupLabel(permission?.module) }}</dd>
          </div>
        </dl>
        <p class="permission-form__hint">{{ t('permissions.fields.keySystemHint') }}</p>
      </fieldset>

      <fieldset class="permission-form__section">
        <legend class="permission-form__legend">{{ t('permissions.form.nameGroup') }}</legend>
        <div class="permission-form__row">
          <BaseInput
            v-model="nameAr"
            name="nameAr"
            :label="t('permissions.fields.nameAr')"
            :error="form.errors.value.nameAr"
            required
          />
          <BaseInput
            v-model="nameEn"
            name="nameEn"
            :label="t('permissions.fields.nameEn')"
            :error="form.errors.value.nameEn"
            required
          />
        </div>
        <div class="permission-form__row">
          <BaseTextarea
            v-model="descriptionAr"
            name="descriptionAr"
            :rows="2"
            :label="t('permissions.fields.descriptionAr')"
            :error="form.errors.value.descriptionAr"
          />
          <BaseTextarea
            v-model="descriptionEn"
            name="descriptionEn"
            :rows="2"
            :label="t('permissions.fields.descriptionEn')"
            :error="form.errors.value.descriptionEn"
          />
        </div>
      </fieldset>

      <fieldset class="permission-form__section">
        <legend class="permission-form__legend">{{ t('permissions.fields.roles') }}</legend>
        <p class="permission-form__hint">{{ t('permissions.fields.rolesHint') }}</p>
        <p v-if="isLocked" class="permission-form__locked">
          <i class="fa-solid fa-lock" aria-hidden="true"></i>
          {{ t('permissions.lockedHint') }}
        </p>
        <div class="permission-form__matrix" role="group" :aria-label="t('permissions.fields.roles')">
          <BaseCheckbox
            v-for="item in roleItems"
            :key="item.value"
            :model-value="selectedRoles.includes(item.value)"
            :name="`role-${item.value}`"
            :label="item.label"
            :disabled="isLocked"
            @update:model-value="toggleRole(item.value, $event)"
          />
        </div>
      </fieldset>
    </div>
  </FormModal>
</template>
