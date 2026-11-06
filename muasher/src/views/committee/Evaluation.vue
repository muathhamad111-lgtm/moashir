<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import CommitteeWorkspace from '@/components/committee/CommitteeWorkspace.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const route = useRoute()
const assignmentId = computed(() => Number(route.params.assignment))

useHead({ title: () => t('award.evaluation.title') })
</script>

<template>
  <section class="review-page">
    <header class="review-page__header">
      <RouterLink class="request-detail__back" :to="{ name: ROUTE_NAMES.AWARD_COMMITTEE }">
        <AppIcon name="arrow-left" />
        {{ t('award.evaluation.back') }}
      </RouterLink>
      <div class="review-page__title-row">
        <div>
          <p class="review-page__eyebrow">{{ t('award.work.eyebrow') }}</p>
          <h1 class="review-page__title">{{ t('award.evaluation.title') }}</h1>
        </div>
      </div>
    </header>
    <CommitteeWorkspace :key="assignmentId" :assignment-id="assignmentId" />
  </section>
</template>
