<script setup>
import { computed } from 'vue'
import { usePublicHome } from '@/composables/useHomeContent'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'

const { status, load, localized, sectionByKey } = usePublicHome()

const FALLBACK_ITEMS = [
  { key: 'grantor', icon: 'fa-solid fa-hand-holding-dollar' },
  { key: 'supervisory', icon: 'fa-solid fa-building-shield' },
  { key: 'community', icon: 'fa-solid fa-people-group' },
  { key: 'vision', icon: 'fa-solid fa-flag' },
  { key: 'strategic', icon: 'fa-solid fa-chess' },
]

const section = computed(() => sectionByKey('analytics-lenses'))

const lenses = computed(() => {
  const items = section.value?.items
  if (items && items.length) {
    return [...items].sort((a, b) => a.order - b.order)
  }
  return null
})

const ruleNote = computed(() => {
  const current = section.value
  if (!current) return null
  const meta = localized(current.meta?.rule)
  if (meta) return meta
  const subtitle = localized(current.subtitle)
  if (subtitle) return subtitle
  return null
})
</script>

<template>
  <div class="lenses-page">
    <header class="lenses-page__hero">
      <div class="lenses-page__hero-inner">
        <span class="lenses-page__eyebrow">
          <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
          {{ $t('app.name') }}
        </span>
        <h1 class="lenses-page__title">
          {{ section ? localized(section.title) : $t('lenses.title') }}
        </h1>
        <p class="lenses-page__subtitle">
          {{
            section && localized(section.description)
              ? localized(section.description)
              : $t('lenses.lead')
          }}
        </p>
      </div>
    </header>

    <div class="lenses-page__body">
      <div v-if="status === 'loading'" class="lenses-page__grid">
        <Skeleton v-for="i in 5" :key="i" variant="block" />
      </div>

      <ErrorState v-else-if="status === 'error'" :message="$t('lenses.error')" @retry="load" />

      <template v-else>
        <ul v-if="lenses" class="lenses-page__grid">
          <li v-for="lens in lenses" :key="lens.id" class="lens-card">
            <span class="lens-card__icon" aria-hidden="true">
              <i :class="lens.icon || 'fa-solid fa-chart-pie'"></i>
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
          <li v-for="lens in FALLBACK_ITEMS" :key="lens.key" class="lens-card">
            <span class="lens-card__icon" aria-hidden="true">
              <i :class="lens.icon"></i>
            </span>
            <div class="lens-card__body">
              <h2 class="lens-card__title">{{ $t(`lenses.items.${lens.key}`) }}</h2>
            </div>
          </li>
        </ul>

        <aside class="lenses-page__rule" role="note">
          <i class="lenses-page__rule-icon fa-solid fa-shield-halved" aria-hidden="true"></i>
          <div class="lenses-page__rule-body">
            <h2 class="lenses-page__rule-title">{{ $t('lenses.ruleTitle') }}</h2>
            <p class="lenses-page__rule-text">{{ ruleNote || $t('lenses.sourceNote') }}</p>
          </div>
        </aside>
      </template>
    </div>
  </div>
</template>
