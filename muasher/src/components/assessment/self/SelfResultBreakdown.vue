<script setup>
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { localizedText } from '@/utils/localizedText'
import { scorePercentText } from '@/utils/percentText'

const props = defineProps({
  result: { type: Object, required: true },
})

const { t, n, locale } = useI18n()
const baseId = useId()
const openIds = ref([])

function text(content) {
  return localizedText(content, locale.value)
}

function percent(value) {
  return scorePercentText(n, t, value)
}

function subsOf(mainId) {
  return props.result.sub.filter((sub) => sub.mainCriterionId === mainId)
}

function pointsOf(subId) {
  return props.result.points.filter((point) => point.subCriterionId === subId)
}

function isOpen(mainId) {
  return openIds.value.includes(mainId)
}

function toggle(mainId) {
  openIds.value = isOpen(mainId)
    ? openIds.value.filter((id) => id !== mainId)
    : [...openIds.value, mainId]
}

function panelId(mainId) {
  return `${baseId}-main-${mainId}`
}
</script>

<template>
  <section class="self-breakdown" :aria-labelledby="`breakdown-${result.id}`">
    <h2 :id="`breakdown-${result.id}`" class="self-result__section-title">
      {{ t('assessment.self.result.breakdownTitle') }}
    </h2>
    <ul class="self-result__mains">
      <li
        v-for="main in result.main"
        :key="main.criterionId"
        class="self-result__main"
        :class="{ 'is-open': isOpen(main.criterionId) }"
      >
        <Card>
          <h3 class="self-result__main-heading">
            <button
              type="button"
              class="self-result__main-head"
              :aria-expanded="isOpen(main.criterionId)"
              :aria-controls="panelId(main.criterionId)"
              @click="toggle(main.criterionId)"
            >
              <span class="self-result__main-title">
                <span class="u-ltr">{{ main.code }}</span>
                {{ text(main.name) }}
              </span>
              <span class="self-result__main-score">
                <Badge variant="info"
                  ><span class="u-ltr">{{ percent(main.percent) }}</span></Badge
                >
                <span class="self-result__main-earned u-ltr">
                  {{
                    t('assessment.self.result.earnedOf', {
                      earned: main.earned,
                      weight: main.weight,
                    })
                  }}
                </span>
                <AppIcon name="chevron-down" class="self-result__main-chevron" />
              </span>
            </button>
          </h3>
          <progress
            class="self-result__bar"
            max="100"
            :value="Number(main.percent)"
            :aria-label="text(main.name)"
            :aria-valuetext="percent(main.percent)"
          ></progress>
          <ul
            v-show="isOpen(main.criterionId)"
            :id="panelId(main.criterionId)"
            class="self-result__subs"
          >
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
                  <span class="self-result__sub-percent u-ltr">{{ percent(sub.percent) }}</span>
                  <span class="self-result__sub-earned u-ltr">
                    {{
                      t('assessment.self.result.earnedOf', {
                        earned: sub.earned,
                        weight: sub.weight,
                      })
                    }}
                  </span>
                </span>
              </div>
              <ul v-if="pointsOf(sub.subId).length" class="self-result__points">
                <li
                  v-for="point in pointsOf(sub.subId)"
                  :key="point.pointId"
                  class="self-result__point"
                >
                  <span class="self-result__point-name">
                    <span class="u-ltr">{{ point.code }}</span>
                    {{ text(point.description) }}
                  </span>
                  <span class="self-result__sub-score">
                    <span v-if="point.rawValue" class="self-result__sub-raw">{{
                      t('assessment.self.result.rawValue', { raw: point.rawValue })
                    }}</span>
                    <span class="self-result__sub-percent u-ltr">{{ percent(point.percent) }}</span>
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
