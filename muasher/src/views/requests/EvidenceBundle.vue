<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import { useEvidenceBundleStatus } from '@/composables/useEvidenceBundle'
import { BUNDLE_STATUSES } from '@/constants/accredited'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatFileSize } from '@/utils/payments/amountFormat'

const VARIANTS = {
  [BUNDLE_STATUSES.PREPARING]: 'warning',
  [BUNDLE_STATUSES.READY]: 'success',
  [BUNDLE_STATUSES.FAILED]: 'danger',
  [BUNDLE_STATUSES.EXPIRED]: 'muted',
}

const { t, locale } = useI18n()
const route = useRoute()
const assessmentId = computed(() => Number(route.params.id))
const bundleId = computed(() => String(route.params.bundle))
const { status, errorMessage, bundle, downloading, load, download } = useEvidenceBundleStatus(
  assessmentId,
  bundleId,
)

useHead({ title: () => t('requests.bundle.title') })

onMounted(load)
</script>

<template>
  <section class="bundle-page">
    <header class="bundle-page__header">
      <RouterLink
        class="request-detail__back"
        :to="{ name: ROUTE_NAMES.REQUESTS_DETAIL, params: { id: assessmentId } }"
      >
        <i class="fa-solid fa-arrow-left-long u-flip-in-rtl" aria-hidden="true"></i>
        {{ t('review.backToRequest') }}
      </RouterLink>
      <h1 class="bundle-page__title">{{ t('requests.bundle.title') }}</h1>
    </header>
    <Skeleton v-if="status === 'loading'" variant="block" />
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="fa-regular fa-folder-open"
      :title="t('requests.bundle.missingTitle')"
      :message="t('requests.bundle.missingMessage')"
    />
    <Card v-else-if="bundle" class="bundle-card">
      <div class="bundle-card__head" aria-live="polite">
        <Badge :variant="VARIANTS[bundle.status] ?? 'muted'">
          <i
            v-if="bundle.status === BUNDLE_STATUSES.PREPARING"
            class="fa-solid fa-spinner fa-spin"
            aria-hidden="true"
          ></i>
          {{ t(`requests.bundle.statuses.${bundle.status}`) }}
        </Badge>
        <span class="bundle-card__meta">{{
          t(
            'requests.bundle.files',
            { count: bundle.fileCount, size: formatFileSize(bundle.sizeBytes, locale) },
            bundle.fileCount,
          )
        }}</span>
      </div>
      <p v-if="bundle.status === BUNDLE_STATUSES.PREPARING" class="bundle-card__note" role="note">
        <i class="fa-solid fa-bell" aria-hidden="true"></i>
        {{ t('requests.bundle.notice') }}
      </p>
      <dl class="bundle-card__facts">
        <div>
          <dt>{{ t('requests.bundle.requestedAt') }}</dt>
          <dd><DateStamp :value="bundle.requestedAt" /></dd>
        </div>
        <div v-if="bundle.expiresAt">
          <dt>{{ t('requests.bundle.expiresAt') }}</dt>
          <dd><DateStamp :value="bundle.expiresAt" /></dd>
        </div>
      </dl>
      <BaseButton
        v-if="bundle.isDownloadable"
        variant="primary"
        icon="fa-solid fa-download"
        :loading="Boolean(downloading[bundle.id])"
        @click="download"
      >
        {{ t('requests.bundle.download') }}
      </BaseButton>
    </Card>
  </section>
</template>
