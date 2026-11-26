<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import DataTable from '@/components/data-list/DataTable.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import Badge from '@/components/ui/Badge.vue'
import CycleStatusBadge from './CycleStatusBadge.vue'
import CycleWindow from './CycleWindow.vue'
import { COLUMN_KINDS } from '@/utils/tableColumns'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'
import { thresholdText } from '@/utils/percentText'

defineProps({
  items: { type: Array, required: true },
  status: { type: String, required: true },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, locale, n } = useI18n()
const { NUMBER, STATUS } = COLUMN_KINDS
const DATE_COLUMNS = ['openedAt', 'closedAt', 'createdAt', 'updatedAt']

const columns = computed(() => [
  { key: 'name', label: t('cycles.columns.name') },
  { key: 'status', label: t('cycles.columns.status') },
  { key: 'submission', label: t('cycles.columns.submission') },
  { key: 'regions', label: t('cycles.columns.regions') },
  { key: 'counts', label: t('cycles.columns.requests') },
  { key: 'committee', label: t('cycles.columns.committee') },
  { key: 'announcementAt', label: t('cycles.columns.announcementAt') },
  { key: 'entryFee', label: t('cycles.columns.entryFee'), kind: NUMBER },
  { key: 'scaleKind', label: t('cycles.columns.scaleKind'), kind: STATUS },
  { key: 'initialReviewSlaDays', label: t('cycles.columns.initialReviewSlaDays'), kind: NUMBER },
  { key: 'minEntityAge', label: t('cycles.columns.minEntityAge'), kind: NUMBER },
  { key: 'disqualificationScore', label: t('cycles.columns.disqualificationScore'), kind: NUMBER },
  {
    key: 'participationCertificate',
    label: t('cycles.columns.participationCertificate'),
    kind: STATUS,
  },
  ...DATE_COLUMNS.map((key) => ({ key, label: t(`cycles.columns.${key}`) })),
])
</script>

<template>
  <DataTable
    table-key="award-cycles"
    stacked
    :columns="columns"
    :items="items"
    :status="status"
    :error-message="errorMessage"
    :empty-title="t('cycles.list.emptyTitle')"
    :empty-message="t('cycles.list.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-name="{ item }">
      <RouterLink
        class="cycle-table__link"
        :to="{ name: ROUTE_NAMES.AWARD_CYCLE, params: { id: item.id } }"
      >
        {{ localizedText(item.name, locale) }} <bdi class="u-ltr">{{ item.year }}</bdi>
      </RouterLink>
    </template>
    <template #cell-status="{ item }"><CycleStatusBadge :status="item.status" /></template>
    <template #cell-submission="{ item }">
      <CycleWindow :start="item.submissionStart" :end="item.submissionEnd" />
    </template>
    <template #cell-regions="{ item }"
      ><span v-if="item.regions">{{
        t('cycles.list.regionCount', { count: item.regions.length }, item.regions.length)
      }}</span></template
    >
    <template #cell-counts="{ item }">
      <span v-if="item.counts" class="cycle-table__counts">
        {{
          t('cycles.list.counts', {
            requests: item.counts.requests,
            qualified: item.counts.qualified,
          })
        }}
      </span>
    </template>
    <template #cell-committee="{ item }">
      <CycleWindow :start="item.committeeStart" :end="item.committeeEnd" />
    </template>
    <template #cell-announcementAt="{ item }"
      ><DateStamp :value="item.announcementAt" :with-time="false"
    /></template>
    <template #cell-entryFee="{ item }"
      ><MoneyAmount
        v-if="item.entryFeeQuote"
        :value="item.entryFeeQuote.total"
        :currency="item.entryFeeQuote.currency"
    /></template>
    <template #cell-scaleKind="{ item }">{{
      item.scaleKind ? t(`assessment.self.scaleNames.${item.scaleKind}`) : ''
    }}</template>
    <template #cell-initialReviewSlaDays="{ item }">{{ item.initialReviewSlaDays ?? '' }}</template>
    <template #cell-minEntityAge="{ item }">{{
      item.conditions?.minEntityAgeYears ?? ''
    }}</template>
    <template #cell-disqualificationScore="{ item }"
      ><bdi v-if="item.conditions?.disqualificationScore" class="u-ltr">{{
        thresholdText(n, t, item.conditions.disqualificationScore)
      }}</bdi></template
    >
    <template #cell-participationCertificate="{ item }"
      ><Badge
        v-if="typeof item.participationCertificateEnabled === 'boolean'"
        :variant="item.participationCertificateEnabled ? 'success' : 'muted'"
        >{{ item.participationCertificateEnabled ? t('common.yes') : t('common.no') }}</Badge
      ></template
    >
    <template v-for="key in DATE_COLUMNS" :key="key" #[`cell-${key}`]="{ item }"
      ><DateStamp v-if="item[key]" :value="item[key]" :with-time="false"
    /></template>
  </DataTable>
</template>
