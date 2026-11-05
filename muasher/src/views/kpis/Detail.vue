<script setup>
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useKpiDetail } from '@/composables/useKpiDetail'
import { useKpiFacts } from '@/composables/useKpiFacts'
import { usePageSeo } from '@/composables/usePageSeo'
import { DEFAULT_KPI_COLOR, KPI_COLOR_TOKENS, KPI_STATUS_VARIANTS } from '@/constants/kpis'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { contentImageUrl } from '@/utils/contentImage'
import { formatKpiValue, kpiProgress } from '@/utils/kpiNumber'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'
import KpiHistoryTable from '@/components/kpis/KpiHistoryTable.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t, n } = useI18n()
const route = useRoute()
const router = useRouter()
const { kpi, status, load, localized } = useKpiDetail()
const facts = useKpiFacts(kpi, localized)

const name = computed(() => (kpi.value ? localized(kpi.value.name) : ''))
const unit = computed(() => localized(kpi.value?.unit))
const color = computed(() =>
  KPI_COLOR_TOKENS.includes(kpi.value?.cardColor) ? kpi.value.cardColor : DEFAULT_KPI_COLOR,
)
const statusVariant = computed(() => KPI_STATUS_VARIANTS[kpi.value?.status] ?? 'muted')
const statusLabel = computed(() =>
  kpi.value?.status ? t(`kpis.statuses.${kpi.value.status}`) : '',
)
const progress = computed(() => kpiProgress(kpi.value?.value, kpi.value?.target))
const description = computed(
  () => localized(kpi.value?.fullDescription) || localized(kpi.value?.shortDescription),
)
const image = computed(() => contentImageUrl(kpi.value?.image))

usePageSeo({
  title: () =>
    kpi.value ? localized(kpi.value.seo?.title) || name.value : t('kpis.detail.metaTitle'),
  description: () => localized(kpi.value?.seo?.description),
})

function goToList() {
  router.push({ name: ROUTE_NAMES.PUBLIC_KPIS })
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
      <nav class="kpi-detail__breadcrumb" :aria-label="t('kpis.detail.breadcrumbLabel')">
        <RouterLink :to="{ name: ROUTE_NAMES.PUBLIC_KPIS }" class="kpi-detail__crumb">
          <AppIcon name="chart-line" />
          {{ t('kpis.page.title') }}
        </RouterLink>
        <template v-if="name">
          <span class="kpi-detail__crumb-sep" aria-hidden="true">/</span>
          <span class="kpi-detail__crumb kpi-detail__crumb--current" aria-current="page">{{
            name
          }}</span>
        </template>
      </nav>

      <div v-if="status === 'loading'" class="kpi-detail__skeleton" aria-busy="true">
        <Skeleton variant="block" />
        <div class="kpi-detail__skeleton-grid">
          <Skeleton v-for="i in 4" :key="i" variant="block" />
        </div>
      </div>

      <ErrorState
        v-else-if="status === 'error'"
        :message="t('kpis.detail.error')"
        @retry="reload"
      />

      <EmptyState
        v-else-if="status === 'not-found'"
        branded
        :title="t('kpis.detail.notFound.title')"
        :message="t('kpis.detail.notFound.message')"
        :illustration-alt="t('kpis.detail.notFound.title')"
      >
        <template #action>
          <BaseButton
            variant="primary"
            icon="arrow-left"
            @click="goToList"
          >
            {{ t('kpis.detail.backToList') }}
          </BaseButton>
        </template>
      </EmptyState>

      <article v-else-if="kpi" class="kpi-detail__body" :class="`kpi-detail__body--${color}`">
        <div class="kpi-detail__main">
          <header class="kpi-detail__header">
            <span class="kpi-detail__icon" aria-hidden="true">
              <AppIcon :name="kpi.icon || 'chart-bar'" />
            </span>
            <div class="kpi-detail__heading">
              <Badge v-if="statusLabel" :variant="statusVariant" class="kpi-detail__status">{{
                statusLabel
              }}</Badge>
              <h1 class="kpi-detail__title">{{ name }}</h1>
            </div>
          </header>

          <p v-if="description" class="kpi-detail__description">{{ description }}</p>

          <div class="kpi-detail__value-block">
            <p class="kpi-detail__value">
              <span class="kpi-detail__number">{{ formatKpiValue(n, kpi.value) }}</span>
              <span class="kpi-detail__unit">{{ unit }}</span>
            </p>
            <div v-if="progress !== null" class="kpi-detail__progress">
              <progress
                class="kpi-detail__meter"
                :value="progress"
                max="100"
                :aria-label="t('kpis.detail.progressLabel')"
              >
                {{ progress }}
              </progress>
              <span class="kpi-detail__progress-label">{{
                t('kpis.detail.progressValue', { value: n(progress, 'id') })
              }}</span>
            </div>
          </div>

          <dl v-if="facts.length" class="kpi-detail__facts">
            <div v-for="fact in facts" :key="fact.key" class="kpi-detail__fact">
              <dt class="kpi-detail__fact-label">{{ fact.label }}</dt>
              <dd class="kpi-detail__fact-value">{{ fact.value }}</dd>
            </div>
          </dl>

          <section v-if="localized(kpi.calcMethod)" class="kpi-detail__section">
            <h2 class="kpi-detail__section-title">{{ t('kpis.detail.calcMethod') }}</h2>
            <p class="kpi-detail__section-text">{{ localized(kpi.calcMethod) }}</p>
          </section>

          <section v-if="localized(kpi.notes)" class="kpi-detail__section">
            <h2 class="kpi-detail__section-title">{{ t('kpis.detail.notes') }}</h2>
            <p class="kpi-detail__section-text">{{ localized(kpi.notes) }}</p>
          </section>

          <section v-if="kpi.history?.length" class="kpi-detail__section">
            <KpiHistoryTable
              :history="kpi.history"
              :unit="unit"
              :caption="t('kpis.detail.history')"
            />
          </section>

          <div class="kpi-detail__actions">
            <BaseButton
              variant="secondary"
              icon="arrow-left"
              @click="goToList"
            >
              {{ t('kpis.detail.backToList') }}
            </BaseButton>
            <a
              v-if="kpi.externalLink"
              class="btn btn--primary btn--md"
              :href="kpi.externalLink"
              target="_blank"
              rel="noopener noreferrer"
            >
              <AppIcon name="link" />
              {{ t('kpis.detail.externalLink') }}
            </a>
          </div>
        </div>

        <aside class="kpi-detail__aside">
          <IllustrationSlot
            class="kpi-detail__illustration"
            :src="image || null"
            :alt="name"
            variant="default"
          />
        </aside>
      </article>
    </div>
  </div>
</template>
