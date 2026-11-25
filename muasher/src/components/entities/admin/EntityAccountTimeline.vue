<script setup>
import { onMounted, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import { useEntityAccountTimeline } from '@/composables/useEntityAccountTimeline'

const props = defineProps({
  entityId: { type: [Number, String], required: true },
})

const { t } = useI18n()
const { items, status, pagination, load, goTo } = useEntityAccountTimeline(toRef(props, 'entityId'))

onMounted(load)
</script>

<template>
  <Card :title="t('entities.record.timeline.title')" class="entity-timeline">
    <Skeleton v-if="status === 'loading'" variant="text" :count="5" />
    <ErrorState
      v-else-if="status === 'error'"
      :message="t('entities.record.timeline.loadFailed')"
      @retry="load"
    />
    <EmptyState v-else-if="status === 'empty'" :title="t('entities.record.timeline.empty')" />
    <ol v-else class="entity-timeline__list">
      <li
        v-for="item in items"
        :key="item.id"
        class="entity-timeline__item"
        :class="[
          `entity-timeline__item--${item.isChange ? 'change' : 'account'}`,
          `entity-timeline__item--${item.kind}`,
        ]"
        :data-event="item.kind"
      >
        <span class="entity-timeline__dot" aria-hidden="true">
          <AppIcon :name="item.isChange ? 'pen-line' : 'clock'" />
        </span>
        <div class="entity-timeline__body">
          <p class="entity-timeline__head">
            <strong>{{ item.title }}</strong>
            <time :datetime="item.time">{{ item.timeLabel }}</time>
          </p>
          <p v-if="item.transition" class="entity-timeline__change">
            <span :dir="item.transition.ltr ? 'ltr' : 'auto'" class="entity-timeline__before">{{
              item.transition.before
            }}</span>
            <AppIcon name="arrow-left" class="entity-timeline__arrow" />
            <span :dir="item.transition.ltr ? 'ltr' : 'auto'" class="entity-timeline__after">{{
              item.transition.after
            }}</span>
          </p>
          <p v-if="item.detail" class="entity-timeline__detail" dir="auto">{{ item.detail }}</p>
          <p v-if="item.actor" class="entity-timeline__actor">{{ item.actor }}</p>
        </div>
      </li>
    </ol>
    <Pagination
      v-if="pagination.lastPage > 1"
      :page="pagination.page"
      :last-page="pagination.lastPage"
      @update:page="goTo"
    />
  </Card>
</template>
