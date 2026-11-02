<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  actions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  localized: { type: Function, required: true },
})

const { t, n } = useI18n()

const SEVERITIES = ['danger', 'warning', 'info', 'success']

function severityOf(action) {
  return SEVERITIES.includes(action.severity) ? action.severity : 'info'
}

function actionText(action) {
  if (action.text) return props.localized(action.text)
  if (action.labelKey) return t(action.labelKey)
  return ''
}

function actionCount(action) {
  if (action.count === null || action.count === undefined) return ''
  return formatNumber(n, action.count, 'default')
}

const hasActions = computed(() => props.actions.length > 0)
</script>

<template>
  <Card class="action-center">
    <template #header>
      <div class="action-center__head">
        <span class="action-center__icon" aria-hidden="true">
          <i class="fa-solid fa-list-check"></i>
        </span>
        <div class="action-center__heading">
          <h2 class="action-center__title">{{ t('dashboard.actionCenter.title') }}</h2>
          <p class="action-center__subtitle">{{ t('dashboard.actionCenter.subtitle') }}</p>
        </div>
        <span v-if="hasActions && !loading" class="action-center__count">
          {{ formatNumber(n, actions.length, 'default') }}
        </span>
      </div>
    </template>

    <div v-if="loading" class="action-center__loading">
      <Skeleton variant="block" :count="3" />
    </div>

    <EmptyState
      v-else-if="!hasActions"
      icon="fa-solid fa-circle-check"
      :title="t('dashboard.actionCenter.emptyTitle')"
      :message="t('dashboard.actionCenter.emptyMessage')"
    />

    <ul v-else class="action-center__list">
      <li
        v-for="action in actions"
        :key="action.id"
        class="action-center__item"
        :class="`action-center__item--${severityOf(action)}`"
      >
        <router-link class="action-center__link" :to="{ name: action.to }">
          <span class="action-center__item-icon" aria-hidden="true">
            <i :class="action.icon || 'fa-solid fa-circle-exclamation'"></i>
          </span>
          <span class="action-center__item-text">{{ actionText(action) }}</span>
          <span v-if="action.source" class="action-center__source">
            {{ t(`dashboard.boards.${action.source}`) }}
          </span>
          <span
            v-if="actionCount(action)"
            class="action-center__item-count"
            :aria-label="t('dashboard.actionCenter.countLabel', { count: actionCount(action) })"
          >
            {{ actionCount(action) }}
          </span>
          <i
            class="action-center__chevron fa-solid fa-chevron-right u-flip-in-rtl"
            aria-hidden="true"
          ></i>
        </router-link>
      </li>
    </ul>
  </Card>
</template>
