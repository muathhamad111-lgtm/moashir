<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import ReviewNoteThread from './ReviewNoteThread.vue'
import { EVALUATION_TYPES } from '@/constants/criteria'
import { SCORE_LEVELS } from '@/constants/assessment'
import { scoreFieldName } from '@/utils/assessments/scoreSheet'
import {
  REVIEW_NOTE_MAX,
  isAchieved,
  isNotAchieved,
  noteKey,
  noteUnit,
} from '@/utils/assessments/reviewNotes'

// An internal note on one unit (operator 2026-11-22): staff write it during the review and the
// entity never sees it. «غير متحقق» opens it for the reason; «متحقق» folds it and keeps the text.
const props = defineProps({
  workspace: { type: Object, required: true },
  main: { type: Object, required: true },
  sub: { type: Object, default: null },
  point: { type: Object, default: null },
})

const { t, n } = useI18n()
const notes = props.workspace.notes
const expanded = ref(false)

const unit = computed(() => noteUnit(props))
// The scored node this note sits on: an indicator, or a directly judged sub-criterion.
const scored = computed(() => {
  if (props.point) return { level: SCORE_LEVELS.POINT, node: props.point }
  const direct = props.sub && props.sub.evaluationType !== EVALUATION_TYPES.GUIDING_POINTS
  return direct ? { level: SCORE_LEVELS.SUB, node: props.sub } : null
})
const scoringType = computed(() =>
  scored.value ? props.workspace.scoreSheet.typeOf(scored.value.node) : '',
)
const score = computed(() => {
  if (!scored.value) return ''
  const name = scoreFieldName({ level: scored.value.level, id: scored.value.node.id })
  return props.workspace.scoreSheet.sheet.values[name]
})
const body = computed(() => notes.bodyOf(unit.value))
const others = computed(() => notes.threadOf(unit.value))
const notAchieved = computed(() => isNotAchieved(scoringType.value, score.value))
const achieved = computed(() => isAchieved(scoringType.value, score.value))
const isOpen = computed(() => {
  if (notAchieved.value || expanded.value) return true
  return !achieved.value && Boolean(body.value)
})
const busy = computed(() => Boolean(props.workspace.actions.busy.value))
const fieldName = computed(() => `review-note-${noteKey(unit.value)}`)
const tooLong = computed(() => body.value.length > REVIEW_NOTE_MAX)
const toggleKey = computed(() => (body.value ? 'show' : 'add'))
</script>

<template>
  <div v-if="notes.canRead.value" class="review-note" :class="{ 'is-reason': notAchieved }">
    <ReviewNoteThread v-if="others.length" :notes="others" />
    <template v-if="notes.canWrite.value">
      <BaseTextarea
        v-if="isOpen"
        :model-value="body"
        :name="fieldName"
        :label="t(notAchieved ? 'review.internalNotes.reasonLabel' : 'review.internalNotes.label')"
        :hint="
          t('review.internalNotes.hint', {
            count: n(body.length),
            max: n(REVIEW_NOTE_MAX),
          })
        "
        :error="tooLong ? t('review.internalNotes.tooLong', { max: n(REVIEW_NOTE_MAX) }) : ''"
        :rows="2"
        :disabled="busy"
        @update:model-value="notes.setBody(unit, $event)"
      />
      <button
        v-else
        type="button"
        class="review-note__toggle"
        :data-testid="`review-note-toggle-${noteKey(unit)}`"
        @click="expanded = true"
      >
        <AppIcon name="lock" class="review-note__icon" />
        {{ t(`review.internalNotes.toggle.${toggleKey}`) }}
      </button>
    </template>
  </div>
</template>
