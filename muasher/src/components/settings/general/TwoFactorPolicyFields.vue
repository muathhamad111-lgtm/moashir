<script setup>
import { computed } from 'vue'
import { useField } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import FieldToggle from '@/components/form/FieldToggle.vue'
import FieldCheckboxGroup from '@/components/form/FieldCheckboxGroup.vue'
import { ROLE_KEYS } from '@/constants/roles'

const { t } = useI18n()
const { value: enabled } = useField('twoFactorEnabled')

const roleOptions = computed(() =>
  ROLE_KEYS.map((role) => ({ value: role, label: t(`users.roleLabels.${role}`) })),
)
</script>

<template>
  <div class="two-factor-policy">
    <FieldToggle
      name="twoFactorEnabled"
      :label="t('settings.general.twoFactor.enable')"
      :description="t('settings.general.twoFactor.superAdmin')"
    />
    <FieldCheckboxGroup
      v-show="enabled"
      name="twoFactorRoles"
      :label="t('settings.general.twoFactor.roles')"
      :options="roleOptions"
    />
  </div>
</template>
