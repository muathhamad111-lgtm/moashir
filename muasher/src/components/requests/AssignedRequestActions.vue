<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { reviewActionKey } from '@/utils/reviewAction'

const props = defineProps({
  requestId: { type: Number, required: true },
  requestNumber: { type: String, required: true },
  status: { type: String, required: true },
})

const { t } = useI18n()
const params = computed(() => ({ id: props.requestId }))
const actionKey = computed(() => reviewActionKey(props.status))
</script>

<template>
  <div class="assigned-request__actions" role="group" :aria-label="requestNumber">
    <BaseButton
      variant="secondary"
      size="sm"
      icon-only
      icon="eye"
      :to="{ name: ROUTE_NAMES.REQUESTS_DETAIL, params }"
      :aria-label="t('requests.assigned.actions.view')"
      :title="t('requests.assigned.actions.view')"
    />
    <BaseButton
      :variant="actionKey === 'viewReview' ? 'secondary' : 'primary'"
      size="sm"
      :to="{ name: ROUTE_NAMES.REQUESTS_REVIEW, params }"
      >{{ t(`requests.assigned.actions.${actionKey}`) }}</BaseButton
    >
  </div>
</template>
