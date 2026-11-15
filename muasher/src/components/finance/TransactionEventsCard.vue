<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { useTransactionEvents } from '@/composables/useTransactionEvents'
import { EVENT_RESULT_VARIANTS } from '@/constants/finance'
import { formatDate } from '@/utils/datetime'

const props = defineProps({
  paymentId: { type: String, required: true },
})

const { t, te, locale } = useI18n()
const events = useTransactionEvents(props.paymentId)

function dateOf(value) {
  return formatDate(value, locale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Riyadh',
  })
}

function typeOf(value) {
  return te(`finance.events.types.${value}`) ? t(`finance.events.types.${value}`) : value
}
</script>

<template>
  <Card :title="t('finance.transaction.events.title')" class="transaction-events">
    <Skeleton v-if="events.status.value === 'loading'" variant="text" :count="3" />
    <ErrorState
      v-else-if="events.status.value === 'error'"
      :message="events.errorMessage.value"
      @retry="events.load"
    />
    <p v-else-if="events.status.value === 'empty'" class="transaction-events__empty">
      {{ t('finance.transaction.events.empty') }}
    </p>
    <template v-else>
      <ol class="transaction-events__list">
        <li v-for="event in events.items.value" :key="event.id" class="transaction-events__item">
          <span class="transaction-events__head">
            <span class="transaction-events__type">{{ typeOf(event.type) }}</span>
            <Badge v-if="event.result" :variant="EVENT_RESULT_VARIANTS[event.result] ?? 'muted'">{{
              t(`finance.events.results.${event.result}`)
            }}</Badge>
          </span>
          <span class="transaction-events__meta">
            <time :datetime="event.createdAt">{{ dateOf(event.createdAt) }}</time>
            <span>{{ t(`finance.events.sources.${event.source}`) }}</span>
            <bdi v-if="event.providerReference" dir="ltr">{{ event.providerReference }}</bdi>
          </span>
        </li>
      </ol>
      <p v-if="events.hasMore.value" class="transaction-events__empty">
        {{ t('finance.transaction.events.limited') }}
      </p>
    </template>
  </Card>
</template>
