<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { USER_STATUSES as S } from '@/constants/users'

const props = defineProps({
  status: { type: String, required: true },
})

const VARIANTS = {
  [S.INVITED]: 'info',
  [S.ACTIVE]: 'success',
  [S.LOCKED]: 'warning',
  [S.SUSPENDED]: 'danger',
  [S.EXPIRED]: 'muted',
}

const { t } = useI18n()
const variant = computed(() => VARIANTS[props.status] ?? 'muted')
</script>

<template>
  <Badge :variant="variant">{{ t(`users.statuses.${status}`) }}</Badge>
</template>
