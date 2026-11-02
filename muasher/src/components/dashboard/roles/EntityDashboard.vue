<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { withLatinNumerals } from '@/utils/numerals'
import { useDashboard } from '@/composables/useDashboard'
import { useNotifications } from '@/composables/useNotifications'
import { useAssessmentHistory } from '@/composables/useAssessmentHistory'
import { useEntityStore } from '@/stores/entity'
import { ROUTE_NAMES } from '@/constants/routeNames'
import RoleDashboardShell from '@/components/dashboard/RoleDashboardShell.vue'
import ProgressGauge from '@/components/dashboard/ProgressGauge.vue'
import MiniBarChart from '@/components/dashboard/MiniBarChart.vue'
import DashboardPathCard from '@/components/dashboard/DashboardPathCard.vue'
import ProfileCompletionPrompt from '@/components/entities/ProfileCompletionPrompt.vue'
import NotificationPanel from '@/components/notifications/NotificationPanel.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'

const { t, n, locale } = useI18n()
const router = useRouter()
const entityStore = useEntityStore()
const { summary, actions, status, errorMessage, load, localized } = useDashboard('entity')

const {
  items: notificationItems,
  unreadCount,
  status: notificationsStatus,
  load: reloadNotifications,
  markRead,
  markAllRead,
} = useNotifications({ perPage: 5 })

const {
  status: historyStatus,
  errorMessage: historyError,
  items: historyItems,
  load: reloadHistory,
} = useAssessmentHistory()

const recentAssessments = computed(() => historyItems.value.slice(0, 3))

const profileStatus = ref('loading')

async function loadProfile() {
  profileStatus.value = 'loading'
  try {
    await entityStore.loadProfile()
    profileStatus.value = 'success'
  } catch {
    profileStatus.value = 'error'
  }
}

onMounted(() => {
  loadProfile()
  reloadHistory()
})

const paths = [
  { key: 'self', icon: 'fa-solid fa-gauge-high', name: ROUTE_NAMES.ASSESSMENT_SELF },
  { key: 'accredited', icon: 'fa-solid fa-certificate', name: ROUTE_NAMES.ASSESSMENT_ACCREDITED },
  { key: 'advanced', icon: 'fa-solid fa-trophy', name: ROUTE_NAMES.ASSESSMENT_ADVANCED },
]

function fmtDate(value, style = 'long') {
  if (!value) return ''
  const lang = withLatinNumerals(locale.value === 'ar' ? 'ar-SA' : 'en-GB')
  return new Intl.DateTimeFormat(lang, { dateStyle: style }).format(new Date(value))
}

function assessmentPathLabel(item) {
  return t(`assessment.paths.${item.path}.title`)
}

function openAssessment(item) {
  if (item.path === 'self') {
    router.push({ name: ROUTE_NAMES.ASSESSMENT_SELF_RESULT, params: { id: item.id } })
  } else {
    goToHistory()
  }
}

function onOpenNotification(item) {
  markRead(item.id)
  if (item.link) router.push(item.link)
}

function goToProfile() {
  router.push({ name: ROUTE_NAMES.ENTITY_PROFILE })
}

function goToHistory() {
  router.push({ name: ROUTE_NAMES.ASSESSMENT_HISTORY })
}
</script>

