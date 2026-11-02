<script setup>
import { computed } from 'vue'
import { useModelCriteria } from '@/composables/useModelCriteria'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'

const { enablers, creativity, results, status, load, localized } = useModelCriteria()

const bands = computed(() =>
  [
    {
      key: 'enablers',
      title: 'public.model.enablers',
      hint: 'public.model.enablersHint',
      items: enablers.value,
    },
    {
      key: 'creativity',
      title: 'public.model.creativity',
      hint: 'public.model.creativityHint',
      items: creativity.value,
    },
    {
      key: 'results',
      title: 'public.model.results',
      hint: 'public.model.resultsHint',
      items: results.value,
    },
  ].filter((band) => band.items.length),
)
</script>

<template>
  <div class="criteria-model">
    <div v-if="status === 'loading'" class="criteria-model__loading">
      <Skeleton variant="block" :count="2" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="$t('public.model.error')" @retry="load" />

    <EmptyState
      v-else-if="status === 'empty'"
      :title="$t('public.notPublished.title')"
      :message="$t('public.notPublished.message')"
      icon="fa-solid fa-diagram-project"
    />

    <div v-else class="criteria-model__diagram">
      <template v-for="(band, index) in bands" :key="band.key">
        <section class="criteria-model__band" :class="`criteria-model__band--${band.key}`">
          <header class="criteria-model__band-head">
            <h3 class="criteria-model__band-title">{{ $t(band.title) }}</h3>
            <p class="criteria-model__band-hint">{{ $t(band.hint) }}</p>
          </header>
          <ul class="criteria-model__grid">
            <li
              v-for="criterion in band.items"
              :key="criterion.id"
              class="criteria-model__cell"
              :class="`criteria-model__cell--${band.key}`"
            >
              <span class="criteria-model__order" aria-hidden="true">{{ criterion.order }}</span>
              <span class="criteria-model__name">{{ localized(criterion.name) }}</span>
              <span v-if="criterion.description" class="criteria-model__description">{{
                localized(criterion.description)
              }}</span>
            </li>
          </ul>
        </section>
        <div v-if="index < bands.length - 1" class="criteria-model__arrow" aria-hidden="true">
          <i class="fa-solid fa-arrow-right-long"></i>
        </div>
      </template>
    </div>
  </div>
</template>
