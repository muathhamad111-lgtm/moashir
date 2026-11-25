<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'

// One optional detail column of the permissions table (PERMISSION_DETAIL_COLUMNS).
const props = defineProps({
  field: { type: String, required: true },
  permission: { type: Object, required: true },
  roleLabel: { type: Function, required: true },
})

const { t } = useI18n()

const lockedRoles = computed(() =>
  (props.permission.lockedToRoles ?? []).map(props.roleLabel).join(t('common.listSeparator')),
)
const flag = computed(() => props.permission[props.field])
</script>

<template>
  <template v-if="field === 'lockedToRoles'">{{ lockedRoles }}</template>
  <Badge v-else-if="typeof flag === 'boolean'" :variant="flag ? 'info' : 'muted'">{{
    t(flag ? 'common.yes' : 'common.no')
  }}</Badge>
</template>
