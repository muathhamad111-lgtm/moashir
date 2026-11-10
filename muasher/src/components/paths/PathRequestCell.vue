<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ROW_TYPES } from '@/constants/entityPaths'
import { STATUS_VARIANTS } from '@/constants/statuses'
import { statusLabelKey } from '@/utils/canonicalStatus'
import { localizedText } from '@/utils/localizedText'
import { isoDate } from '@/utils/datetime'
import { percentText } from '@/utils/percentText'
import { rowAction } from '@/utils/pathActions'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Badge from '@/components/ui/Badge.vue'

const props = defineProps({
  column: { type: String, required: true },
  row: { type: Object, required: true },
  path: { type: String, required: true },
  cycleName: { type: [Object, String], default: null },
})

const { t, n, locale } = useI18n()
const isMissed = computed(() => props.row.rowType === ROW_TYPES.MISSED_PERIOD)
const percent = computed(() => props.row.result?.percent ?? null)
const knownStatus = computed(() => Boolean(props.row.status && props.row.status in STATUS_VARIANTS))
const action = computed(() => rowAction(props.path, props.row))
const periodText = computed(() => localizedText(props.row.periodLabel, locale.value))
</script>

<template>
  <span v-if="column === 'number'" class="path-cell path-cell--number">
    <bdi v-if="row.number" dir="ltr">{{ row.number }}</bdi>
    <span v-else aria-hidden="true">—</span>
  </span>
  <span v-else-if="column === 'startedAt'">
    <bdi dir="ltr">{{ isoDate(row.createdAt) || '—' }}</bdi>
  </span>
  <span v-else-if="column === 'submittedAt'">
    <bdi dir="ltr">{{ isoDate(row.submittedAt) || '—' }}</bdi>
  </span>
  <span v-else-if="column === 'period'">{{ periodText || '—' }}</span>
  <span v-else-if="column === 'status'" class="path-cell path-cell--status">
    <Badge v-if="isMissed" variant="danger">{{ t('paths.periodStatus.missed') }}</Badge>
    <StatusBadge v-else-if="knownStatus" :status="row.status" />
    <span v-else-if="row.status">{{ t(statusLabelKey(row.status)) }}</span>
    <span v-else aria-hidden="true">—</span>
  </span>
  <span v-else-if="column === 'payment'">
    <template v-if="row.payment">
      <bdi dir="ltr">{{ row.payment.reference }}</bdi>
      <span class="path-cell__sub">{{ t(statusLabelKey(row.payment.status)) }}</span>
    </template>
    <span v-else aria-hidden="true">—</span>
  </span>
  <span v-else-if="column === 'result'">
    <template v-if="percent !== null">
      <bdi dir="ltr">{{ percentText(n, t, percent) }}</bdi>
      <span v-if="path === 'self'" class="path-cell__sub">{{ t('paths.list.indicative') }}</span>
    </template>
    <span v-else aria-hidden="true">—</span>
  </span>
  <span v-else-if="column === 'level'">
    <template v-if="row.level">
      {{ t(`level.names.${row.level}`) }}
      <bdi v-if="row.certificate" class="path-cell__sub" dir="ltr">{{
        row.certificate.number
      }}</bdi>
    </template>
    <span v-else aria-hidden="true">—</span>
  </span>
  <span v-else-if="column === 'kit'">
    {{ row.kit?.status ? t(`paths.kit.status.${row.kit.status}`) : '—' }}
  </span>
  <span v-else-if="column === 'cycle'">{{ localizedText(cycleName, locale) || '—' }}</span>
  <span v-else-if="column === 'certificate'">
    <bdi v-if="row.certificate" dir="ltr">{{ row.certificate.number }}</bdi>
    <span v-else aria-hidden="true">—</span>
  </span>
  <router-link
    v-else-if="column === 'action' && action"
    class="path-cell__action"
    :to="action"
    :data-testid="`row-open-${row.rowKey}`"
  >
    {{ t('paths.list.open') }}
    <span class="path-cell__sr">{{ row.number }}</span>
  </router-link>
</template>
