<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import WizardStepper from '@/components/wizard/WizardStepper.vue'
import CriteriaBreadcrumb from '@/components/criteria/CriteriaBreadcrumb.vue'
import CriteriaReviewNotes from '@/components/criteria/review/CriteriaReviewNotes.vue'
import CriteriaReviewChanges from '@/components/criteria/review/CriteriaReviewChanges.vue'
import CriteriaPublishDone from '@/components/criteria/review/CriteriaPublishDone.vue'
import CriteriaReviewApply from '@/components/criteria/workspace/CriteriaReviewApply.vue'
import { useCriteriaReviewPage } from '@/composables/useCriteriaReviewPage'
import { useCriteriaAccess } from '@/composables/useCriteriaAccess'
import { ROUTE_NAMES } from '@/constants/routeNames'

// Review and publish a draft on a full page (UX-A3, CR-7): notes → changes → apply and publish.
const { t } = useI18n()
const page = useCriteriaReviewPage()
const access = useCriteriaAccess(page.draft)

const at = ref(0)
const outcome = ref(null)
const step = computed(() => page.steps.value[at.value])
const stepItems = computed(() =>
  page.steps.value.map((id) => ({ id, label: t(`criteria.workspace.review.steps.${id}`) })),
)
const blocked = computed(
  () => page.consistency.checking.value || page.consistency.problems.value.length > 0,
)
const backLink = { name: ROUTE_NAMES.ADMIN_CRITERIA }
const crumbs = computed(() => [
  { label: t('criteria.index.title'), to: backLink },
  { label: t('criteria.review.crumb') },
])

function go(offset) {
  at.value = Math.min(Math.max(at.value + offset, 0), page.steps.value.length - 1)
}

// The stepper only goes back: a later step is reached with «التالي», after the checks.
function select(index) {
  if (index < at.value) at.value = index
}

// Each step and the outcome start at the top of the page, not where the previous step was scrolled to.
watch([at, outcome], () => window.scrollTo({ top: 0 }))

onMounted(page.load)
</script>

<template>
  <section class="criteria-review-page">
    <CriteriaBreadcrumb :items="crumbs" />
    <div v-if="page.status.value === 'loading'" class="criteria-page__loading">
      <Skeleton variant="block" />
    </div>
    <ErrorState v-else-if="page.status.value === 'error'" @retry="page.load" />
    <EmptyState
      v-else-if="!page.draft.value || !access.canPublishDraft.value"
      :title="t('criteria.review.unavailableTitle')"
      :message="t('criteria.review.unavailableMessage')"
      icon="hierarchy"
    >
      <template #action>
        <BaseButton variant="primary" :to="backLink">{{
          t('criteria.review.backToCriteria')
        }}</BaseButton>
      </template>
    </EmptyState>
    <CriteriaPublishDone
      v-else-if="outcome"
      :outcome="outcome"
      :change-count="page.preview.preview.value?.changeCount ?? 0"
    />
    <template v-else>
      <header class="criteria-page__header">
        <div>
          <h1 class="criteria-page__title">
            {{ t('criteria.workspace.review.title', { number: page.draft.value.versionNumber }) }}
          </h1>
          <p class="criteria-page__subtitle">
            {{ t(`criteria.review.subtitle.${page.published.value ? 'replace' : 'first'}`) }}
          </p>
        </div>
      </header>
      <div class="criteria-review-page__steps">
        <WizardStepper :steps="stepItems" :current-index="at" @select="select" />
      </div>

      <template v-if="step !== 'apply'">
        <CriteriaReviewNotes
          v-if="step === 'notes'"
          :checking="page.consistency.checking.value"
          :problems="page.consistency.problems.value"
          :version-id="page.draft.value.id"
        />
        <CriteriaReviewChanges
          v-else
          :preview="page.preview"
          :main-names="page.mainNames.value"
          :version-id="page.draft.value.id"
        />
        <footer class="criteria-review-page__bar">
          <BaseButton v-if="at === 0" variant="secondary" :to="backLink">
            {{ t('actions.cancel') }}
          </BaseButton>
          <BaseButton v-else variant="secondary" icon="arrow-left" @click="go(-1)">
            {{ t('criteria.workspace.review.back') }}
          </BaseButton>
          <BaseButton variant="primary" icon-end="arrow-right" :disabled="blocked" @click="go(1)">
            {{ t('criteria.workspace.review.next') }}
          </BaseButton>
        </footer>
      </template>
      <CriteriaReviewApply
        v-else
        :version="page.draft.value"
        :published="page.published.value"
        :preview="page.preview"
        @back="go(-1)"
        @published="outcome = $event"
      />
    </template>
  </section>
</template>
