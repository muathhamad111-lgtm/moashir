<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import CriteriaBreadcrumb from '@/components/criteria/CriteriaBreadcrumb.vue'
import CriteriaVersionBanner from '@/components/criteria/CriteriaVersionBanner.vue'
import WeightBudget from '@/components/criteria/WeightBudget.vue'
import GuidingPointsTable from '@/components/criteria/GuidingPointsTable.vue'
import GuidingPointForm from '@/components/criteria/GuidingPointForm.vue'
import CriteriaTextCorrectionDialog from '@/components/criteria/CriteriaTextCorrectionDialog.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaAccess } from '@/composables/useCriteriaAccess'
import { useSubCriterionDetail } from '@/composables/useSubCriterionDetail'
import { useCriteriaNodeActions } from '@/composables/useCriteriaNodeActions'
import { useEvaluationTypeSwitch } from '@/composables/useEvaluationTypeSwitch'
import { compareWeights } from '@/utils/weight'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { CRITERIA_NODE_TYPES as NODE, EVALUATION_TYPES } from '@/constants/criteria'

const { t } = useI18n()
const route = useRoute()
const { localized, weight } = useCriteria()

const mainId = computed(() => Number(route.params.mainId))
const subId = computed(() => Number(route.params.subId))
const { status, errorMessage, sub, points, version, load, refresh } = useSubCriterionDetail(
  mainId,
  subId,
)
const access = useCriteriaAccess(version)
const nodeActions = useCriteriaNodeActions(NODE.POINT, refresh, () => subId.value)
const { toggleType } = useEvaluationTypeSwitch()

const formOpen = ref(false)
const editing = ref(null)
const correcting = ref(null)

onMounted(load)
watch(subId, load)

const isGuidingPoints = computed(
  () => sub.value?.evaluationType === EVALUATION_TYPES.GUIDING_POINTS,
)
const addBlocked = computed(
  () => !sub.value.distributeEqually && compareWeights(sub.value.remainingPointWeight, '0') <= 0,
)
const switchLabel = computed(() =>
  isGuidingPoints.value
    ? t('criteria.evaluationType.switchToDirect')
    : t('criteria.evaluationType.switchToPoints'),
)
const crumbs = computed(() => [
  {
    label: t('criteria.breadcrumb.mains'),
    to: { name: ROUTE_NAMES.ADMIN_CRITERIA, query: { version: version.value.id } },
  },
  {
    label: localized(sub.value.parentName),
    to: { name: ROUTE_NAMES.ADMIN_CRITERIA_MAIN, params: { mainId: mainId.value } },
  },
  { label: `${sub.value.code} ${localized(sub.value.name)}` },
])

const facts = computed(() => [
  { key: 'weight', label: t('criteria.fields.weight'), value: weight(sub.value.weight) },
  {
    key: 'available',
    label: t('criteria.sub.availableFromMain'),
    value: weight(sub.value.availableWeight),
  },
  {
    key: 'cv',
    label: t('criteria.sub.cvField'),
    value: sub.value.hasCv ? t('criteria.sub.enabled') : t('criteria.sub.disabled'),
  },
  {
    key: 'note',
    label: t('criteria.sub.assessorNoteField'),
    value: localized(sub.value.assessorNoteLabel) || t('criteria.sub.noPrompt'),
  },
  {
    key: 'attachments',
    label: t('criteria.columns.attachments'),
    value: t(
      'criteria.attachments.count',
      { count: sub.value.attachments.length },
      sub.value.attachments.length,
    ),
  },
])

function openForm(point = null) {
  editing.value = point
  formOpen.value = true
}

const savePoint = ({ id, payload }) => nodeActions.save(id, payload)

function onAction(key, point) {
  const handlers = {
    edit: () => openForm(point),
    toggle: () => nodeActions.toggle(point),
    delete: () => nodeActions.remove(point, point.code),
    correct: () => (correcting.value = { type: NODE.POINT, node: point }),
  }
  handlers[key]?.()
}
</script>

