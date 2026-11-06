<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'
import ProgressMeter from '@/components/ui/ProgressMeter.vue'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { ENTITY_EDITABLE_STATUSES } from '@/constants/accredited'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { pathLabelOf } from '@/utils/assessments/pathLabel'
import { riyadhYear } from '@/utils/riyadhYear'
import AppIcon from '@/components/ui/AppIcon.vue'

const FORM_ROUTES = {
  [ASSESSMENT_PATHS.SELF]: () => ({ name: ROUTE_NAMES.ASSESSMENT_SELF }),
  [ASSESSMENT_PATHS.ACCREDITED]: (item) => ({
    name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM,
    params: { id: item.id },
  }),
  [ASSESSMENT_PATHS.ADVANCED]: (item) => ({
    name: ROUTE_NAMES.ASSESSMENT_ADVANCED_FORM,
    params: { id: item.id },
  }),
}

const props = defineProps({
  item: { type: Object, required: true },
  progress: { type: Object, default: null },
})

const { t, locale } = useI18n()

const label = computed(() => pathLabelOf(props.item, t, locale.value))
const isEditable = computed(
  () => !props.item.isFrozen && ENTITY_EDITABLE_STATUSES.includes(props.item.status),
)
const target = computed(() => FORM_ROUTES[props.item.path]?.(props.item) ?? null)
const remaining = computed(() =>
  props.progress ? props.progress.subsTotal - props.progress.subsComplete : 0,
)
const actionKey = computed(() => {
  if (!isEditable.value) return 'view'
  return props.item.path === ASSESSMENT_PATHS.ACCREDITED ? 'evidence' : 'continue'
})
</script>

<template>
  <article class="current-assessment" :aria-labelledby="`current-${item.id}`">
    <div class="current-assessment__head">
      <h3 :id="`current-${item.id}`" class="current-assessment__title">
        {{ label }} —
        <bdi dir="ltr">{{ riyadhYear(item.createdAt) }}</bdi>
      </h3>
      <div class="current-assessment__badges">
        <StatusBadge :status="item.status" />
        <FrozenBadge v-if="item.isFrozen" />
      </div>
    </div>
    <p class="current-assessment__number">
      <bdi dir="ltr">{{ item.number }}</bdi>
    </p>
    <div v-if="progress && isEditable" class="current-assessment__progress">
      <ProgressMeter
        :label="t('portal.assessments.current.progress')"
        :current="progress.subsComplete"
        :total="progress.subsTotal"
      />
      <p class="current-assessment__hint">
        {{ t('portal.assessments.current.remaining', { count: remaining }, remaining) }}
      </p>
    </div>
    <p v-else-if="!isEditable" class="current-assessment__hint">
      {{ t(`portal.assessments.current.states.${item.path}`) }}
    </p>
    <router-link
      v-if="target"
      class="btn"
      :class="isEditable ? 'btn--primary' : 'btn--secondary'"
      :to="target"
    >
      {{ t(`portal.assessments.current.actions.${actionKey}`) }}
      <AppIcon name="arrow-right" />
    </router-link>
  </article>
</template>
