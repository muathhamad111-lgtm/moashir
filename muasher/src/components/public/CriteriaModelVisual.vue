<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useModelCriteria } from '@/composables/useModelCriteria'
import { formatPadded } from '@/utils/formatNumber'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { n, t } = useI18n()
const { enablers, creativity, results, status, load, localized } = useModelCriteria()

const bands = computed(() =>
  [
    { key: 'enablers', icon: 'layers', items: enablers.value },
    { key: 'creativity', icon: 'lightbulb', items: creativity.value },
    { key: 'results', icon: 'chart-bar', items: results.value },
  ].filter((band) => band.items.length),
)

function padded(value) {
  return formatPadded(n, value)
}
</script>

<template>
  <div class="criteria-model">
    <div v-if="status === 'loading'" class="criteria-model__loading">
      <Skeleton variant="block" :count="2" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="t('public.model.error')" @retry="load" />

    <EmptyState
      v-else-if="status === 'empty'"
      :title="t('public.notPublished.title')"
      :message="t('public.notPublished.message')"
      icon="diagram"
    />

    <div v-else class="criteria-model__diagram">
      <template v-for="(band, index) in bands" :key="band.key">
        <section class="criteria-model__band" :class="`criteria-model__band--${band.key}`">
          <header v-reveal class="criteria-model__band-head">
            <span class="criteria-model__band-icon" aria-hidden="true"
              ><AppIcon :name="band.icon"
            /></span>
            <span class="criteria-model__band-text">
              <h3 class="criteria-model__band-title">{{ t(`public.model.${band.key}`) }}</h3>
              <span class="criteria-model__band-hint">{{ t(`public.model.${band.key}Hint`) }}</span>
            </span>
            <bdi
              class="criteria-model__band-count"
              dir="ltr"
              :title="t('public.home.model.count', { count: band.items.length })"
              >{{ padded(band.items.length) }}</bdi
            >
          </header>
          <ul v-reveal="{ stagger: true }" class="criteria-model__grid">
            <li
              v-for="criterion in band.items"
              :key="criterion.id"
              class="criteria-model__cell"
              :class="`criteria-model__cell--${band.key}`"
            >
              <bdi class="criteria-model__order" dir="ltr" aria-hidden="true">{{
                padded(criterion.order)
              }}</bdi>
              <span class="criteria-model__name">{{ localized(criterion.name) }}</span>
            </li>
          </ul>
        </section>
        <div
          v-if="index < bands.length - 1"
          v-reveal
          class="criteria-model__connector"
          aria-hidden="true"
        >
          <span class="criteria-model__line"></span>
          <span class="criteria-model__arrow">
            <AppIcon name="arrow-right" />
          </span>
        </div>
      </template>
    </div>
  </div>
</template>
