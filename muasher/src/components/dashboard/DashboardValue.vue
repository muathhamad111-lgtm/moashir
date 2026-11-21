<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import { CARD_FORMATS } from '@/constants/dashboardBoards'
import { COLUMN_FORMATS } from '@/constants/dashboardWidgets'
import { formatNumber } from '@/utils/formatNumber'
import { percentText } from '@/utils/percentText'
import { formatRiyadhDate, formatRiyadhDateTime } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'
import { statusLabelKey } from '@/utils/canonicalStatus'

const props = defineProps({
  value: { type: [String, Number, Boolean, Object, null], default: null },
  format: { type: String, default: CARD_FORMATS.COUNT },
  row: { type: Object, default: null },
})

const { t, te, n, locale } = useI18n()
const F = { ...CARD_FORMATS, ...COLUMN_FORMATS }
const LATIN = [F.COUNT, F.NUMBER, F.PERCENT, F.REFERENCE]
const DATES = [F.DATE, F.DATETIME]

const isEmpty = computed(
  () => props.value === null || props.value === undefined || props.value === '',
)

function statusText(value) {
  const key = statusLabelKey(value)
  return te(key) ? t(key) : String(value)
}

const TEXT = {
  [F.COUNT]: (value) => formatNumber(n, value),
  [F.NUMBER]: (value) => formatNumber(n, value),
  [F.PERCENT]: (value) => percentText(n, t, value),
  [F.DAYS]: (value) =>
    t('dashboard.units.days', { count: formatNumber(n, value) }, Math.round(Number(value))),
  [F.DATE]: (value) => formatRiyadhDate(value, locale.value),
  [F.DATETIME]: (value) => formatRiyadhDateTime(value, locale.value),
  [F.LOCALIZED]: (value) => localizedText(value, locale.value),
  [F.STATUS]: statusText,
  [F.BOOLEAN]: (value) => t(value ? 'dashboard.values.yes' : 'dashboard.values.no'),
  [F.TYPE]: (value) => t(`dashboard.values.editTypes.${value}`),
  [F.REFERENCE]: (value) => String(value),
  [F.TEXT]: (value) => String(value),
}

const text = computed(() =>
  isEmpty.value ? '' : (TEXT[props.format] ?? TEXT[F.TEXT])(props.value),
)
const isLatin = computed(() => LATIN.includes(props.format))
const isDate = computed(() => DATES.includes(props.format))
</script>

<template>
  <span v-if="isEmpty" class="dashboard-value dashboard-value--empty">{{
    t('dashboard.values.none')
  }}</span>
  <MoneyAmount v-else-if="format === F.MONEY" :value="String(value)" />
  <PathBadge v-else-if="format === F.PATH" :path="String(value)" :label="row?.pathLabel ?? null" />
  <bdi v-else-if="isLatin" class="dashboard-value" dir="ltr">{{ text }}</bdi>
  <bdi v-else-if="isDate" class="dashboard-value">{{ text }}</bdi>
  <span v-else class="dashboard-value">{{ text }}</span>
</template>
