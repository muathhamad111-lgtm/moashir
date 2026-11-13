<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { KIT_STATUSES } from '@/constants/entityPaths'
import { ROUTE_NAMES } from '@/constants/routeNames'
import EmptyState from '@/components/feedback/EmptyState.vue'

const props = defineProps({
  record: { type: Object, required: true },
})

const { t } = useI18n()
const status = computed(() => props.record.kit?.status ?? null)
const link = computed(() => {
  if (status.value === KIT_STATUSES.ACTIVE) return { name: ROUTE_NAMES.BAG_INDEX }
  if (status.value === KIT_STATUSES.CLOSED) return { name: ROUTE_NAMES.BAG_COMPARISON }
  return null
})
</script>

<template>
  <div class="request-kit" data-testid="request-tab-kit" :data-kit-status="status">
    <EmptyState
      icon="layers"
      :title="t(`paths.kit.tab.${status ?? 'none'}.title`)"
      :message="t(`paths.kit.tab.${status ?? 'none'}.text`)"
    />
    <router-link v-if="link" class="btn btn--primary request-kit__action" :to="link">
      {{ t(`paths.kit.tab.${status}.action`) }}
    </router-link>
  </div>
</template>
