<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import LevelBadge from '@/components/level/LevelBadge.vue'
import InsightPercent from './InsightPercent.vue'
import InsightFigure from './InsightFigure.vue'
import InsightsDelta from './InsightsDelta.vue'
import { CERTIFICATE_STANDINGS } from '@/constants/insights'
import { LEVEL_CERTIFICATE_STATUS } from '@/constants/levels'

const props = defineProps({
  summary: { type: Object, required: true },
})

const { t } = useI18n()
const standing = computed(() => props.summary.certificate?.standing)
const previous = computed(() => props.summary.change?.previous ?? null)
const certificateNumber = computed(() => {
  const certificate = props.summary.result?.certificate
  return certificate?.status === LEVEL_CERTIFICATE_STATUS ? (certificate.number ?? null) : null
})
const certificateKey = computed(() =>
  standing.value === CERTIFICATE_STANDINGS.LEVEL
    ? 'insights.board.validCertificate'
    : 'insights.board.participationCertificate',
)
const showsNumber = computed(
  () => Boolean(certificateNumber.value) && standing.value !== CERTIFICATE_STANDINGS.NOT_ISSUED,
)
</script>

<template>
  <dl class="executive-facts">
    <div class="executive-facts__tile">
      <dt>{{ t('insights.board.score') }}</dt>
      <dd>
        <InsightPercent class="executive-facts__value" :value="summary.score.percent" />
        <i18n-t keypath="insights.board.points" tag="span" class="executive-facts__note">
          <template #earned><InsightFigure :value="summary.score.earned" /></template>
          <template #total><InsightFigure :value="summary.score.totalWeight" /></template>
        </i18n-t>
      </dd>
    </div>
    <div class="executive-facts__tile">
      <dt>{{ t('insights.board.level') }}</dt>
      <dd data-testid="executive-level">
        <LevelBadge
          v-if="standing === CERTIFICATE_STANDINGS.LEVEL"
          :tier="summary.certificate.level"
        />
        <span
          v-else-if="standing === CERTIFICATE_STANDINGS.PARTICIPATION"
          class="executive-facts__plain"
        >
          {{ t('insights.board.participation') }}
        </span>
        <span v-else class="executive-facts__plain">{{ t('insights.board.notIssued') }}</span>
        <i18n-t
          v-if="showsNumber"
          :keypath="certificateKey"
          tag="span"
          class="executive-facts__note"
          data-testid="executive-certificate-number"
        >
          <template #number>
            <bdi dir="ltr">{{ certificateNumber }}</bdi>
          </template>
        </i18n-t>
      </dd>
    </div>
    <div class="executive-facts__tile">
      <dt>{{ t('insights.board.change') }}</dt>
      <dd>
        <template v-if="previous && summary.change.deltaPp !== null">
          <InsightsDelta :delta-pp="summary.change.deltaPp" />
          <i18n-t keypath="insights.board.changeVs" tag="span" class="executive-facts__note">
            <template #number>
              <bdi dir="ltr">{{ previous.number }}</bdi>
            </template>
          </i18n-t>
        </template>
        <span v-else class="executive-facts__note">{{ t('insights.delta.none') }}</span>
      </dd>
    </div>
  </dl>
</template>