<template>
  <section class="criteria-page">
    <div v-if="status === 'loading'" class="criteria-page__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <template v-else>
      <CriteriaBreadcrumb :items="crumbs" />
      <CriteriaVersionBanner :version="version" />

      <header class="criteria-page__header">
        <div class="criteria-page__title-row">
          <h1 class="criteria-page__title">{{ localized(sub.name) }}</h1>
          <Badge variant="muted">{{ sub.code }}</Badge>
          <StatusBadge :status="sub.active ? 'active' : 'inactive'" />
          <Badge :variant="isGuidingPoints ? 'info' : 'muted'">
            {{
              isGuidingPoints
                ? t('criteria.evaluationType.guidingPoints')
                : t('criteria.evaluationType.direct')
            }}
          </Badge>
        </div>
        <div class="criteria-page__header-actions">
          <BaseButton
            v-if="access.canCorrectText.value"
            variant="secondary"
            icon="fa-solid fa-spell-check"
            @click="correcting = { type: NODE.SUB, node: sub }"
          >
            {{ t('criteria.actions.correctText') }}
          </BaseButton>
          <BaseButton
            v-if="access.canEditTree.value"
            variant="secondary"
            icon="fa-solid fa-right-left"
            @click="toggleType(sub, refresh)"
          >
            {{ switchLabel }}
          </BaseButton>
        </div>
      </header>
      <p v-if="sub.description" class="criteria-page__subtitle">{{ localized(sub.description) }}</p>

      <dl class="criteria-page__facts">
        <div v-for="fact in facts" :key="fact.key" class="criteria-page__fact">
          <dt>{{ fact.label }}</dt>
          <dd>{{ fact.value }}</dd>
        </div>
      </dl>

      <Card v-if="!isGuidingPoints" :title="t('criteria.sub.directTitle')">
        <p class="criteria-page__direct-text">{{ t('criteria.sub.directExplanation') }}</p>
      </Card>

      <template v-else>
        <WeightBudget
          :capacity="sub.weight"
          :allocated="sub.allocatedPointWeight"
          :capacity-label="t('criteria.budget.subCapacity')"
          :exhausted-message="
            access.canEditTree.value && addBlocked ? t('criteria.budget.exhaustedPoints') : ''
          "
          :equal-distribution="sub.distributeEqually"
        />

        <div class="criteria-page__toolbar">
          <h2 class="criteria-page__section-title">{{ t('criteria.sub.pointsTitle') }}</h2>
          <BaseButton
            v-if="access.canEditTree.value"
            variant="primary"
            icon="fa-solid fa-plus"
            :disabled="addBlocked"
            @click="openForm()"
          >
            {{ t('criteria.point.add') }}
          </BaseButton>
        </div>

        <EmptyState
          v-if="!points.length"
          :title="t('criteria.sub.pointsEmptyTitle')"
          :message="t('criteria.sub.pointsEmptyMessage')"
        >
          <template v-if="access.canEditTree.value && !addBlocked" #action>
            <BaseButton variant="primary" icon="fa-solid fa-plus" @click="openForm()">{{
              t('criteria.point.add')
            }}</BaseButton>
          </template>
        </EmptyState>
        <GuidingPointsTable
          v-else
          :points="points"
          :sub-weight="sub.weight"
          :access="access"
          @action="onAction"
          @move="(index, delta) => nodeActions.move(points, index, delta)"
        />

        <GuidingPointForm
          v-if="access.canEditTree.value"
          v-model="formOpen"
          :point="editing"
          :sub="sub"
          :save="savePoint"
        />
      </template>

      <CriteriaTextCorrectionDialog
        v-if="access.canCorrectText.value"
        :model-value="Boolean(correcting)"
        :version="version"
        :target="correcting"
        @update:model-value="correcting = $event ? correcting : null"
        @corrected="nodeActions.corrected"
      />
    </template>
  </section>
</template>
