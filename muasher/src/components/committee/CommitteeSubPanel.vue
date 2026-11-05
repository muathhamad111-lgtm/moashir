<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import RawScoreField from '@/components/assessment/RawScoreField.vue'
import ReviewEvidenceList from '@/components/review/ReviewEvidenceList.vue'
import CommitteePriorScores from './CommitteePriorScores.vue'
import { EVALUATION_TYPES } from '@/constants/criteria'
import { SCORE_LEVELS } from '@/constants/assessment'
import { COMMITTEE_LIMITS } from '@/constants/committee'
import { savedScoreOf, scoreFieldName } from '@/utils/assessments/scoreSheet'
import { evidenceOfSub } from '@/utils/accredited/evidenceRules'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  sub: { type: Object, required: true },
  workspace: { type: Object, required: true },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()

const evaluation = computed(() => props.workspace.state.evaluation.value)
const disabled = computed(
  () => !props.workspace.state.canScore.value || Boolean(props.workspace.actions.busy.value),
)
const usesPoints = computed(() => props.sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS)
const cv = computed(() => evaluation.value.cv[String(props.sub.id)] ?? '')
const files = computed(() => evidenceOfSub(evaluation.value.evidence, props.sub.id))
const maximum = computed(() => props.workspace.scoreSheet.maximum.value)
const units = computed(() =>
  usesPoints.value
    ? props.sub.points.map((point) => unitOf(SCORE_LEVELS.POINT, point))
    : [unitOf(SCORE_LEVELS.SUB, props.sub)],
)
const note = computed(() => props.workspace.notes.noteOf(props.sub.id))
const noteError = computed(() =>
  note.value.length > COMMITTEE_LIMITS.NOTE_MAX
    ? t('award.common.maxChars', { max: COMMITTEE_LIMITS.NOTE_MAX })
    : '',
)

function unitOf(level, node) {
  return {
    level,
    id: node.id,
    code: node.code,
    label: level === SCORE_LEVELS.POINT ? node.description : node.name,
  }
}

function text(content) {
  return localizedText(content, locale.value)
}
</script>

<template>
  <li :id="`committee-sub-${sub.id}`" class="review-sub" tabindex="-1">
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
      <div v-for="unit in units" :key="scoreFieldName(unit)" class="review-sub__point">
        <p v-if="unit.level === SCORE_LEVELS.POINT" class="review-sub__point-text">
          <span class="review-sub__code u-ltr">{{ unit.code }}</span> {{ text(unit.label) }}
        </p>
        <RawScoreField
          :name="scoreFieldName(unit)"
          :label="
            unit.level === SCORE_LEVELS.POINT
              ? t('review.pointScoreLabel', { code: unit.code })
              : t('review.directScoreLabel', { code: unit.code })
          "
          :maximum="maximum"
          :saved="savedScoreOf(evaluation.scores, unit)"
          :disabled="disabled"
        />
      </div>
    </div>
    <BaseTextarea
      :model-value="note"
      :name="`committee-note-${sub.id}`"
      :label="t('award.evaluation.subNote', { code: sub.code })"
      :hint="t('award.evaluation.noteHint')"
      :rows="3"
      :error="noteError"
      :disabled="disabled"
      @update:model-value="workspace.notes.setNote(sub.id, $event)"
    />
    <CommitteePriorScores
      v-if="evaluation.prior"
      :prior="evaluation.prior"
      :units="units"
      :sub-id="sub.id"
    />
  </li>
</template>
