<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import AssessmentHistoryItem from '@/components/assessment/AssessmentHistoryItem.vue'
import { useAssessmentHistory } from '@/composables/useAssessmentHistory'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermissions()
const { status, errorMessage, items, pagination, load, goToPage } = useAssessmentHistory()

function startSelf() {
  router.push({ name: ROUTE_NAMES.ASSESSMENT_SELF })
}

onMounted(load)
</script>

<template>
  <section class="history">
    <header class="history__header">
      <div>
        <p class="history__eyebrow">{{ t('assessment.history.eyebrow') }}</p>
        <h1 class="history__title">{{ t('assessment.history.title') }}</h1>
        <p class="history__subtitle">{{ t('assessment.history.subtitle') }}</p>
      </div>
    </header>

    <div v-if="status === 'loading'" class="history__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <EmptyState
      v-else-if="status === 'empty'"
      icon="fa-regular fa-clipboard"
      :title="t('assessment.history.empty.title')"
      :message="t('assessment.history.empty.message')"
    >
      <template v-if="can(PERMISSIONS.ASSESSMENT_SELF_MANAGE)" #action>
        <BaseButton variant="primary" icon="fa-solid fa-play" @click="startSelf">
          {{ t('assessment.history.empty.cta') }}
        </BaseButton>
      </template>
    </EmptyState>

    <template v-else>
      <p class="history__count" role="status">
        {{ t('assessment.history.entries', { count: pagination.total }, pagination.total) }}
      </p>
      <ul class="history__list">
        <AssessmentHistoryItem v-for="item in items" :key="item.id" :item="item" />
      </ul>
      <Pagination
        v-if="pagination.lastPage > 1"
        :page="pagination.page"
        :last-page="pagination.lastPage"
        @update:page="goToPage"
      />
    </template>
  </section>
</template>
