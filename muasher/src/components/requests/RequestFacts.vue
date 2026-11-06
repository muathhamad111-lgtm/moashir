<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  detail: { type: Object, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <dl class="request-facts">
    <div class="request-facts__item">
      <dt>{{ t('requests.columns.entity') }}</dt>
      <dd>
        {{ localizedText(detail.entity?.name, locale) }}
        <bdi dir="ltr" class="request-facts__reference">{{ detail.entity?.reference }}</bdi>
      </dd>
    </div>
    <div v-if="detail.path" class="request-facts__item">
      <dt>{{ t('requests.columns.path') }}</dt>
      <dd><PathBadge :path="detail.path" :label="detail.pathLabel" /></dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('requests.columns.status') }}</dt>
      <dd class="request-facts__badges">
        <StatusBadge :status="detail.status" />
        <Badge v-if="detail.isFrozen" variant="danger"
          ><AppIcon name="snowflake" /> {{ t('accredited.form.frozen') }}</Badge
        >
      </dd>
    </div>
    <div v-if="detail.awardCycle" class="request-facts__item">
      <dt>{{ t('requests.columns.cycle') }}</dt>
      <dd>{{ localizedText(detail.awardCycle.name, locale) }}</dd>
    </div>
    <div v-if="detail.region" class="request-facts__item">
      <dt>{{ t('requests.columns.region') }}</dt>
      <dd>{{ localizedText(detail.region.name, locale) }}</dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('requests.columns.assessor') }}</dt>
      <dd>{{ detail.assessor?.name ?? t('requests.unassigned') }}</dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('requests.columns.submittedAt') }}</dt>
      <dd><DateStamp :value="detail.submittedAt" /></dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('requests.columns.dueAt') }}</dt>
      <dd class="request-facts__badges">
        <DateStamp :value="detail.dueAt" with-hijri />
        <OverdueBadge v-if="detail.dueAt && !detail.completedAt" :overdue="detail.isOverdue" />
      </dd>
    </div>
    <div v-if="detail.completionDeadline" class="request-facts__item">
      <dt>{{ t('accredited.completion.deadline') }}</dt>
      <dd><DateStamp :value="detail.completionDeadline" with-hijri /></dd>
    </div>
    <div v-if="detail.completedAt" class="request-facts__item">
      <dt>{{ t('requests.detail.completedAt') }}</dt>
      <dd><DateStamp :value="detail.completedAt" /></dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('requests.detail.payment') }}</dt>
      <dd>
        <Badge :variant="detail.payment.paid ? 'success' : 'warning'">{{
          detail.payment.paid ? t('requests.detail.paid') : t('requests.detail.notPaid')
        }}</Badge>
      </dd>
    </div>
  </dl>
</template>
