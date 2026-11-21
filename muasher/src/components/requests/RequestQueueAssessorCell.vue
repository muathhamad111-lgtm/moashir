<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'

// The request's assessor in every state, marked when the request moved from one assessor to another.
defineProps({
  item: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <span v-if="item.assessor" class="request-queue__assessor">
    <span>{{ item.assessor.name }}</span>
    <Badge v-if="item.isReassigned" variant="info">{{ t('requests.reassigned') }}</Badge>
  </span>
  <span v-else class="request-queue__muted">{{
    item.progress ? t('requests.noAssessorYet') : t('requests.unassigned')
  }}</span>
</template>
