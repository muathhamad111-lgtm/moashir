<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import CriteriaBreadcrumb from '@/components/criteria/CriteriaBreadcrumb.vue'
import CriteriaVersionBanner from '@/components/criteria/CriteriaVersionBanner.vue'
import WeightBudget from '@/components/criteria/WeightBudget.vue'
import CriteriaWeightsCard from '@/components/criteria/CriteriaWeightsCard.vue'
import SubCriteriaTable from '@/components/criteria/SubCriteriaTable.vue'
import SubCriterionForm from '@/components/criteria/SubCriterionForm.vue'
import CriteriaTextCorrectionDialog from '@/components/criteria/CriteriaTextCorrectionDialog.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaAccess } from '@/composables/useCriteriaAccess'
import { useMainCriterionDetail } from '@/composables/useMainCriterionDetail'
import { useCriteriaNodeActions } from '@/composables/useCriteriaNodeActions'
import { useEvaluationTypeSwitch } from '@/composables/useEvaluationTypeSwitch'
import { criteriaService } from '@/services/criteriaService'
import { compareWeights } from '@/utils/weight'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { localized } = useCriteria()

const mainId = computed(() => Number(route.params.mainId))
const { status, errorMessage, main, subs, version, load, refresh } = useMainCriterionDetail(mainId)
const access = useCriteriaAccess(version)
const nodeActions = useCriteriaNodeActions(NODE.SUB, refresh, () => mainId.value)
const { toggleType } = useEvaluationTypeSwitch()

const formOpen = ref(false)
const editing = ref(null)
const correcting = ref(null)

const weightItems = computed(() =>
  subs.value.map((sub) => ({
    id: sub.id,
    label: localized(sub.name),
    weight: sub.weight,
    active: sub.active,
  })),
)
const saveMode = (distributeEqually) =>
  criteriaService.updateMain(mainId.value, { distributeEqually })
const saveWeights = (weights) => criteriaService.saveSubWeights(mainId.value, weights)

onMounted(load)
watch(mainId, load)

const addBlocked = computed(
  () => !main.value.distributeEqually && compareWeights(main.value.remainingWeight, '0') <= 0,
)
const crumbs = computed(() => [
  {
    label: t('criteria.breadcrumb.mains'),
    to: { name: ROUTE_NAMES.ADMIN_CRITERIA, query: { version: version.value.id } },
  },
  { label: localized(main.value.name) },
])

function openForm(sub = null) {
  editing.value = sub
  formOpen.value = true
}

const saveSub = ({ id, payload }) => nodeActions.save(id, payload)

function onAction(key, sub) {
  const handlers = {
    open: () =>
      router.push({
        name: ROUTE_NAMES.ADMIN_CRITERIA_SUB,
        params: { mainId: mainId.value, subId: sub.id },
      }),
    edit: () => openForm(sub),
    toggle: () => nodeActions.toggle(sub),
    delete: () => nodeActions.remove(sub, localized(sub.name)),
    switch: () => toggleType(sub, refresh),
    correct: () => (correcting.value = { type: NODE.SUB, node: sub }),
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
        <div>
          <div class="criteria-page__title-row">
            <h1 class="criteria-page__title">{{ localized(main.name) }}</h1>
            <Badge variant="info">{{ t(`criteria.groups.${main.group}`) }}</Badge>
            <StatusBadge :status="main.active ? 'active' : 'inactive'" />
          </div>
          <p v-if="main.description" class="criteria-page__subtitle">
            {{ localized(main.description) }}
          </p>
        </div>
        <div class="criteria-page__header-actions">
          <BaseButton
            v-if="access.canCorrectText.value"
            variant="secondary"
            icon="pen-line"
            @click="correcting = { type: NODE.MAIN, node: main }"
          >
            {{ t('criteria.actions.correctText') }}
          </BaseButton>
          <BaseButton
            v-if="access.canEditTree.value"
            variant="primary"
            icon="plus"
            :disabled="addBlocked"
            @click="openForm()"
          >
            {{ t('criteria.main.addSub') }}
          </BaseButton>
        </div>
      </header>

      <CriteriaWeightsCard
        v-if="access.canEditTree.value"
        :title="t('criteria.weights.editorTitle')"
        :parent-weight="main.weight"
        :distribute-equally="main.distributeEqually"
        :items="weightItems"
        :save-mode="saveMode"
        :save-weights="saveWeights"
        :reload="refresh"
      >
        <p v-if="addBlocked" class="criteria-page__hint" role="note">
          {{ t('criteria.budget.exhaustedSubs') }}
        </p>
      </CriteriaWeightsCard>
      <WeightBudget
        v-else
        :capacity="main.weight"
        :allocated="main.allocatedWeight"
        :capacity-label="t('criteria.budget.mainCapacity')"
        :equal-distribution="main.distributeEqually"
      />

      <h2 class="criteria-page__section-title">{{ t('criteria.main.subsTitle') }}</h2>
      <EmptyState
        v-if="!subs.length"
        :title="t('criteria.main.subsEmptyTitle')"
        :message="t('criteria.main.subsEmptyMessage')"
      >
        <template v-if="access.canEditTree.value && !addBlocked" #action>
          <BaseButton variant="primary" icon="plus" @click="openForm()">{{
            t('criteria.main.addSub')
          }}</BaseButton>
        </template>
      </EmptyState>
      <SubCriteriaTable
        v-else
        :subs="subs"
        :main-weight="main.weight"
        :access="access"
        @action="onAction"
        @move="(index, delta) => nodeActions.move(subs, index, delta)"
      />

      <SubCriterionForm
        v-if="access.canEditTree.value"
        v-model="formOpen"
        :criterion="editing"
        :main="main"
        :save="saveSub"
      />
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
