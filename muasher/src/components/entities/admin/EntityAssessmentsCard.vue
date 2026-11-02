<script setup>
import { onMounted, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'
import { useEntityAssessments } from '@/composables/useEntityAssessments'
import { formatDate } from '@/utils/datetime'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  entityId: { type: [Number, String], required: true },
})

const { t, locale } = useI18n()
const { status, items, total, load } = useEntityAssessments(toRef(props, 'entityId'))

function dateOf(value) {
  return formatDate(value, locale.value, { dateStyle: 'medium', timeZone: 'Asia/Riyadh' })
}

onMounted(load)
</script>

<template>
  <Card :title="t('assessment.staff.entityCardTitle')" class="entity-assessments">
    <Skeleton v-if="status === 'loading'" variant="text" :count="3" />
    <ErrorState v-else-if="status === 'error'" :message="t('assessment.staff.loadFailed')" @retry="load" />
    <p v-else-if="status === 'empty'" class="entity-assessments__empty">{{ t('assessment.staff.noAssessments') }}</p>
    <template v-else>
      <ul class="entity-assessments__list">
        <li v-for="item in items" :key="item.id" class="entity-assessments__item">
          <router-link class="entity-assessments__link" :to="{ name: ROUTE_NAMES.ADMIN_ASSESSMENT, params: { id: item.id } }">
            <span class="u-ltr">{{ item.number }}</span>
          </router-link>
          <span class="entity-assessments__path">{{ t(`assessment.paths.${item.path}.title`) }}</span>
          <span class="entity-assessments__date">{{ dateOf(item.createdAt) }}</span>
          <span class="entity-assessments__badges">
            <StatusBadge :status="item.status" />
            <FrozenBadge v-if="item.isFrozen" />
          </span>
          <span v-if="item.result" class="entity-assessments__score u-ltr">{{ item.result.percent }}%</span>
        </li>
      </ul>
      <p v-if="total > items.length" class="entity-assessments__more">
        {{ t('assessment.staff.moreAssessments', { shown: items.length, total }) }}
      </p>
    </template>
  </Card>
</template>
