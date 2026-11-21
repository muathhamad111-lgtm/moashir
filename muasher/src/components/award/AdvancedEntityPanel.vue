<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import AdvancedEligibilityList from './AdvancedEligibilityList.vue'
import AdvancedRequestCard from './AdvancedRequestCard.vue'

const props = defineProps({
  status: { type: String, required: true },
  current: { type: Object, default: null },
  errorMessage: { type: String, default: '' },
  applying: { type: Boolean, default: false },
  deleting: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'delete', 'retry'])
const { t } = useI18n()

const showEligibility = computed(() => Boolean(props.current?.cycle) && !props.current?.request)
</script>

<template>
  <Card class="advanced-entity" :title="t('advanced.entity.title')">
    <div v-if="status === 'loading'" class="advanced-entity__loading" aria-busy="true">
      <Skeleton variant="text" :count="4" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="emit('retry')" />
    <div v-else-if="current" class="advanced-entity__body">
      <template v-if="current.cycle">
        <div v-if="showEligibility" class="advanced-entity__eligibility">
          <h3 class="advanced-entity__subtitle">{{ t('advanced.eligibility.title') }}</h3>
          <AdvancedEligibilityList
            :conditions="current.eligibility.conditions"
            :window-open="current.eligibility.windowOpen"
          />
        </div>
        <AdvancedRequestCard
          :request="current.request"
          :eligibility="current.eligibility"
          :applying="applying"
          :deleting="deleting"
          @apply="emit('apply')"
          @delete="emit('delete', current.request)"
        />
      </template>
      <p v-else class="advanced-entity__empty">{{ t('advanced.entity.noCycle') }}</p>
      <div v-if="current.previous" class="advanced-entity__previous">
        <h3 class="advanced-entity__subtitle">{{ t('advanced.entity.previous') }}</h3>
        <p class="advanced-entity__previous-row">
          <bdi dir="ltr">{{ current.previous.number }}</bdi>
          <StatusBadge :status="current.previous.status" />
        </p>
      </div>
    </div>
  </Card>
</template>
