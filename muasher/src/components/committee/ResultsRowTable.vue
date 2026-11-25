<script setup>
import { percentText } from '@/utils/percentText'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import RankSelect from './RankSelect.vue'
import { RANK_KEYS } from '@/constants/committee'
import { localizedText } from '@/utils/localizedText'
import { COLUMN_KINDS } from '@/utils/tableColumns'

const props = defineProps({
  rows: { type: Array, required: true },
  rankField: { type: String, required: true },
  canRank: { type: Boolean, default: false },
  busy: { type: String, default: '' },
  showRegion: { type: Boolean, default: false },
})

const emit = defineEmits(['rank'])
const { t, locale, n } = useI18n()
const DATES = ['submittedAt', 'approvedAt']

const columns = computed(() => [
  { key: 'entity', label: t('award.results.columns.entity') },
  ...(props.showRegion ? [{ key: 'region', label: t('award.results.columns.region') }] : []),
  { key: 'status', label: t('award.results.columns.status') },
  { key: 'average', label: t('award.results.columns.average') },
  ...(props.showRegion
    ? [{ key: 'regionalRank', label: t('award.results.columns.regionalRank') }]
    : []),
  { key: 'rank', label: t('award.results.columns.rank') },
  {
    key: 'entityReference',
    label: t('award.results.columns.entityReference'),
    kind: COLUMN_KINDS.CODE,
  },
  ...DATES.map((key) => ({ key, label: t(`award.results.columns.${key}`) })),
])
</script>

<template>
  <DataTable
    table-key="award-results"
    stacked
    :columns="columns"
    :items="rows"
    status="success"
    :empty-title="t('award.results.emptyTitle')"
    :empty-message="t('award.results.emptyMessage')"
  >
    <template #cell-entity="{ item }">
      <span class="committee-member__name">{{ localizedText(item.entity?.name, locale) }}</span>
      <bdi dir="ltr" class="committee-member__note">{{ item.number }}</bdi>
    </template>
    <template #cell-region="{ item }">{{ localizedText(item.region?.name, locale) }}</template>
    <template #cell-status="{ item }"><StatusBadge :status="item.status" /></template>
    <template #cell-average="{ item }"
      ><bdi class="u-ltr results-table__average">{{
        percentText(n, t, item.averagePercent)
      }}</bdi></template
    >
    <template #cell-regionalRank="{ item }">{{
      item.regionalRank ? t(`award.results.rank.${RANK_KEYS[item.regionalRank]}`) : ''
    }}</template>
    <template #cell-rank="{ item }">
      <RankSelect
        :rank="item[rankField]"
        :name="`rank-${item.assessmentId}`"
        :disabled="!canRank || Boolean(busy)"
        @change="emit('rank', item, $event)"
      />
    </template>
    <template #cell-entityReference="{ item }"
      ><bdi class="u-ltr">{{ item.entity?.reference }}</bdi></template
    >
    <template v-for="key in DATES" :key="key" #[`cell-${key}`]="{ item }"
      ><DateStamp v-if="item[key]" :value="item[key]" :with-time="false"
    /></template>
  </DataTable>
</template>
