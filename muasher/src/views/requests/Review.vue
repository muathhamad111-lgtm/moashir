<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReviewWorkspace from '@/components/review/ReviewWorkspace.vue'
import { useEvidenceBundleRequest } from '@/composables/useEvidenceBundle'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const route = useRoute()
const { can } = usePermissions()
const assessmentId = computed(() => Number(route.params.id))
const bundles = useEvidenceBundleRequest(assessmentId)

useHead({ title: () => t('review.title') })
</script>

<template>
  <section class="review-page">
    <header class="review-page__header">
      <RouterLink
        class="request-detail__back"
        :to="{ name: ROUTE_NAMES.REQUESTS_DETAIL, params: { id: assessmentId } }"
      >
        <i class="fa-solid fa-arrow-left-long u-flip-in-rtl" aria-hidden="true"></i>
        {{ t('review.backToRequest') }}
      </RouterLink>
      <div class="review-page__title-row">
        <div>
          <p class="review-page__eyebrow">{{ t('requests.index.eyebrow') }}</p>
          <h1 class="review-page__title">{{ t('review.title') }}</h1>
        </div>
        <BaseButton
          v-if="can(PERMISSIONS.EVIDENCE_VIEW)"
          variant="secondary"
          icon="fa-solid fa-file-zipper"
          :loading="bundles.requesting.value"
          @click="bundles.requestBundle"
        >
          {{ t('requests.actions.bundle') }}
        </BaseButton>
      </div>
    </header>
    <ReviewWorkspace :key="assessmentId" :assessment-id="assessmentId" />
  </section>
</template>
