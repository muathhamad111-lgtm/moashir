<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import GapCard from '@/components/bag/GapCard.vue'
import BagIntro from '@/components/bag/BagIntro.vue'
import { useBagEntity } from '@/composables/useBag'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t, n } = useI18n()

const {
  status,
  errorMessage,
  subStatus,
  isEligible,
  face,
  view,
  reference,
  product,
  pricing,
  history,
  localized,
  load,
} = useBagEntity()

const showIntro = computed(() => face.value === 'eligible' || face.value === 'not-eligible')

useHead({ title: () => t('bag.index.title') })

onMounted(load)

const stage = computed(() => view.value?.stage ?? null)
const openGaps = computed(() => view.value?.gaps ?? [])

const helperFiles = computed(() =>
  openGaps.value.flatMap((gap) => (gap.helperFiles ?? []).map((file) => ({ ...file, gapId: gap.id }))),
)

</script>

<template>
  <section class="bag">
    <div v-if="status === 'loading'" class="bag__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="5" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <template v-else>
      <BagIntro
        v-if="showIntro && product"
        :product="product"
        :pricing="pricing"
        :localized="localized"
        :eligible="isEligible"
      />

      <template v-else>
        <header class="bag__header">
          <div>
            <p class="bag__eyebrow">{{ t('bag.index.eyebrow') }}</p>
            <h1 class="bag__title">{{ t('bag.index.title') }}</h1>
          </div>
          <StatusBadge :status="subStatus" />
        </header>

        <p class="bag__intro">{{ t('bag.index.intro') }}</p>

        <EmptyState
          v-if="face === 'period-ended'"
          icon="fa-solid fa-flag-checkered"
          :title="t('bag.index.periodEnded.title')"
          :message="t('bag.index.periodEnded.message')"
        >
          <template #action>
            <router-link class="bag__return-link" :to="{ name: ROUTE_NAMES.ASSESSMENT_ACCREDITED }">
              <BaseButton variant="primary" icon="fa-solid fa-rotate-left" tabindex="-1">
                {{ t('bag.index.returnToAccredited') }}
              </BaseButton>
            </router-link>
          </template>
        </EmptyState>

        <template v-else>
        <p v-if="reference" class="bag__reference" role="note">
          <i class="fa-solid fa-lock bag__reference-icon" aria-hidden="true"></i>
          <span>{{
            t('bag.index.reference', {
              id: n(reference.assessmentId, 'id'),
              version: n(reference.version, 'id'),
            })
          }}</span>
        </p>

        <Card v-if="stage" class="bag__stage">
          <div class="bag__stage-head">
            <div>
              <p class="bag__stage-eyebrow">{{ t('bag.index.stage.current') }}</p>
              <h2 class="bag__stage-title">{{ localized(stage.title) }}</h2>
            </div>
            <StatusBadge :status="stage.status" />
          </div>
          <p v-if="stage.description" class="bag__stage-desc">{{ localized(stage.description) }}</p>
        </Card>

        <router-link class="bag__comparison-link" :to="{ name: ROUTE_NAMES.BAG_COMPARISON }">
          <span class="bag__comparison-link-body">
            <i class="fa-solid fa-chart-column bag__comparison-link-icon" aria-hidden="true"></i>
            <span>
              <span class="bag__comparison-link-title">{{ t('bag.index.comparison.title') }}</span>
              <span class="bag__comparison-link-desc">{{ t('bag.index.comparison.desc') }}</span>
            </span>
          </span>
          <i class="fa-solid fa-arrow-right bag__comparison-link-arrow" aria-hidden="true"></i>
        </router-link>

        <h2 class="bag__section-title">{{ t('bag.index.openGaps.title') }}</h2>

        <div class="bag__how" role="note">
          <i class="fa-solid fa-circle-info bag__how-icon" aria-hidden="true"></i>
          <div>
            <p class="bag__how-title">{{ t('bag.index.howGaps.title') }}</p>
            <p class="bag__how-body">{{ t('bag.index.howGaps.body') }}</p>
          </div>
        </div>

        <EmptyState
          v-if="!openGaps.length"
          icon="fa-solid fa-circle-check"
          :title="t('bag.index.openGaps.emptyTitle')"
          :message="t('bag.index.openGaps.emptyMessage')"
        />

        <ul v-else class="bag__gaps">
          <li v-for="gap in openGaps" :key="gap.id" class="bag__gap">
            <GapCard :gap="gap" :localized="localized" :downloadable="true" />
          </li>
        </ul>

        <section v-if="helperFiles.length" class="bag__helpers">
          <h2 class="bag__section-title">{{ t('bag.index.helpers.title') }}</h2>
          <p class="bag__helpers-lead">{{ t('bag.index.helpers.lead') }}</p>
          <ul class="bag__helper-list">
            <li v-for="file in helperFiles" :key="file.id" class="bag__helper">
              <i class="fa-solid fa-file-arrow-down bag__helper-icon" aria-hidden="true"></i>
              <a class="bag__helper-link" :href="file.url" :download="file.fileName">
                {{ localized(file.name) }}
              </a>
              <span class="bag__helper-size">
                {{ t('bag.gap.fileSize', { size: n(file.sizeKb, 'default') }) }}
              </span>
            </li>
          </ul>
        </section>
        </template>

        <section v-if="history.length" class="bag__history" aria-labelledby="bag-history-title">
          <h2 id="bag-history-title" class="bag__section-title">{{ t('bag.index.history.title') }}</h2>
          <p class="bag__history-lead">{{ t('bag.index.history.lead') }}</p>
          <ul class="bag__history-list">
            <li v-for="kit in history" :key="kit.id" class="bag__history-item">
              <div class="bag__history-head">
                <span class="bag__history-name">{{ localized(kit.title) }}</span>
                <StatusBadge :status="kit.status" />
              </div>
              <div class="bag__history-scores">
                <span class="bag__history-score">{{ t('bag.index.history.before', { score: n(kit.referenceScorePercent, 'default') }) }}</span>
                <i class="fa-solid fa-arrow-right-long bag__history-arrow" aria-hidden="true"></i>
                <span class="bag__history-score bag__history-score--after">{{ t('bag.index.history.after', { score: n(kit.reassessedScorePercent, 'default') }) }}</span>
                <span class="bag__history-gain">{{ t('bag.index.history.gain', { points: n(kit.improvementPoints, 'default') }) }}</span>
              </div>
            </li>
          </ul>
        </section>
      </template>
    </template>
  </section>
</template>
