<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { pathLabelOf } from '@/utils/assessments/pathLabel'
import { percentText } from '@/utils/percentText'
import { isoDate } from '@/utils/datetime'

const RESULT_ROUTES = {
  [ASSESSMENT_PATHS.SELF]: ROUTE_NAMES.ASSESSMENT_SELF_RESULT,
  [ASSESSMENT_PATHS.ACCREDITED]: ROUTE_NAMES.ASSESSMENT_ACCREDITED_RESULT,
  [ASSESSMENT_PATHS.ADVANCED]: ROUTE_NAMES.ASSESSMENT_ADVANCED_RESULT,
}

const props = defineProps({
  item: { type: Object, required: true },
})

const { t, n, locale } = useI18n()
const label = computed(() => pathLabelOf(props.item, t, locale.value))
const percent = computed(() => percentText(n, t, Number(props.item.result?.percent ?? 0)))
const completedOn = computed(() => isoDate(props.item.completedAt ?? props.item.submittedAt))
const isAccredited = computed(() => props.item.path === ASSESSMENT_PATHS.ACCREDITED)
</script>

<template>
  <li class="assessment-result">
    <div class="assessment-result__main">
      <p class="assessment-result__path">{{ label }}</p>
      <p class="assessment-result__meta">
        <bdi dir="ltr">{{ item.number }}</bdi>
        <span v-if="completedOn" aria-hidden="true">·</span>
        <bdi v-if="completedOn" dir="ltr">{{ completedOn }}</bdi>
      </p>
    </div>
    <bdi class="assessment-result__percent" dir="ltr">{{ percent }}</bdi>
    <div class="assessment-result__actions">
      <router-link
        v-if="RESULT_ROUTES[item.path]"
        class="assessment-result__link"
        :to="{ name: RESULT_ROUTES[item.path], params: { id: item.id } }"
      >
        {{ t('portal.assessments.results.view') }}
        <span class="assessment-result__context">{{ item.number }}</span>
      </router-link>
      <router-link
        v-if="isAccredited"
        class="assessment-result__link"
        :to="{ name: ROUTE_NAMES.REPORTS_FEEDBACK, params: { assessmentId: item.id } }"
      >
        {{ t('portal.assessments.results.feedback') }}
        <span class="assessment-result__context">{{ item.number }}</span>
      </router-link>
    </div>
  </li>
</template>
