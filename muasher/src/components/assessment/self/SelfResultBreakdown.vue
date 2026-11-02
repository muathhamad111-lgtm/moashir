<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  result: { type: Object, required: true },
})

const { t, locale } = useI18n()

function text(content) {
  return localizedText(content, locale.value)
}

function subsOf(mainId) {
  return props.result.sub.filter((sub) => sub.mainCriterionId === mainId)
}

function pointsOf(subId) {
  return props.result.points.filter((point) => point.subCriterionId === subId)
}
</script>

<template>
  <section class="self-breakdown" :aria-labelledby="`breakdown-${result.id}`">
    <h2 :id="`breakdown-${result.id}`" class="self-result__section-title">{{ t('assessment.self.result.breakdownTitle') }}</h2>
    <ul class="self-result__mains">
      <li v-for="main in result.main" :key="main.criterionId" class="self-result__main">
        <Card>
          <div class="self-result__main-head">
            <h3 class="self-result__main-title">
              <span class="u-ltr">{{ main.code }}</span>
              {{ text(main.name) }}
            </h3>
            <div class="self-result__main-score">
              <Badge variant="info"><span class="u-ltr">{{ main.percent }}%</span></Badge>
              <span class="self-result__main-earned u-ltr">
                {{ t('assessment.self.result.earnedOf', { earned: main.earned, weight: main.weight }) }}
              </span>
            </div>
          </div>
          <progress
            class="self-result__bar"
            max="100"
            :value="Number(main.percent)"
            :aria-label="text(main.name)"
            :aria-valuetext="`${main.percent}%`"
          ></progress>
          <ul class="self-result__subs">
            <li v-for="sub in subsOf(main.criterionId)" :key="sub.subId" class="self-result__sub">
              <div class="self-result__sub-row">
                <span class="self-result__sub-name">
                  <span class="u-ltr">{{ sub.code }}</span>
                  {{ text(sub.name) }}
                </span>
                <span class="self-result__sub-score">
                  <span v-if="sub.rawValue" class="self-result__sub-raw">
                    {{ t('assessment.self.result.rawValue', { raw: sub.rawValue }) }}
                  </span>
                  <span class="self-result__sub-percent u-ltr">{{ sub.percent }}%</span>
                  <span class="self-result__sub-earned u-ltr">
                    {{ t('assessment.self.result.earnedOf', { earned: sub.earned, weight: sub.weight }) }}
                  </span>
                </span>
              </div>
              <ul v-if="pointsOf(sub.subId).length" class="self-result__points">
                <li v-for="point in pointsOf(sub.subId)" :key="point.pointId" class="self-result__point">
                  <span class="self-result__point-name">
                    <span class="u-ltr">{{ point.code }}</span>
                    {{ text(point.description) }}
                  </span>
                  <span class="self-result__sub-score">
                    <span v-if="point.rawValue" class="self-result__sub-raw">{{ t('assessment.self.result.rawValue', { raw: point.rawValue }) }}</span>
                    <span class="self-result__sub-percent u-ltr">{{ point.percent }}%</span>
                  </span>
                </li>
              </ul>
            </li>
          </ul>
        </Card>
      </li>
    </ul>
  </section>
</template>
