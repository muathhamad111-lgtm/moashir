<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCertificateLevels } from '@/composables/useCertificateLevels'
import { levelOfScore } from '@/utils/levelRanges'
import { scorePercentText } from '@/utils/percentText'
import { formatNumber } from '@/utils/formatNumber'
import { localizedText } from '@/utils/localizedText'
import { resultSeries, seriesDelta } from '@/utils/accreditedPerformance'
import KpiTile from './KpiTile.vue'

const props = defineProps({
  accreditedSeries: { type: Array, required: true },
  selfAssessments: { type: Array, required: true },
  certificates: { type: Array, required: true },
  evidenceCount: { type: Number, default: null },
})

const { t, n, locale } = useI18n()
const { ranges } = useCertificateLevels()

const selfSeries = computed(() => resultSeries(props.selfAssessments))
const latestAccredited = computed(() => props.accreditedSeries.at(-1) ?? null)
const latestSelf = computed(() => selfSeries.value.at(-1) ?? null)
const issued = computed(() => props.certificates.filter((item) => item.status === 'issued'))
const selfLevel = computed(() => levelOfScore(ranges.value, latestSelf.value?.percent))

const tiles = computed(() => [
  {
    key: 'accredited',
    icon: 'award',
    label: t('portal.home.kpis.accredited'),
    value: latestAccredited.value
      ? scorePercentText(n, t, latestAccredited.value.percent)
      : t('portal.home.kpis.none'),
    caption: latestAccredited.value
      ? latestAccredited.value.number
      : t('portal.home.kpis.noAccredited'),
    delta: seriesDelta(props.accreditedSeries),
    trend: props.accreditedSeries.map((entry) => entry.percent),
  },
  {
    key: 'self',
    icon: 'clipboard-check',
    label: t('portal.home.kpis.self'),
    value: latestSelf.value
      ? scorePercentText(n, t, latestSelf.value.percent)
      : t('portal.home.kpis.none'),
    caption: selfCaption(),
    delta: seriesDelta(selfSeries.value),
    trend: selfSeries.value.map((entry) => entry.percent),
  },
  {
    key: 'certificates',
    icon: 'certificate',
    label: t('portal.home.kpis.certificates'),
    value: formatNumber(n, issued.value.length),
    caption: issued.value[0]?.number ?? t('portal.home.kpis.noCertificate'),
    delta: null,
    trend: [],
  },
  {
    key: 'evidence',
    icon: 'attachment',
    label: t('portal.home.kpis.evidence'),
    value:
      props.evidenceCount === null
        ? t('portal.home.kpis.none')
        : formatNumber(n, props.evidenceCount),
    caption: t('portal.home.kpis.evidenceCaption'),
    delta: null,
    trend: [],
  },
])

function selfCaption() {
  if (!latestSelf.value) return t('portal.home.kpis.noSelf')
  const label = localizedText(selfLevel.value?.label, locale.value)
  return label
    ? t('portal.home.glance.result.indicative', { level: label })
    : t('portal.home.glance.result.noLevel')
}
</script>

<template>
  <section class="journey-kpis" :aria-label="t('portal.home.kpis.label')">
    <KpiTile
      v-for="tile in tiles"
      :key="tile.key"
      :data-kpi="tile.key"
      :icon="tile.icon"
      :label="tile.label"
      :value="tile.value"
      :caption="tile.caption"
      :delta="tile.delta"
      :trend="tile.trend"
    />
  </section>
</template>
