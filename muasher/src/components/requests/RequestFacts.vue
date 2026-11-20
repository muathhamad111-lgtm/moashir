<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import OverdueBadge from '@/components/accredited/OverdueBadge.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import LevelBadge from '@/components/level/LevelBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { usePermissions } from '@/composables/usePermissions'
import { localizedText } from '@/utils/localizedText'
import { displayTierOf } from '@/utils/levelTier'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'

// The request's data: the entity it belongs to first (admin review 2026-11-20), then its state and dates.
const props = defineProps({
  detail: { type: Object, required: true },
})

const { t, locale } = useI18n()
const { can } = usePermissions()
const showsPaidFlag = computed(
  () => !props.detail.payment.details && props.detail.path === ASSESSMENT_PATHS.ACCREDITED,
)
const entityRoute = computed(() =>
  props.detail.entity?.id && can(PERMISSIONS.ENTITIES_VIEW)
    ? { name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: props.detail.entity.id } }
    : null,
)
</script>

<template>
  <dl class="request-facts">
    <div v-if="detail.entity" class="request-facts__item request-facts__item--wide">
      <dt>{{ t('requests.columns.entity') }}</dt>
      <dd class="request-facts__entity" data-testid="request-entity">
        <RouterLink v-if="entityRoute" class="request-facts__entity-link" :to="entityRoute">{{
          localizedText(detail.entity.name, locale)
        }}</RouterLink>
        <span v-else>{{ localizedText(detail.entity.name, locale) }}</span>
        <bdi dir="ltr" class="request-facts__reference">{{ detail.entity.reference }}</bdi>
        <LevelBadge
          :tier="displayTierOf(detail.entity)"
          size="s"
          :certificate="detail.entity.lastLevel"
        />
      </dd>
    </div>
    <div v-if="detail.path" class="request-facts__item">
      <dt>{{ t('requests.columns.path') }}</dt>
      <dd><PathBadge :path="detail.path" :label="detail.pathLabel" /></dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('requests.columns.status') }}</dt>
      <dd class="request-facts__badges">
        <StatusBadge :status="detail.status" />
        <Badge v-if="detail.isFrozen" variant="danger"
          ><AppIcon name="snowflake" /> {{ t('accredited.form.frozen') }}</Badge
        >
      </dd>
    </div>
    <div v-if="detail.awardCycle" class="request-facts__item">
      <dt>{{ t('requests.columns.cycle') }}</dt>
      <dd>{{ localizedText(detail.awardCycle.name, locale) }}</dd>
    </div>
    <div v-if="detail.region" class="request-facts__item">
      <dt>{{ t('requests.columns.region') }}</dt>
      <dd>{{ localizedText(detail.region.name, locale) }}</dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('requests.columns.assessor') }}</dt>
      <dd class="request-facts__badges" data-testid="request-assessor">
        <span>{{ detail.assessor?.name ?? t('requests.unassigned') }}</span>
        <Badge v-if="detail.isReassigned" variant="info">{{ t('requests.reassigned') }}</Badge>
      </dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('requests.columns.submittedAt') }}</dt>
      <dd><DateStamp :value="detail.submittedAt" /></dd>
    </div>
    <div class="request-facts__item">
      <dt>{{ t('requests.columns.dueAt') }}</dt>
      <dd class="request-facts__badges">
        <DateStamp :value="detail.dueAt" with-hijri />
        <OverdueBadge v-if="detail.dueAt && !detail.completedAt" :overdue="detail.isOverdue" />
      </dd>
    </div>
    <div v-if="detail.completionDeadline" class="request-facts__item">
      <dt>{{ t('accredited.completion.deadline') }}</dt>
      <dd><DateStamp :value="detail.completionDeadline" with-hijri /></dd>
    </div>
    <div v-if="detail.completedAt" class="request-facts__item">
      <dt>{{ t('requests.detail.completedAt') }}</dt>
      <dd><DateStamp :value="detail.completedAt" /></dd>
    </div>
    <div v-if="showsPaidFlag" class="request-facts__item">
      <dt>{{ t('requests.detail.payment') }}</dt>
      <dd>
        <Badge :variant="detail.payment.paid ? 'success' : 'warning'">{{
          detail.payment.paid ? t('requests.detail.paid') : t('requests.detail.notPaid')
        }}</Badge>
      </dd>
    </div>
  </dl>
</template>
