<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ndaRoleWarningApplies } from '@/utils/nda/ndaForms'

const props = defineProps({
  scopeRoles: { type: Array, default: () => [] },
  initialRoles: { type: Array, default: () => [] },
  selectedRoles: { type: Array, default: () => [] },
  status: { type: String, default: null },
  name: { type: String, default: '' },
})

const { t } = useI18n()

const shown = computed(() =>
  ndaRoleWarningApplies({
    scopeRoles: props.scopeRoles,
    addedRoles: props.selectedRoles.filter((role) => !props.initialRoles.includes(role)),
    status: props.status,
  }),
)
</script>

<template>
  <p v-if="shown" class="nda-role-warning" role="note" data-testid="nda-role-warning">
    <AppIcon name="lock" />
    <span>{{ name ? t('nda.roleWarning.named', { name }) : t('nda.roleWarning.invite') }}</span>
  </p>
</template>
