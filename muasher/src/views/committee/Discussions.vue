<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Pagination from '@/components/data-list/Pagination.vue'
import Tabs from '@/components/ui/Tabs.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import DiscussionCard from '@/components/committee/DiscussionCard.vue'
import { useCommitteeDiscussions } from '@/composables/useCommitteeDiscussions'
import { useCountedTabs } from '@/composables/useCountedTabs'
import { DISCUSSION_STATUSES } from '@/constants/committee'

const { t } = useI18n()
const { items, status, errorMessage, load, filters, paginator, setFilter, respond, meta } =
  useCommitteeDiscussions()
const { pagination, setPage } = paginator

useHead({ title: () => t('award.discussion.title') })

// One tab per status with its count, like the other committee pages.
const { tabs, tab } = useCountedTabs({
  values: Object.values(DISCUSSION_STATUSES),
  labelOf: (value) => t(`award.states.discussion.${value}`),
  countOf: (value) => meta.value.statusCounts?.[value],
  counts: computed(() => meta.value.statusCounts),
  current: () => filters.filters.status,
  select: (value) => setFilter('status', value),
})
</script>

<template>
  <section class="cycles-page committee-discussions">
    <header class="cycles-page__header">
      <div>
        <p class="cycles-page__eyebrow">{{ t('award.work.eyebrow') }}</p>
        <h1 class="cycles-page__title">{{ t('award.discussion.title') }}</h1>
        <p class="cycles-page__subtitle">{{ t('award.discussion.subtitle') }}</p>
      </div>
    </header>
    <Tabs v-model="tab" :tabs="tabs">
      <div class="committee-discussions__panel">
        <AssessmentNotice
          variant="info"
          :title="t('award.discussion.anonymousTitle')"
          :message="t('award.discussion.anonymousMessage')"
        />
        <div v-if="status === 'loading'" aria-busy="true"><Skeleton variant="block" /></div>
        <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
        <EmptyState
          v-else-if="status === 'empty'"
          icon="chat"
          :title="t('award.discussion.emptyTitle')"
          :message="t('award.discussion.emptyMessage')"
        />
        <div v-else class="committee-discussions__list">
          <DiscussionCard
            v-for="discussion in items"
            :key="discussion.id"
            :discussion="discussion"
            :respond="respond"
          />
        </div>
        <Pagination
          v-if="status === 'success' && pagination.lastPage > 1"
          :page="pagination.page"
          :last-page="pagination.lastPage"
          @update:page="setPage($event)"
        />
      </div>
    </Tabs>
  </section>
</template>
