<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import RawScoreField from '@/components/assessment/RawScoreField.vue'
import ReviewEvidenceList from './ReviewEvidenceList.vue'
import ReviewFeedbackFields from './ReviewFeedbackFields.vue'
import { EVALUATION_TYPES } from '@/constants/criteria'
import { SCORE_LEVELS } from '@/constants/assessment'
import { savedScoreOf, scoreFieldName } from '@/utils/assessments/scoreSheet'
import { evidenceOfSub } from '@/utils/accredited/evidenceRules'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  sub: { type: Object, required: true },
  workspace: { type: Object, required: true },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()

const review = computed(() => props.workspace.reviewState.review.value)
const disabled = computed(
  () => !props.workspace.reviewState.canScore.value || Boolean(props.workspace.actions.busy.value),
)
const usesPoints = computed(() => props.sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS)
const cv = computed(() => review.value.cv[String(props.sub.id)] ?? '')
const files = computed(() => evidenceOfSub(review.value.evidence, props.sub.id))
const maximum = computed(() => props.workspace.scoreSheet.maximum.value)

function text(content) {
  return localizedText(content, locale.value)
}

function unit(level, node) {
  return { level, id: node.id }
}
</script>

<template>
  <li :id="`review-sub-${sub.id}`" class="review-sub" tabindex="-1">
    <div class="review-sub__head">
      <h3 class="review-sub__title">
        <span class="review-sub__code u-ltr">{{ sub.code }}</span> {{ text(sub.name) }}
      </h3>
      <Badge :variant="usesPoints ? 'info' : 'muted'">{{
        usesPoints
          ? t('assessment.self.tree.points', { count: sub.points.length })
          : t('assessment.self.tree.direct')
      }}</Badge>
    </div>
    <div v-if="sub.hasCv" class="review-sub__cv">
      <p class="review-sub__label">{{ t('review.cvTitle') }}</p>
      <p v-if="cv" class="review-sub__cv-text">{{ cv }}</p>
      <p v-else class="review-sub__empty">{{ t('review.noCv') }}</p>
    </div>
    <ReviewEvidenceList
      :files="files"
      :downloading="workspace.downloads.busy"
      @download="emit('download', $event)"
    />
    <div class="review-sub__scores">
      <template v-if="usesPoints">
        <div v-for="point in sub.points" :key="point.id" class="review-sub__point">
          <p class="review-sub__point-text">
            <span class="review-sub__code u-ltr">{{ point.code }}</span>
            {{ text(point.description) }}
          </p>
          <RawScoreField
            :name="scoreFieldName(unit(SCORE_LEVELS.POINT, point))"
            :label="t('review.pointScoreLabel', { code: point.code })"
            :maximum="maximum"
            :saved="savedScoreOf(review.scores, unit(SCORE_LEVELS.POINT, point))"
            :disabled="disabled"
          />
        </div>
      </template>
      <RawScoreField
        v-else
        :name="scoreFieldName(unit(SCORE_LEVELS.SUB, sub))"
        :label="t('review.directScoreLabel', { code: sub.code })"
        :maximum="maximum"
        :saved="savedScoreOf(review.scores, unit(SCORE_LEVELS.SUB, sub))"
        :disabled="disabled"
      />
    </div>
    <ReviewFeedbackFields
      :sub-id="sub.id"
      :fields="workspace.feedback.fieldsOf(sub.id)"
      :disabled="disabled"
      @update="workspace.feedback.setField(sub.id, $event.field, $event.value)"
    />
  </li>
</template>
