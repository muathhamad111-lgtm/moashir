<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { toRequestStatus } from '@/services/mappers/accreditedStatus'

defineProps({
  requests: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <AssessmentNotice
    variant="danger"
    live
    :title="t('cycles.blocked.title')"
    :message="t('cycles.blocked.message', { count: requests.length }, requests.length)"
  >
    <ul class="cycle-blocked">
      <li v-for="request in requests" :key="request.id" class="cycle-blocked__item">
        <RouterLink
          class="cycle-blocked__link"
          :to="{ name: ROUTE_NAMES.REQUESTS_DETAIL, params: { id: request.id } }"
        >
          <bdi dir="ltr">{{ request.number }}</bdi>
        </RouterLink>
        <StatusBadge :status="toRequestStatus(request.status)" />
      </li>
    </ul>
  </AssessmentNotice>
</template>
