<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Badge from '@/components/ui/Badge.vue'
import ReviewScoreControl from './ReviewScoreControl.vue'
import ReviewIndicatorRow from './ReviewIndicatorRow.vue'
import ReviewEvidenceRows from './ReviewEvidenceRows.vue'
import ReviewRoundRows from './ReviewRoundRows.vue'
import ReviewFoldedText from './ReviewFoldedText.vue'
import ReviewFeedbackFold from './ReviewFeedbackFold.vue'
import { EVALUATION_TYPES } from '@/constants/criteria'
import { SCORE_LEVELS } from '@/constants/assessment'
import { savedScoreOf, scoreFieldName } from '@/utils/assessments/scoreSheet'
import { evidenceOfSub } from '@/utils/accredited/evidenceRules'
import {
  isUnitScored,
  scoredCount,
  subEvidenceOf,
  subUnits,
  unitOf,
} from '@/utils/assessments/reviewRows'
import { localizedText } from '@/utils/localizedText'
import { formatShare, shareOf } from '@/utils/weight'
import { useSubRounds } from '@/composables/useSubRounds'
import { useOverrideRows } from '@/composables/useOverrideRows'

// One sub-criterion as a card (design §B.3.1): its title with the score (direct) or the indicator
// count facing it, then the CV, its own attachments, the indicators with their evidence, and the
// feedback folded at the end.
const props = defineProps({
  sub: { type: Object, required: true },
  main: { type: Object, required: true },
  workspace: { type: Object, required: true },
})

const emit = defineEmits(['download'])
const { t, locale } = useI18n()

const review = computed(() => props.workspace.reviewState.review.value)
const values = computed(() => props.workspace.scoreSheet.sheet.values)
const overrideRows = useOverrideRows(props.workspace)
const busy = computed(() => Boolean(props.workspace.actions.busy.value))
const feedbackDisabled = computed(() => !props.workspace.reviewState.canScore.value || busy.value)
// The override mode opens the published scores for editing; the feedback stays as it was.
const disabled = computed(() => {
  const editable = props.workspace.reviewState.canScore.value || overrideRows.active.value
  return !editable || busy.value
})
const usesPoints = computed(() => props.sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS)
const units = computed(() => subUnits(props.sub))
const scored = computed(() => scoredCount(values.value, units.value))
const files = computed(() => evidenceOfSub(review.value.evidence, props.sub.id))
const evidence = computed(() =>
  subEvidenceOf(props.sub, files.value, review.value.lateAttachmentKeys),
)
const { roundOf, newIds, anyAwaiting } = useSubRounds({
  sub: computed(() => props.sub),
  units,
  files,
  workspace: props.workspace,
  disabled,
})
const done = computed(() => scored.value === units.value.length && !anyAwaiting.value)
const ownRows = computed(() => {
  const others = evidence.value.others
  if (!others.length) return evidence.value.own
  return [
    ...evidence.value.own,
    { key: 'other', name: null, required: false, late: false, files: others },
  ]
})
const share = computed(() =>
  formatShare(shareOf(props.sub.weight, props.main.weight), locale.value),
)
const subUnit = computed(() => unitOf(SCORE_LEVELS.SUB, props.sub))

const text = (content) => localizedText(content, locale.value)
const typeOf = (node) => props.workspace.scoreSheet.typeOf(node)
const pointUnit = (point) => unitOf(SCORE_LEVELS.POINT, point)
const subRound = computed(() => roundOf(subUnit.value))
const badge = computed(() => {
  if (anyAwaiting.value) return { variant: 'warning', key: 'awaiting' }
  return done.value ? { variant: 'success', key: 'scored' } : { variant: 'muted', key: 'pending' }
})
const confirm = (unit) => props.workspace.actions.confirmScore(unit)
</script>

