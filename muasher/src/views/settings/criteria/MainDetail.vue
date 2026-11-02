<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
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
import WeightDistributionEditor from '@/components/criteria/WeightDistributionEditor.vue'
import SubCriteriaTable from '@/components/criteria/SubCriteriaTable.vue'
import SubCriterionForm from '@/components/criteria/SubCriterionForm.vue'
import CriteriaTextCorrectionDialog from '@/components/criteria/CriteriaTextCorrectionDialog.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useCriteriaAccess } from '@/composables/useCriteriaAccess'
import { useMainCriterionDetail } from '@/composables/useMainCriterionDetail'
import { useCriteriaNodeActions } from '@/composables/useCriteriaNodeActions'
import { useEvaluationTypeSwitch } from '@/composables/useEvaluationTypeSwitch'
import { useWeightDistribution } from '@/composables/useWeightDistribution'
import { criteriaService } from '@/services/criteriaService'
import { compareWeights } from '@/utils/weight'
import { weightChanges } from '@/utils/criteriaOrder'
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

const editedWeights = ref([])
const formOpen = ref(false)
const editing = ref(null)
const correcting = ref(null)

function resetWeights() {
  editedWeights.value = subs.value.map((sub) => ({
    id: sub.id,
    label: `${sub.code} ${localized(sub.name)}`,
    weight: sub.weight,
    active: sub.active,
  }))
}

watch(subs, resetWeights)
onMounted(load)
watch(mainId, load)

const distribution = useWeightDistribution({
  saveParentMode: (distributeEqually) =>
    criteriaService.updateMain(mainId.value, { distributeEqually }),
  saveChildWeight: (id, weight) => criteriaService.updateSub(id, { weight }),
  reload: refresh,
})

const mode = computed(() => (main.value?.distributeEqually ? 'equal' : 'manual'))
const weightsDirty = computed(() => weightChanges(editedWeights.value, subs.value).length > 0)
const addBlocked = computed(
  () => !main.value.distributeEqually && compareWeights(main.value.remainingWeight, '0') <= 0,
)
const crumbs = computed(() => [
  {
    label: t('criteria.breadcrumb.mains'),
    to: { name: ROUTE_NAMES.ADMIN_CRITERIA, query: { version: version.value.id } },
  },
  { label: `${main.value.code} ${localized(main.value.name)}` },
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
            <Badge variant="muted">{{ main.code }}</Badge>
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
            icon="fa-solid fa-spell-check"
            @click="correcting = { type: NODE.MAIN, node: main }"
          >
            {{ t('criteria.actions.correctText') }}
          </BaseButton>
          <BaseButton
            v-if="access.canEditTree.value"
            variant="primary"
            icon="fa-solid fa-plus"
            :disabled="addBlocked"
            @click="openForm()"
          >
            {{ t('criteria.main.addSub') }}
          </BaseButton>
        </div>
      </header>

      <Card v-if="access.canEditTree.value" :title="t('criteria.weights.editorTitle')">
        <WeightDistributionEditor
          v-model:children="editedWeights"
          :mode="mode"
          :parent-weight="main.weight"
          :disabled="distribution.saving.value"
          @update:mode="distribution.changeMode"
        />
        <p v-if="addBlocked" class="criteria-page__hint" role="note">
          {{ t('criteria.budget.exhaustedSubs') }}
        </p>
        <template v-if="mode === 'manual'" #footer>
          <div class="criteria-page__weight-actions">
            <BaseButton
              variant="ghost"
              :disabled="!weightsDirty || distribution.saving.value"
              @click="resetWeights"
              >{{ t('criteria.weights.reset') }}</BaseButton
            >
            <BaseButton
              variant="primary"
              :loading="distribution.saving.value"
              :disabled="!weightsDirty"
              @click="distribution.saveWeights(editedWeights, subs)"
            >
              {{ t('criteria.weights.save') }}
            </BaseButton>
          </div>
        </template>
      </Card>
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
          <BaseButton variant="primary" icon="fa-solid fa-plus" @click="openForm()">{{
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
