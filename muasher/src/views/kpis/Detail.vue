<script setup>
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import { useKpiDetail } from '@/composables/useKpiDetail'
import { KPI_STATUS_VARIANTS } from '@/constants/kpiCategories'
import { ROUTE_NAMES } from '@/constants/routeNames'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'

const { t, n } = useI18n()
const route = useRoute()
const router = useRouter()

const { kpi, status, load, localized } = useKpiDetail()

function fmt(value) {
  if (value === null || value === undefined || value === '') return ''
  if (typeof value !== 'number') return value
  const formatted = n(value, 'default')
  return formatted === '' ? String(value) : formatted
}

const heroName = computed(() => (kpi.value ? localized(kpi.value.name) : ''))

useHead({
  title: () =>
    heroName.value ? `${heroName.value} — ${t('kpis.page.title')}` : t('kpis.detail.metaTitle'),
  meta: [
    {
      name: 'description',
      content: () => (kpi.value ? localized(kpi.value.description) : t('kpis.page.subtitle')),
    },
  ],
})

const statusVariant = computed(() =>
  kpi.value ? (KPI_STATUS_VARIANTS[kpi.value.status] ?? 'muted') : 'muted',
)

const statusLabel = computed(() =>
  kpi.value?.status ? t(`kpis.statuses.${kpi.value.status}`) : '',
)

const categoryLabel = computed(() =>
  kpi.value?.category ? t(`kpis.categories.${kpi.value.category}`) : '',
)

const progress = computed(() => {
  if (!kpi.value) return null
  const target = Number(kpi.value.target) || 0
  const value = Number(kpi.value.value) || 0
  if (!target) return null
  return Math.min(100, Math.round((value / target) * 100))
})

const facts = computed(() => {
  if (!kpi.value) return []
  const unit = localized(kpi.value.unit)
  const entries = []
  if (kpi.value.target) {
    entries.push({ key: 'target', label: t('kpis.detail.target'), value: `${fmt(kpi.value.target)} ${unit}` })
  }
  if (kpi.value.baseline !== null && kpi.value.baseline !== undefined) {
    entries.push({
      key: 'baseline',
      label: t('kpis.detail.baseline'),
      value: `${fmt(kpi.value.baseline)} ${unit}`,
    })
  }
  if (categoryLabel.value) {
    entries.push({ key: 'category', label: t('kpis.detail.category'), value: categoryLabel.value })
  }
  if (kpi.value.lastUpdate) {
    entries.push({ key: 'lastUpdate', label: t('kpis.detail.lastUpdate'), value: kpi.value.lastUpdate })
  }
  if (localized(kpi.value.owner)) {
    entries.push({ key: 'owner', label: t('kpis.detail.owner'), value: localized(kpi.value.owner) })
  }
  if (localized(kpi.value.dataSource)) {
    entries.push({
      key: 'dataSource',
      label: t('kpis.detail.dataSource'),
      value: localized(kpi.value.dataSource),
    })
  }
  return entries
})

function goToList() {
  router.push({ name: ROUTE_NAMES.PUBLIC_KPIS })
}

function openExternal() {
  if (kpi.value?.externalLink) {
    window.open(kpi.value.externalLink, '_blank', 'noopener')
  }
}

function reload() {
  load(route.params.slug)
}

onMounted(reload)

watch(
  () => route.params.slug,
  (slug, previous) => {
    if (slug && slug !== previous) load(slug)
  },
)
</script>

