<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import { formatKpiValue } from '@/utils/kpiNumber'

// One optional detail column of the indicators table (KPI_DETAIL_COLUMNS); a value the indicator
// lacks stays empty.
const props = defineProps({
  field: { type: String, required: true },
  kpi: { type: Object, required: true },
  types: { type: Array, default: () => [] },
  localized: { type: Function, required: true },
})

const { t, n } = useI18n()

const DATE_FIELDS = Object.freeze({
  valueUpdatedAt: 'lastUpdate',
  publishedAt: 'publishedAt',
  updatedAt: 'updatedAt',
})

const TEXT_OF = {
  baseline: (kpi) => formatKpiValue(n, kpi.baseline),
  unit: (kpi) => props.localized(kpi.unit),
  type: (kpi) => props.localized(props.types.find((type) => type.id === kpi.kpiTypeId)?.name),
  measurementYear: (kpi) => (kpi.measurementYear ? String(kpi.measurementYear) : ''),
  owner: (kpi) => props.localized(kpi.owner),
  dataSource: (kpi) => props.localized(kpi.dataSource),
  updatedBy: (kpi) => kpi.updatedBy?.name ?? '',
  slug: (kpi) => kpi.slug ?? '',
}

const date = computed(() => (DATE_FIELDS[props.field] ? props.kpi[DATE_FIELDS[props.field]] : null))
const text = computed(() => TEXT_OF[props.field]?.(props.kpi) || '')
</script>

<template>
  <DateStamp v-if="date" :value="date" :with-time="false" />
  <Badge v-else-if="field === 'onHomepage' && kpi.showOnHomepage" variant="info">{{
    t('kpis.admin.featuredBadge')
  }}</Badge>
  <bdi v-else-if="field === 'slug' && text" dir="ltr">{{ text }}</bdi>
  <template v-else>{{ text }}</template>
</template>
