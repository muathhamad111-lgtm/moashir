<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import RawScoreField from '@/components/assessment/RawScoreField.vue'
import { EVALUATION_TYPES } from '@/constants/criteria'
import { SCORE_LEVELS } from '@/constants/assessment'
import { savedScoreOf, scoreFieldName } from '@/utils/assessments/scoreSheet'
import { localizedText } from '@/utils/localizedText'

defineProps({
  main: { type: Object, required: true },
  step: { type: Number, required: true },
  total: { type: Number, required: true },
  scores: { type: Object, required: true },
  maximum: { type: String, required: true },
  disabled: { type: Boolean, default: false },
})

const { t, locale } = useI18n()
const title = ref(null)

function text(content) {
  return localizedText(content, locale.value)
}

function usesPoints(sub) {
  return sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS
}

function unit(level, node) {
  return { level, id: node.id }
}

function focusTitle() {
  title.value?.focus()
}

defineExpose({ focusTitle })
</script>

<template>
  <Card class="self-main">
    <template #header>
      <div class="self-main__head">
        <p class="self-main__step">{{ t('assessment.self.steps.counter', { step, total }) }}</p>
        <h2 ref="title" class="self-main__title" tabindex="-1">{{ text(main.name) }}</h2>
      </div>
    </template>

    <p v-if="main.description" class="self-main__description">{{ text(main.description) }}</p>

    <ul class="self-main__subs">
      <li v-for="sub in main.subs" :key="sub.id" class="self-main__sub">
        <div class="self-main__sub-head">
          <h3 class="self-main__sub-title">{{ text(sub.name) }}</h3>
          <Badge :variant="usesPoints(sub) ? 'info' : 'muted'">
            {{
              usesPoints(sub)
                ? t('assessment.self.tree.points', { count: sub.points.length })
                : t('assessment.self.tree.direct')
            }}
          </Badge>
        </div>
        <p v-if="sub.description" class="self-main__sub-description">{{ text(sub.description) }}</p>

        <template v-if="usesPoints(sub)">
          <p class="self-main__hint">{{ t('assessment.self.pointsHint') }}</p>
          <ul class="self-main__points">
            <li v-for="(point, index) in sub.points" :key="point.id" class="self-main__point">
              <p class="self-main__point-text">{{ text(point.description) }}</p>
              <RawScoreField
                :name="scoreFieldName(unit(SCORE_LEVELS.POINT, point))"
                :label="t('assessment.self.pointScoreLabel', { number: index + 1 })"
                :maximum="maximum"
                :saved="savedScoreOf(scores, unit(SCORE_LEVELS.POINT, point))"
                :disabled="disabled"
              />
            </li>
          </ul>
        </template>

        <RawScoreField
          v-else
          :name="scoreFieldName(unit(SCORE_LEVELS.SUB, sub))"
          :label="t('assessment.self.directLabel', { name: text(sub.name) })"
          :maximum="maximum"
          :saved="savedScoreOf(scores, unit(SCORE_LEVELS.SUB, sub))"
          :disabled="disabled"
        />
      </li>
    </ul>
  </Card>
</template>
