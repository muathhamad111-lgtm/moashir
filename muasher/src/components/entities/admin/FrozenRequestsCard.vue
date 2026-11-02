<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import OpenRequestsList from './OpenRequestsList.vue'

defineProps({
  requests: { type: Array, required: true },
  status: { type: String, required: true },
})

const emit = defineEmits(['retry'])
const { t } = useI18n()
</script>

<template>
  <Card :title="t('entities.record.frozenRequestsTitle')">
    <Skeleton v-if="status === 'loading'" variant="text" :count="3" />
    <ErrorState v-else-if="status === 'error'" @retry="emit('retry')" />
    <template v-else>
      <p class="entity-record__hint">{{ t('entities.frozen.hint') }}</p>
      <OpenRequestsList v-if="requests.length" :requests="requests" />
      <p v-else class="entity-record__empty">{{ t('entities.record.noFrozenRequests') }}</p>
    </template>
  </Card>
</template>
