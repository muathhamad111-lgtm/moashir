<script setup>
import { computed } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FieldToggle from '@/components/form/FieldToggle.vue'
import FieldCheckboxGroup from '@/components/form/FieldCheckboxGroup.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ROLE_KEYS } from '@/constants/roles'

const props = defineProps({
  fileAccessRoles: { type: Array, default: () => [] },
})

const { t, locale } = useI18n()
const { value: enabled } = useField('nationalIdEnabled')
const { value: selected } = useField('nationalIdRoles')

function roleLabel(role) {
  const label = t(`users.roleLabels.${role}`)
  return props.fileAccessRoles.includes(role)
    ? t('settings.general.nationalId.withFileAccess', { role: label })
    : label
}

const roleOptions = computed(() =>
  ROLE_KEYS.map((role) => ({ value: role, label: roleLabel(role) })),
)

function listOf(items) {
  return new Intl.ListFormat(locale.value, { type: 'conjunction' }).format(items)
}

const uncoveredRoles = computed(() =>
  props.fileAccessRoles
    .filter((role) => !(selected.value ?? []).includes(role))
    .map((role) => t(`users.roleLabels.${role}`)),
)
</script>

<template>
  <div class="national-id-policy">
    <FieldToggle
      name="nationalIdEnabled"
      :label="t('settings.general.nationalId.enable')"
      :description="t('settings.general.nationalId.description')"
    />
    <FieldCheckboxGroup
      v-show="enabled"
      name="nationalIdRoles"
      :label="t('settings.general.nationalId.roles')"
      :options="roleOptions"
    />
    <p
      v-if="enabled && uncoveredRoles.length"
      class="national-id-policy__warning"
      role="status"
      data-testid="national-id-uncovered"
    >
      <AppIcon name="warning-triangle" />
      {{ t('settings.general.nationalId.uncovered', { roles: listOf(uncoveredRoles) }) }}
    </p>
  </div>
</template>
