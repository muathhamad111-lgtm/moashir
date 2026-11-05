<script setup>
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DateStamp from './DateStamp.vue'

defineProps({
  assessment: { type: Object, required: true },
  criteria: { type: Object, default: null },
})

const { t } = useI18n()
</script>

<template>
  <dl class="accredited-summary">
    <div class="accredited-summary__item">
      <dt>{{ t('accredited.form.number') }}</dt>
      <dd>
        <bdi dir="ltr">{{ assessment.number }}</bdi>
      </dd>
    </div>
    <div class="accredited-summary__item">
      <dt>{{ t('accredited.form.status') }}</dt>
      <dd class="accredited-summary__badges">
        <StatusBadge :status="assessment.status" />
        <Badge v-if="assessment.isFrozen" variant="danger">
          <i class="fa-solid fa-snowflake" aria-hidden="true"></i>
          {{ t('accredited.form.frozen') }}
        </Badge>
      </dd>
    </div>
    <div v-if="criteria" class="accredited-summary__item">
      <dt>{{ t('accredited.form.version') }}</dt>
      <dd>{{ t('accredited.form.versionValue', { number: criteria.versionNumber }) }}</dd>
    </div>
    <div class="accredited-summary__item">
      <dt>{{ t('accredited.form.openedAt') }}</dt>
      <dd><DateStamp :value="assessment.createdAt" :with-time="false" /></dd>
    </div>
    <div v-if="assessment.submittedAt" class="accredited-summary__item">
      <dt>{{ t('accredited.form.submittedAt') }}</dt>
      <dd><DateStamp :value="assessment.submittedAt" /></dd>
    </div>
  </dl>
</template>
