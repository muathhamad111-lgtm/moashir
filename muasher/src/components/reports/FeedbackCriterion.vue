<script setup>
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import { percentText } from '@/utils/percentText'

const FIELDS = [
  { key: 'strengths', tone: 'strengths' },
  { key: 'improvementPoints', tone: 'improvements' },
  { key: 'notes', tone: 'notes' },
]

defineProps({
  criterion: { type: Object, required: true },
})

const { t, n, locale } = useI18n()

function percentOf(value) {
  return percentText(n, t, Number(value ?? 0))
}
</script>

<template>
  <section class="feedback-criterion" :aria-labelledby="`criterion-${criterion.code}`">
    <header class="feedback-criterion__head">
      <h3 :id="`criterion-${criterion.code}`" class="feedback-criterion__title">
        <bdi class="feedback-criterion__code" dir="ltr">{{ criterion.code }}</bdi>
        {{ localizedText(criterion.name, locale) }}
      </h3>
      <bdi class="feedback-criterion__percent" dir="ltr">{{ percentOf(criterion.percent) }}</bdi>
    </header>
    <ul class="feedback-criterion__subs">
      <li v-for="sub in criterion.subs" :key="sub.subId ?? sub.code" class="feedback-report__sub">
        <div class="feedback-report__sub-head">
          <h4 class="feedback-report__sub-title">
            <bdi class="feedback-criterion__code" dir="ltr">{{ sub.code }}</bdi>
            {{ localizedText(sub.name, locale) }}
          </h4>
          <span class="feedback-report__sub-score">
            <bdi dir="ltr">{{ percentOf(sub.percent) }}</bdi>
            <span class="feedback-report__sub-points">
              {{ t('feedback.report.points', { earned: sub.earned, weight: sub.weight }) }}
            </span>
          </span>
        </div>
        <template v-for="field in FIELDS" :key="field.key">
          <div
            v-if="sub[field.key]"
            class="feedback-report__note"
            :class="`feedback-report__note--${field.tone}`"
          >
            <p class="feedback-report__note-label">
              {{ t(`feedback.report.fields.${field.key}`) }}
            </p>
            <p class="feedback-report__note-text">{{ sub[field.key] }}</p>
          </div>
        </template>
      </li>
    </ul>
  </section>
</template>
