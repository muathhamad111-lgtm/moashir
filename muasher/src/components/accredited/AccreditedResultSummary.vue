<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import DateStamp from './DateStamp.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  result: { type: Object, required: true },
})

const LONG_VALUE_LENGTH = 5

const { t } = useI18n()
const isLongValue = computed(() => String(props.result.percent ?? '').length >= LONG_VALUE_LENGTH)
</script>

<template>
  <Card class="accredited-result-summary">
    <div class="accredited-result-summary__score">
      <div
        class="accredited-result-summary__gauge"
        role="img"
        :aria-label="t('accredited.result.percentAria', { percent: result.percent })"
      >
        <span
          class="accredited-result-summary__value u-ltr"
          :class="{ 'accredited-result-summary__value--long': isLongValue }"
          >{{ result.percent }}%</span
        >
      </div>
      <div class="accredited-result-summary__detail">
        <p class="accredited-result-summary__total">
          {{ t('accredited.result.totalOf', { total: result.total, max: result.maxTotal }) }}
        </p>
        <p class="accredited-result-summary__meta">
          {{ t('accredited.result.completedAt') }}
          <DateStamp :value="result.completedAt" with-hijri />
        </p>
      </div>
    </div>
    <p class="accredited-result-summary__confidential" role="note">
      <AppIcon name="user-shield" />
      {{ t('accredited.result.confidential') }}
    </p>
  </Card>
</template>
