<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'
import AssessmentStatusHistory from './AssessmentStatusHistory.vue'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  item: { type: Object, required: true },
})

const { t } = useI18n()

const isSelf = computed(() => props.item.path === ASSESSMENT_PATHS.SELF)
const isAccredited = computed(() => props.item.path === ASSESSMENT_PATHS.ACCREDITED)
const hasResult = computed(() => Boolean(props.item.result))
const resultRoute = computed(() =>
  isAccredited.value
    ? ROUTE_NAMES.ASSESSMENT_ACCREDITED_RESULT
    : ROUTE_NAMES.ASSESSMENT_SELF_RESULT,
)
const canContinue = computed(() => props.item.isOpen && !props.item.isFrozen)
const continueRoute = computed(() =>
  isAccredited.value
    ? { name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM, params: { id: props.item.id } }
    : { name: ROUTE_NAMES.ASSESSMENT_SELF },
)
</script>

<template>
  <li class="history-item">
    <div class="history-item__head">
      <div class="history-item__identity">
        <span class="history-item__path">{{ t(`assessment.paths.${item.path}.title`) }}</span>
        <span class="history-item__number u-ltr">{{ item.number }}</span>
      </div>
      <div class="history-item__badges">
        <StatusBadge :status="item.status" />
        <FrozenBadge v-if="item.isFrozen" />
      </div>
    </div>
    <AssessmentStatusHistory :assessment-id="item.id" />
    <div class="history-item__foot">
      <span v-if="hasResult" class="history-item__score">
        {{ t('assessment.history.scored', { percent: item.result.percent }) }}
      </span>
      <span v-else class="history-item__score history-item__score--muted">{{
        t('assessment.history.inProgress')
      }}</span>
      <div class="history-item__actions">
        <router-link
          v-if="(isSelf || isAccredited) && hasResult"
          class="btn btn--ghost btn--sm"
          :to="{ name: resultRoute, params: { id: item.id } }"
        >
          {{ t('assessment.history.viewResult') }}
          <AppIcon name="arrow-right" />
        </router-link>
        <router-link
          v-if="canContinue && (isSelf || isAccredited)"
          class="btn btn--secondary btn--sm"
          :to="continueRoute"
        >
          <AppIcon name="pen" />
          {{
            isAccredited
              ? t('assessment.history.openRequest')
              : t('assessment.history.continueDraft')
          }}
        </router-link>
      </div>
    </div>
  </li>
</template>
