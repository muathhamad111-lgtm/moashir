<script setup>
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'

defineProps({
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t } = useI18n()
</script>

<template>
  <div v-if="status === 'loading'" class="general-settings__loading" aria-busy="true">
    <Skeleton variant="block" />
    <Skeleton variant="text" :count="3" />
  </div>
  <EmptyState
    v-else-if="status === 'forbidden'"
    icon="lock"
    :title="t('integrations.forbidden.title')"
    :message="t('integrations.forbidden.message')"
  />
  <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="emit('retry')" />
  <slot v-else />
</template>