<template>
  <div class="entity-dashboard">
    <ProfileCompletionPrompt
      v-if="profileStatus === 'success' && !entityStore.profileCompleted"
      @complete="goToProfile"
    />

    <div class="entity-dashboard__layout">
      <div class="entity-dashboard__main">
        <RoleDashboardShell
          :status="status"
          :actions="actions"
          :error-message="errorMessage"
          :localized="localized"
          @retry="load"
        >
          <section class="role-dashboard__section" aria-labelledby="entity-status">
            <h2 id="entity-status" class="role-dashboard__section-title">
              {{ t('dashboard.entity.statusTitle') }}
            </h2>
            <div class="role-dashboard__grid-3">
              <Card :title="t('dashboard.entity.scoreTitle')">
                <div class="role-dashboard__gauge-wrap">
                  <ProgressGauge
                    :value="summary.score.value"
                    :label="localized(summary.score.level)"
                    color="primary"
                  />
                </div>
              </Card>

              <Card :title="t('dashboard.entity.subscriptionTitle')">
                <div class="role-dashboard__panel">
                  <Badge :variant="summary.subscription.status === 'active' ? 'success' : 'danger'">
                    {{ t(`dashboard.entity.subscription.${summary.subscription.status}`) }}
                  </Badge>
                  <p class="role-dashboard__panel-note">
                    {{ t('dashboard.entity.subscription.remaining', { days: n(summary.subscription.daysRemaining, 'default') }) }}
                  </p>
                  <dl class="role-dashboard__facts">
                    <div class="role-dashboard__fact">
                      <dt class="role-dashboard__fact-label">{{ t('dashboard.entity.maturityStage') }}</dt>
                      <dd class="role-dashboard__fact-value">
                        {{ t('dashboard.entity.stage', { stage: n(summary.maturityStage, 'default') }) }}
                      </dd>
                    </div>
                    <div class="role-dashboard__fact">
                      <dt class="role-dashboard__fact-label">{{ t('dashboard.entity.reassessment') }}</dt>
                      <dd class="role-dashboard__fact-value">{{ fmtDate(summary.reassessmentAt) }}</dd>
                    </div>
                  </dl>
                </div>
              </Card>

              <Card :title="t('dashboard.entity.gapClosureTitle')">
                <div class="role-dashboard__gauge-wrap">
                  <ProgressGauge
                    :value="summary.gapClosureRate"
                    :label="t('dashboard.entity.gapClosureTitle')"
                    color="success"
                  />
                </div>
              </Card>
            </div>
          </section>

          <div class="role-dashboard__two-col">
            <Card :title="t('dashboard.entity.lastAssessment.title')">
              <dl class="role-dashboard__facts">
                <div class="role-dashboard__fact">
                  <dt class="role-dashboard__fact-label">{{ t('dashboard.entity.lastAssessment.date') }}</dt>
                  <dd class="role-dashboard__fact-value">{{ fmtDate(summary.lastAssessment.date, 'medium') }}</dd>
                </div>
                <div class="role-dashboard__fact">
                  <dt class="role-dashboard__fact-label">{{ t('dashboard.entity.lastAssessment.score') }}</dt>
                  <dd class="role-dashboard__fact-value">{{ n(summary.lastAssessment.percent, 'default') }}%</dd>
                </div>
                <div class="role-dashboard__fact">
                  <dt class="role-dashboard__fact-label">{{ t('dashboard.entity.lastAssessment.path') }}</dt>
                  <dd class="role-dashboard__fact-value">
                    {{ t(`dashboard.paths.${summary.lastAssessment.path}.title`) }}
                  </dd>
                </div>
              </dl>
            </Card>

            <Card :title="t('dashboard.entity.bag.title')">
              <dl class="role-dashboard__facts">
                <div class="role-dashboard__fact">
                  <dt class="role-dashboard__fact-label">{{ t('dashboard.entity.bag.current') }}</dt>
                  <dd class="role-dashboard__fact-value">{{ localized(summary.bag.currentStage) }}</dd>
                </div>
                <div class="role-dashboard__fact">
                  <dt class="role-dashboard__fact-label">{{ t('dashboard.entity.bag.next') }}</dt>
                  <dd class="role-dashboard__fact-value">{{ localized(summary.bag.nextStage) }}</dd>
                </div>
                <div class="role-dashboard__fact">
                  <dt class="role-dashboard__fact-label">{{ t('dashboard.entity.bag.opensAt') }}</dt>
                  <dd class="role-dashboard__fact-value">{{ fmtDate(summary.bag.opensAt, 'medium') }}</dd>
                </div>
              </dl>
            </Card>
          </div>

          <Card :title="t('dashboard.entity.criteriaTitle')">
            <MiniBarChart :series="summary.criteria" :localized="localized" color="primary" unit="%" />
          </Card>

          <section class="role-dashboard__section" aria-labelledby="entity-paths">
            <h2 id="entity-paths" class="role-dashboard__section-title">
              {{ t('dashboard.choosePath') }}
            </h2>
            <div class="entity-dashboard__paths">
              <DashboardPathCard
                v-for="path in paths"
                :key="path.key"
                :path-key="path.key"
                :icon="path.icon"
                :to="{ name: path.name }"
              />
            </div>
          </section>

          <Card>
            <template #header>
              <div class="entity-dashboard__card-head">
                <span class="card__title-text">{{ t('dashboard.history.title') }}</span>
                <BaseButton variant="ghost" size="sm" @click="goToHistory">
                  {{ t('dashboard.history.viewAll') }}
                </BaseButton>
              </div>
            </template>
            <div v-if="historyStatus === 'loading'" class="entity-dashboard__history-loading">
              <Skeleton variant="text" :count="3" />
            </div>
            <ErrorState
              v-else-if="historyStatus === 'error'"
              :message="historyError"
              @retry="reloadHistory"
            />
            <EmptyState
              v-else-if="historyStatus === 'empty'"
              icon="fa-regular fa-clipboard"
              :title="t('dashboard.history.emptyTitle')"
              :message="t('dashboard.history.emptyMessage')"
            >
              <template #action>
                <BaseButton
                  variant="primary"
                  icon="fa-solid fa-play"
                  @click="router.push({ name: ROUTE_NAMES.ASSESSMENT_SELF })"
                >
                  {{ t('dashboard.history.startCta') }}
                </BaseButton>
              </template>
            </EmptyState>
            <ul v-else class="entity-dashboard__history">
              <li
                v-for="item in recentAssessments"
                :key="item.id"
                class="entity-dashboard__history-item"
              >
                <div class="entity-dashboard__history-info">
                  <span class="entity-dashboard__history-path">{{ assessmentPathLabel(item) }}</span>
                  <span class="entity-dashboard__history-date">{{ fmtDate(item.createdAt, 'medium') }}</span>
                </div>
                <div class="entity-dashboard__history-meta">
                  <StatusBadge :status="item.status" />
                  <span v-if="item.result" class="entity-dashboard__history-score">
                    {{ t('dashboard.history.scored', { percent: item.result.percent }) }}
                  </span>
                  <BaseButton
                    v-if="item.path === 'self'"
                    variant="ghost"
                    size="sm"
                    icon="fa-solid fa-arrow-right u-flip-in-rtl"
                    icon-only
                    :aria-label="t('assessment.history.viewResult')"
                    @click="openAssessment(item)"
                  />
                </div>
              </li>
            </ul>
          </Card>

          <Card :title="t('dashboard.entity.recommendationsTitle')">
            <ul class="role-dashboard__notes">
              <li v-for="rec in summary.recommendations" :key="rec.id" class="role-dashboard__note">
                <div class="role-dashboard__note-head">
                  <span class="role-dashboard__note-entity">
                    <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                    {{ t('dashboard.entity.recommendationTag') }}
                  </span>
                  <time class="role-dashboard__note-date" :datetime="rec.at">{{ fmtDate(rec.at, 'medium') }}</time>
                </div>
                <p class="role-dashboard__note-text">{{ localized(rec.text) }}</p>
              </li>
            </ul>
          </Card>
        </RoleDashboardShell>
      </div>

      <aside class="entity-dashboard__aside" :aria-label="t('dashboard.notifications.title')">
        <Card :title="t('dashboard.notifications.title')">
          <NotificationPanel
            :items="notificationItems"
            :status="notificationsStatus"
            :unread-count="unreadCount"
            @open="onOpenNotification"
            @mark-all-read="markAllRead"
            @retry="reloadNotifications"
          />
        </Card>
      </aside>
    </div>
  </div>
</template>
