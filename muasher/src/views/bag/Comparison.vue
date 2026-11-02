<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import ReassessmentRow from '@/components/bag/ReassessmentRow.vue'
import { useBagReassessment } from '@/composables/useBag'

const { t, n } = useI18n()

const {
  status,
  errorMessage,
  overall,
  reference,
  reassessment,
  kitCriteria,
  otherCriteria,
  localized,
  load,
} = useBagReassessment()

useHead({ title: () => t('bag.comparison.title') })

onMounted(load)
</script>

<template>
  <section class="bag-comparison">
    <div v-if="status === 'loading'" class="bag-comparison__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="5" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <template v-else>
      <header class="bag-comparison__header">
        <div>
          <p class="bag-comparison__eyebrow">{{ t('bag.comparison.eyebrow') }}</p>
          <h1 class="bag-comparison__title">{{ t('bag.comparison.title') }}</h1>
        </div>
      </header>

      <p class="bag-comparison__intro">{{ t('bag.comparison.intro') }}</p>

      <p v-if="reference && reassessment" class="bag-comparison__versions">
        <i class="fa-solid fa-lock bag-comparison__versions-icon" aria-hidden="true"></i>
        {{
          t('bag.comparison.versions', {
            refVersion: n(reference.version, 'id'),
            newVersion: n(reassessment.version, 'id'),
          })
        }}
      </p>

      <Card v-if="overall" class="bag-comparison__overall">
        <div class="bag-comparison__overall-scores">
          <div class="bag-comparison__overall-score">
            <span class="bag-comparison__overall-label">{{ t('bag.comparison.overallBefore') }}</span>
            <span class="bag-comparison__overall-value">
              {{ t('bag.comparison.percent', { value: n(overall.beforePercent, 'default') }) }}
            </span>
          </div>
          <i class="fa-solid fa-arrow-right-long bag-comparison__overall-arrow" aria-hidden="true"></i>
          <div class="bag-comparison__overall-score">
            <span class="bag-comparison__overall-label">{{ t('bag.comparison.overallAfter') }}</span>
            <span class="bag-comparison__overall-value bag-comparison__overall-value--after">
              {{ t('bag.comparison.percent', { value: n(overall.afterPercent, 'default') }) }}
            </span>
          </div>
          <div class="bag-comparison__overall-score">
            <span class="bag-comparison__overall-label">{{ t('bag.comparison.overallDiff') }}</span>
            <span class="bag-comparison__overall-gain">
              {{ t('bag.comparison.points', { value: n(overall.diffPercent, 'default') }) }}
            </span>
          </div>
        </div>

        <ul class="bag-comparison__counts">
          <li class="bag-comparison__count">
            <span class="bag-comparison__count-value">{{ n(overall.closed, 'default') }}</span>
            <span class="bag-comparison__count-label">{{ t('status.closed') }}</span>
          </li>
          <li class="bag-comparison__count">
            <span class="bag-comparison__count-value">{{ n(overall.improved, 'default') }}</span>
            <span class="bag-comparison__count-label">{{ t('status.partially_improved') }}</span>
          </li>
          <li class="bag-comparison__count">
            <span class="bag-comparison__count-value">{{ n(overall.stable, 'default') }}</span>
            <span class="bag-comparison__count-label">{{ t('status.stable') }}</span>
          </li>
          <li class="bag-comparison__count">
            <span class="bag-comparison__count-value">{{ n(overall.regressed, 'default') }}</span>
            <span class="bag-comparison__count-label">{{ t('status.regressed') }}</span>
          </li>
        </ul>
      </Card>

      <section v-if="kitCriteria.length" class="bag-comparison__group" aria-labelledby="bag-cmp-kit">
        <h2 id="bag-cmp-kit" class="bag-comparison__group-title">{{ t('bag.comparison.kitScope.title') }}</h2>
        <p class="bag-comparison__group-lead">{{ t('bag.comparison.kitScope.lead') }}</p>
        <ul class="bag-comparison__rows">
          <li v-for="row in kitCriteria" :key="row.subCriterionId" class="bag-comparison__row">
            <ReassessmentRow :row="row" :localized="localized" />
          </li>
        </ul>
      </section>

      <section v-if="otherCriteria.length" class="bag-comparison__group" aria-labelledby="bag-cmp-rest">
        <h2 id="bag-cmp-rest" class="bag-comparison__group-title">{{ t('bag.comparison.rest.title') }}</h2>
        <p class="bag-comparison__group-lead">{{ t('bag.comparison.rest.lead') }}</p>
        <ul class="bag-comparison__rows">
          <li v-for="row in otherCriteria" :key="row.subCriterionId" class="bag-comparison__row">
            <ReassessmentRow :row="row" :localized="localized" />
          </li>
        </ul>
      </section>
    </template>
  </section>
</template>
