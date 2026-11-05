<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCertificateLevels } from '@/composables/useCertificateLevels'
import { levelOfScore } from '@/utils/levelRanges'
import { percentText } from '@/utils/percentText'
import { riyadhDateKey } from '@/utils/datetime'
import { ROUTE_NAMES } from '@/constants/routeNames'
import GlanceCard from './GlanceCard.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const SELF_PATH = 'self'

const props = defineProps({
  result: { type: Object, default: null },
})

const { t, n, locale } = useI18n()
const { ranges } = useCertificateLevels()

const isSelf = computed(() => props.result?.path === SELF_PATH)
const pathLabel = computed(() => {
  const label = props.result?.pathLabel
  return label?.[locale.value] || label?.ar || t(`portal.home.paths.${props.result?.path}`)
})
const level = computed(() =>
  isSelf.value ? levelOfScore(ranges.value, props.result?.percent) : null,
)
const levelLabel = computed(
  () => level.value?.label?.[locale.value] || level.value?.label?.ar || '',
)
const reportRoute = computed(() => ({
  name: isSelf.value
    ? ROUTE_NAMES.ASSESSMENT_SELF_RESULT
    : ROUTE_NAMES.ASSESSMENT_ACCREDITED_RESULT,
  params: { id: String(props.result?.assessmentId) },
}))
</script>

<template>
  <GlanceCard
    :title="t('portal.home.glance.result.title')"
    icon="chart-bar"
    heading-id="glance-result-title"
  >
    <template v-if="result">
      <bdi class="glance-result__percent" dir="ltr">{{ percentText(n, t, result.percent) }}</bdi>
      <p class="glance-result__meta">
        {{ pathLabel }} ·
        <bdi class="glance-result__date" dir="ltr">{{
          result.completedAt ? riyadhDateKey(result.completedAt) : ''
        }}</bdi>
      </p>
      <p v-if="isSelf" class="glance-result__note">
        {{
          levelLabel
            ? t('portal.home.glance.result.indicative', { level: levelLabel })
            : t('portal.home.glance.result.noLevel')
        }}
      </p>
    </template>
    <p v-else class="glance-card__empty">{{ t('portal.home.glance.result.empty') }}</p>
    <template v-if="result" #footer>
      <router-link class="glance-card__link" :to="reportRoute">
        <AppIcon name="doc" />
        <span>{{
          isSelf ? t('portal.home.glance.result.selfReport') : t('portal.home.glance.result.report')
        }}</span>
      </router-link>
    </template>
  </GlanceCard>
</template>
