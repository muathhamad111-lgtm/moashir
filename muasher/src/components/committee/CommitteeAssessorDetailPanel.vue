<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { ratioToPercent } from '@/utils/assessmentScoring'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  detail: { type: Object, required: true },
  localized: { type: Function, required: true },
  totalLabel: { type: String, default: '' },
})

const { t, n } = useI18n()

const resolvedTotalLabel = computed(
  () => props.totalLabel || t('advanced.secretary.review.committeeAssessorTotal'),
)

const tree = computed(() => props.detail.tree ?? [])

function subPercent(subId) {
  const ratio = props.detail.scores?.subs?.[subId]
  if (typeof ratio !== 'number') return null
  return ratioToPercent(ratio)
}

function pointPercent(pointId) {
  const ratio = props.detail.scores?.points?.[pointId]
  if (typeof ratio !== 'number') return null
  return ratioToPercent(ratio)
}

function noteFor(subId) {
  return props.detail.notes?.find((note) => note.subCriterionId === subId)?.text ?? ''
}

function fmt(value) {
  return formatNumber(n, value, 'default')
}
</script>

<template>
  <div class="committee-assessor-detail">
    <div class="committee-assessor-detail__summary">
      <StatusBadge v-if="detail.committeeAssessorStatus" :status="detail.committeeAssessorStatus" />
      <span v-if="detail.total != null" class="committee-assessor-detail__total">
        {{ resolvedTotalLabel }}: <strong>{{ fmt(detail.total) }}%</strong>
      </span>
    </div>

    <ul class="committee-assessor-detail__mains">
      <li v-for="main in tree" :key="main.id" class="committee-assessor-detail__main">
        <h3 class="committee-assessor-detail__main-title">{{ localized(main.name) }}</h3>
        <ul class="committee-assessor-detail__subs">
          <li v-for="sub in main.subs" :key="sub.id" class="committee-assessor-detail__sub">
            <div class="committee-assessor-detail__sub-head">
              <span class="committee-assessor-detail__sub-title">{{ localized(sub.name) }}</span>
              <Badge :variant="sub.evaluationType === 'guiding-points' ? 'muted' : 'info'">
                {{
                  sub.evaluationType === 'guiding-points'
                    ? t('advanced.secretary.review.guidance')
                    : t('advanced.secretary.review.direct')
                }}
              </Badge>
            </div>

            <template v-if="sub.evaluationType === 'guiding-points' && sub.points.length">
              <ul class="committee-assessor-detail__points">
                <li
                  v-for="point in sub.points"
                  :key="point.id"
                  class="committee-assessor-detail__point"
                >
                  <span class="committee-assessor-detail__point-desc">{{
                    localized(point.description)
                  }}</span>
                  <span class="committee-assessor-detail__score">
                    <template v-if="pointPercent(point.id) != null">
                      {{ t('advanced.secretary.review.scoreLabel') }}:
                      <strong>{{ fmt(pointPercent(point.id)) }}%</strong>
                    </template>
                    <template v-else>{{ t('advanced.secretary.review.noScore') }}</template>
                  </span>
                </li>
              </ul>
            </template>
            <p
              v-else
              class="committee-assessor-detail__score committee-assessor-detail__score--direct"
            >
              <template v-if="subPercent(sub.id) != null">
                {{ t('advanced.secretary.review.scoreLabel') }}:
                <strong>{{ fmt(subPercent(sub.id)) }}%</strong>
              </template>
              <template v-else>{{ t('advanced.secretary.review.noScore') }}</template>
            </p>

            <div class="committee-assessor-detail__note">
              <span class="committee-assessor-detail__note-label">{{
                t('advanced.secretary.review.noteLabel')
              }}</span>
              <p v-if="noteFor(sub.id)" class="committee-assessor-detail__note-text">
                {{ noteFor(sub.id) }}
              </p>
              <p
                v-else
                class="committee-assessor-detail__note-text committee-assessor-detail__note-text--empty"
              >
                {{ t('advanced.secretary.review.noNote') }}
              </p>
            </div>
          </li>
        </ul>
      </li>
    </ul>
  </div>
</template>