<template>
  <div class="kpi-detail">
    <div class="kpi-detail__container">
      <nav class="kpi-detail__breadcrumb" :aria-label="$t('kpis.detail.breadcrumbLabel')">
        <RouterLink :to="{ name: ROUTE_NAMES.PUBLIC_KPIS }" class="kpi-detail__crumb">
          <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
          {{ $t('kpis.page.title') }}
        </RouterLink>
        <span v-if="heroName" class="kpi-detail__crumb-sep" aria-hidden="true">/</span>
        <span v-if="heroName" class="kpi-detail__crumb kpi-detail__crumb--current" aria-current="page">
          {{ heroName }}
        </span>
      </nav>

      <div v-if="status === 'loading'" class="kpi-detail__skeleton" aria-hidden="true">
        <Skeleton variant="block" />
        <div class="kpi-detail__skeleton-grid">
          <Skeleton v-for="i in 4" :key="i" variant="block" />
        </div>
      </div>

      <ErrorState
        v-else-if="status === 'error'"
        :message="$t('kpis.detail.error')"
        @retry="reload"
      />

      <EmptyState
        v-else-if="status === 'not-found'"
        branded
        :title="$t('kpis.detail.notFound.title')"
        :message="$t('kpis.detail.notFound.message')"
        :illustration-alt="$t('kpis.detail.notFound.title')"
      >
        <template #action>
          <BaseButton variant="primary" icon="fa-solid fa-arrow-right-long" @click="goToList">
            {{ $t('kpis.detail.backToList') }}
          </BaseButton>
        </template>
      </EmptyState>

      <article v-else-if="kpi" class="kpi-detail__body" :class="`kpi-detail__body--${kpi.cardColor || kpi.color || 'primary'}`">
        <div class="kpi-detail__main">
          <header class="kpi-detail__header">
            <span class="kpi-detail__icon" aria-hidden="true">
              <i :class="kpi.icon || 'fa-solid fa-chart-column'"></i>
            </span>
            <div class="kpi-detail__heading">
              <Badge v-if="statusLabel" :variant="statusVariant" class="kpi-detail__status">
                {{ statusLabel }}
              </Badge>
              <h1 class="kpi-detail__title">{{ heroName }}</h1>
            </div>
          </header>

          <p v-if="localized(kpi.description)" class="kpi-detail__description">
            {{ localized(kpi.description) }}
          </p>

          <div class="kpi-detail__value-block">
            <p class="kpi-detail__value">
              <span class="kpi-detail__number">{{ fmt(kpi.value) }}</span>
              <span class="kpi-detail__unit">{{ localized(kpi.unit) }}</span>
            </p>
            <div v-if="progress !== null" class="kpi-detail__progress">
              <div
                class="kpi-detail__meter"
                role="progressbar"
                :aria-valuenow="progress"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="$t('kpis.detail.progressLabel')"
              >
                <span
                  class="kpi-detail__meter-fill"
                  :style="{ '--kpi-progress': `${progress}%` }"
                ></span>
              </div>
              <span class="kpi-detail__progress-label">
                {{ $t('kpis.detail.progressValue', { value: fmt(progress) }) }}
              </span>
            </div>
          </div>

          <dl v-if="facts.length" class="kpi-detail__facts">
            <div v-for="fact in facts" :key="fact.key" class="kpi-detail__fact">
              <dt class="kpi-detail__fact-label">{{ fact.label }}</dt>
              <dd class="kpi-detail__fact-value">{{ fact.value }}</dd>
            </div>
          </dl>

          <section v-if="localized(kpi.calcMethod)" class="kpi-detail__section">
            <h2 class="kpi-detail__section-title">{{ $t('kpis.detail.calcMethod') }}</h2>
            <p class="kpi-detail__section-text">{{ localized(kpi.calcMethod) }}</p>
          </section>

          <section v-if="localized(kpi.notes)" class="kpi-detail__section">
            <h2 class="kpi-detail__section-title">{{ $t('kpis.detail.notes') }}</h2>
            <p class="kpi-detail__section-text">{{ localized(kpi.notes) }}</p>
          </section>

          <div class="kpi-detail__actions">
            <BaseButton variant="secondary" icon="fa-solid fa-arrow-right-long" @click="goToList">
              {{ $t('kpis.detail.backToList') }}
            </BaseButton>
            <BaseButton
              v-if="kpi.externalLink"
              variant="primary"
              icon="fa-solid fa-arrow-up-right-from-square"
              @click="openExternal"
            >
              {{ $t('kpis.detail.externalLink') }}
            </BaseButton>
          </div>
        </div>

        <aside class="kpi-detail__aside">
          <IllustrationSlot
            class="kpi-detail__illustration"
            :alt="heroName"
            variant="default"
          />
        </aside>
      </article>
    </div>
  </div>
</template>
