<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { LENS_FALLBACK, useLensesContent } from '@/composables/useLensesContent'
import { usePageSeo } from '@/composables/usePageSeo'
import { PUBLIC_PAGE_STATES } from '@/constants/cms'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const { state, load, localized, title, lead, rule, lenses } = useLensesContent()

const heroTitle = computed(() => title.value || t('lenses.title'))
const heroLead = computed(() => lead.value || t('lenses.lead'))

usePageSeo({ title: () => title.value, description: () => lead.value })
</script>

<template>
  <div class="lenses-page">
    <header class="lenses-page__hero">
      <div class="lenses-page__hero-inner">
        <span class="lenses-page__eyebrow">
          <AppIcon name="layers" />
          {{ t('app.name') }}
        </span>
        <h1 class="lenses-page__title">{{ heroTitle }}</h1>
        <p class="lenses-page__subtitle">{{ heroLead }}</p>
      </div>
    </header>

    <div class="lenses-page__body">
      <div v-if="state === PUBLIC_PAGE_STATES.LOADING" class="lenses-page__grid">
        <Skeleton v-for="i in 5" :key="i" variant="block" />
      </div>

      <ErrorState
        v-else-if="state === PUBLIC_PAGE_STATES.ERROR"
        :message="t('lenses.error')"
        @retry="load"
      />

      <template v-else>
        <ul v-if="lenses.length" class="lenses-page__grid">
          <li v-for="lens in lenses" :key="lens.id" class="lens-card">
            <span class="lens-card__icon" aria-hidden="true">
              <AppIcon :name="lens.icon || 'pie-chart'" />
            </span>
            <div class="lens-card__body">
              <h2 class="lens-card__title">{{ localized(lens.title) }}</h2>
              <p v-if="localized(lens.description)" class="lens-card__desc">
                {{ localized(lens.description) }}
              </p>
            </div>
          </li>
        </ul>

        <ul v-else class="lenses-page__grid">
          <li v-for="lens in LENS_FALLBACK" :key="lens.key" class="lens-card">
            <span class="lens-card__icon" aria-hidden="true">
              <AppIcon :name="lens.icon" />
            </span>
            <div class="lens-card__body">
              <h2 class="lens-card__title">{{ t(`lenses.items.${lens.key}`) }}</h2>
            </div>
          </li>
        </ul>

        <aside class="lenses-page__rule" role="note">
          <AppIcon name="shield-info" class="lenses-page__rule-icon" />
          <div class="lenses-page__rule-body">
            <h2 class="lenses-page__rule-title">{{ t('lenses.ruleTitle') }}</h2>
            <p class="lenses-page__rule-text">{{ rule || t('lenses.sourceNote') }}</p>
          </div>
        </aside>
      </template>
    </div>
  </div>
</template>
