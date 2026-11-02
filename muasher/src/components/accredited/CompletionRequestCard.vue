<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from './DateStamp.vue'
import { COMPLETION_STATUSES } from '@/constants/accredited'

defineProps({
  request: { type: Object, required: true },
  showStatus: { type: Boolean, default: false },
  titleKey: { type: String, default: 'accredited.completion.title' },
})

const { t } = useI18n()

const STATUS_VARIANTS = {
  [COMPLETION_STATUSES.OPEN]: 'warning',
  [COMPLETION_STATUSES.RESUBMITTED]: 'success',
  [COMPLETION_STATUSES.CANCELLED]: 'muted',
  [COMPLETION_STATUSES.EXPIRED_CONTINUED]: 'info',
  [COMPLETION_STATUSES.EXPIRED_DECLINED]: 'danger',
}
</script>

<template>
  <article class="completion-card">
    <header class="completion-card__head">
      <h3 class="completion-card__title">{{ t(titleKey) }}</h3>
      <Badge v-if="showStatus" :variant="STATUS_VARIANTS[request.status] ?? 'muted'">{{ t(`accredited.completion.statuses.${request.status}`) }}</Badge>
    </header>
    <p class="completion-card__notes">{{ request.notes }}</p>
    <dl class="completion-card__facts">
      <div class="completion-card__fact">
        <dt>{{ t('accredited.completion.deadline') }}</dt>
        <dd><DateStamp :value="request.deadlineAt" with-hijri /></dd>
      </div>
      <div class="completion-card__fact">
        <dt>{{ t('accredited.completion.expiryOutcome') }}</dt>
        <dd>{{ t(`accredited.completion.outcomes.${request.expiryOutcome}`) }}</dd>
      </div>
      <div class="completion-card__fact">
        <dt>{{ t('accredited.completion.requestedAt') }}</dt>
        <dd><DateStamp :value="request.requestedAt" /></dd>
      </div>
      <div v-if="request.resolvedAt" class="completion-card__fact">
        <dt>{{ t('accredited.completion.resolvedAt') }}</dt>
        <dd><DateStamp :value="request.resolvedAt" /></dd>
      </div>
    </dl>
    <p class="completion-card__items-title">{{ t('accredited.completion.items') }}</p>
    <ul class="completion-card__items">
      <li v-for="item in request.items" :key="`${item.code}-${item.kind}`" class="completion-card__item">
        <span class="completion-card__code u-ltr">{{ item.code }}</span>
        <Badge variant="info">{{ t(`accredited.completion.kinds.${item.kind}`) }}</Badge>
        <span v-if="item.note" class="completion-card__note">{{ item.note }}</span>
      </li>
    </ul>
  </article>
</template>
