<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ACTION_ICONS } from '@/constants/dashboardBoards'
import { formatNumber } from '@/utils/formatNumber'
import { formatRelativeTime } from '@/utils/datetime'
import { isInternalPath } from '@/utils/internalPath'

const props = defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const { t, te, n, locale } = useI18n()

const total = computed(() => props.items.reduce((sum, item) => sum + item.count, 0))

function labelOf(item) {
  const key = `dashboard.actions.${item.key}`
  return te(key) ? t(key) : item.key
}

function severityOf(item) {
  return ACTION_ICONS[item.severity] ? item.severity : 'info'
}

function dueOf(item) {
  return item.dueAt
    ? t('dashboard.actionCenter.due', { time: formatRelativeTime(item.dueAt, locale.value) })
    : ''
}
</script>

<template>
  <Card class="action-center">
    <template #header>
      <div class="action-center__head">
        <span class="action-center__icon" aria-hidden="true"><AppIcon name="list-check" /></span>
        <div class="action-center__heading">
          <h2 class="action-center__title">{{ t('dashboard.actionCenter.title') }}</h2>
          <p class="action-center__subtitle">{{ t('dashboard.actionCenter.subtitle') }}</p>
        </div>
        <span
          v-if="items.length && !loading"
          class="action-center__count"
          :aria-label="t('dashboard.actionCenter.totalLabel', { count: formatNumber(n, total) })"
          >{{ formatNumber(n, total) }}</span
        >
      </div>
    </template>

    <Skeleton v-if="loading" variant="block" :count="3" />
    <EmptyState
      v-else-if="!items.length"
      icon="check-circle"
      :title="t('dashboard.actionCenter.emptyTitle')"
      :message="t('dashboard.actionCenter.emptyMessage')"
    />
    <ul v-else class="action-center__list">
      <li
        v-for="item in items"
        :key="item.key"
        class="action-center__item"
        :class="`action-center__item--${severityOf(item)}`"
        :data-action="item.key"
      >
        <component
          :is="isInternalPath(item.link) ? RouterLink : 'div'"
          class="action-center__link"
          v-bind="isInternalPath(item.link) ? { to: item.link } : {}"
        >
          <span class="action-center__item-icon" aria-hidden="true">
            <AppIcon :name="ACTION_ICONS[severityOf(item)]" />
          </span>
          <span class="action-center__item-text">
            <span>{{ labelOf(item) }}</span>
            <span v-if="dueOf(item)" class="action-center__due">{{ dueOf(item) }}</span>
          </span>
          <span
            class="action-center__item-count"
            :aria-label="
              t('dashboard.actionCenter.countLabel', { count: formatNumber(n, item.count) })
            "
            >{{ formatNumber(n, item.count) }}</span
          >
          <AppIcon
            v-if="isInternalPath(item.link)"
            name="chevron-right"
            class="action-center__chevron"
          />
        </component>
      </li>
    </ul>
  </Card>
</template>
