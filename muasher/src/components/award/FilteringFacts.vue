<script setup>
import { useI18n } from 'vue-i18n'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import ThresholdBadge from './ThresholdBadge.vue'
import { localizedText } from '@/utils/localizedText'

defineProps({
  detail: { type: Object, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <dl class="request-facts">
    <div class="request-facts__item">
      <dt>{{ t('filtering.facts.entity') }}</dt>
      <dd>{{ localizedText(detail.row.entity?.name, locale) }}</dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('filtering.facts.region') }}</dt>
      <dd>{{ localizedText(detail.row.region?.name, locale) }}</dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('filtering.facts.status') }}</dt>
      <dd><StatusBadge :status="detail.row.status" /></dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('filtering.facts.result') }}</dt>
      <dd>
        <bdi class="u-ltr">{{
          detail.row.initialResult
            ? t('filtering.facts.resultValue', detail.row.initialResult)
            : t('accredited.common.notSet')
        }}</bdi>
      </dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('filtering.facts.threshold') }}</dt>
      <dd>
        <bdi class="u-ltr">{{
          t('filtering.percent', { value: detail.disqualificationScore })
        }}</bdi>
        <ThresholdBadge :meets="detail.row.meetsThreshold" />
      </dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('filtering.facts.assessor') }}</dt>
      <dd>{{ detail.assessor?.name ?? t('accredited.common.notSet') }}</dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('filtering.facts.assessedAt') }}</dt>
      <dd><DateStamp :value="detail.row.initialAssessedAt" /></dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('filtering.facts.submittedAt') }}</dt>
      <dd><DateStamp :value="detail.row.submittedAt" /></dd>
    </div>
  </dl>
</template>
