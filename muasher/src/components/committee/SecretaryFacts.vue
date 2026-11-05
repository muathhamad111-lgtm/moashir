<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import { localizedText } from '@/utils/localizedText'

defineProps({
  detail: { type: Object, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <dl class="request-facts secretary-facts">
    <div class="request-facts__item">
      <dt>{{ t('award.secretary.columns.entity') }}</dt>
      <dd>{{ localizedText(detail.entity?.name, locale) }}</dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('award.secretary.columns.region') }}</dt>
      <dd>{{ localizedText(detail.region?.name, locale) }}</dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('award.secretary.columns.status') }}</dt>
      <dd><StatusBadge :status="detail.status" /></dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('award.secretary.initialResult') }}</dt>
      <dd>
        <bdi v-if="detail.initialResult" class="u-ltr">{{ detail.initialResult.percent }}%</bdi>
      </dd>
    </div>
    <div class="request-facts__item secretary-facts__average">
      <dt>{{ t('award.secretary.columns.average') }}</dt>
      <dd v-if="detail.average">
        <bdi class="u-ltr secretary-facts__percent">{{ detail.average.percent }}%</bdi>
        <span class="committee-member__note">{{
          t('award.secretary.averageMeta', {
            version: detail.average.version,
            count: detail.average.memberCount,
          })
        }}</span>
        <DateStamp :value="detail.average.computedAt" />
        <Badge v-if="detail.average.isManual" variant="muted">{{
          t('award.secretary.manual')
        }}</Badge>
      </dd>
      <dd v-else>
        {{
          detail.allSubmitted
            ? t('award.secretary.noAverage')
            : t('award.secretary.waitingCommittee')
        }}
      </dd>
    </div>
  </dl>
</template>