<template>
  <li
    :id="`review-sub-${sub.id}`"
    class="review-sub review-card"
    :class="{
      'is-scored': done,
      'is-awaiting': anyAwaiting,
      'is-changed': !usesPoints && overrideRows.isChanged(subUnit),
    }"
    tabindex="-1"
  >
    <div class="review-card__head">
      <span
        class="review-state"
        :class="{ 'is-done': done, 'is-awaiting': anyAwaiting }"
        aria-hidden="true"
      >
        <AppIcon v-if="done" name="check" />
      </span>
      <div class="review-card__heading">
        <h3 class="review-card__title">
          <bdi class="criteria-code">{{ sub.code }}</bdi>
          {{ text(sub.name) }}
        </h3>
        <p class="review-card__meta">
          <span>{{ t('review.shareOfMain', { share }) }}</span>
          <span>{{
            usesPoints
              ? t('assessment.self.tree.points', { count: sub.points.length })
              : t('assessment.self.tree.direct')
          }}</span>
          <span v-if="!usesPoints">{{ t(`criteria.scoringTypes.${typeOf(sub)}.label`) }}</span>
          <Badge :variant="badge.variant">{{ t(`review.state.${badge.key}`) }}</Badge>
        </p>
      </div>
      <ReviewScoreControl
        v-if="!usesPoints"
        class="review-card__control"
        :name="scoreFieldName(subUnit)"
        :label="t('review.directScoreLabel', { name: text(sub.name) })"
        :scoring-type="typeOf(sub)"
        :saved="savedScoreOf(review.scores, subUnit)"
        :disabled="disabled"
        :changed-label="overrideRows.changedLabel(subUnit, typeOf(sub))"
      />
      <span v-else class="review-card__count">
        {{ t('review.indicatorsScored', { scored, total: units.length }) }}
      </span>
    </div>

    <div class="review-card__body">
      <ReviewFoldedText
        v-if="sub.hasCv"
        :title="t('review.cvTitle')"
        :text="review.cv[String(sub.id)] ?? ''"
        :empty-text="t('review.noCv')"
      />
      <section v-if="ownRows.length" class="review-card__section">
        <h4 class="review-card__label">
          {{ t(usesPoints ? 'review.subAttachments' : 'review.directEvidence') }}
        </h4>
        <ReviewEvidenceRows
          :rows="ownRows"
          :downloading="workspace.downloads.busy"
          :new-ids="newIds"
          @download="emit('download', $event)"
        />
      </section>
      <ReviewRoundRows
        v-if="!usesPoints && subRound.rows.length"
        :rows="subRound.rows"
        :scoring-type="typeOf(sub)"
        :label="text(sub.name)"
        :can-confirm="subRound.canConfirm"
        :confirming="subRound.confirming"
        @confirm="confirm(subUnit)"
      />
      <section v-if="usesPoints" class="review-card__section">
        <h4 class="review-card__label">{{ t('review.indicators') }}</h4>
        <ul class="review-card__items">
          <ReviewIndicatorRow
            v-for="point in sub.points"
            :key="point.id"
            :point="point"
            :sub="sub"
            :name="scoreFieldName(pointUnit(point))"
            :scoring-type="typeOf(point)"
            :saved="savedScoreOf(review.scores, pointUnit(point))"
            :scored="isUnitScored(values, pointUnit(point))"
            :disabled="disabled"
            :evidence="evidence.byPoint.get(point.id) ?? []"
            :downloading="workspace.downloads.busy"
            :round="roundOf(pointUnit(point))"
            :changed-label="overrideRows.changedLabel(pointUnit(point), typeOf(point))"
            :changed="overrideRows.isChanged(pointUnit(point))"
            @download="emit('download', $event)"
            @confirm="confirm(pointUnit(point))"
          />
        </ul>
      </section>
      <ReviewFeedbackFold
        :sub-id="sub.id"
        :fields="workspace.feedback.fieldsOf(sub.id)"
        :note-label="text(sub.assessorNoteLabel)"
        :disabled="feedbackDisabled"
        @update="workspace.feedback.setField(sub.id, $event.field, $event.value)"
      />
    </div>
  </li>
</template>
