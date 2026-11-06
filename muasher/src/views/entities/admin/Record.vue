<script setup>
import { computed, onMounted, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'
import EntityDetailsList from '@/components/entities/EntityDetailsList.vue'
import EntityRecordActions from '@/components/entities/admin/EntityRecordActions.vue'
import FrozenRequestsCard from '@/components/entities/admin/FrozenRequestsCard.vue'
import EntityActionsHost from '@/components/entities/admin/EntityActionsHost.vue'
import ProfileChangeHistory from '@/components/entities/ProfileChangeHistory.vue'
import EntityAssessmentsCard from '@/components/entities/admin/EntityAssessmentsCard.vue'
import EntityLogoPreview from '@/components/entities/admin/EntityLogoPreview.vue'
import EntityLevelCard from '@/components/entities/admin/EntityLevelCard.vue'
import EntityInsightsPanel from '@/components/insights/EntityInsightsPanel.vue'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { profileChangesService } from '@/services/profileChangesService'
import { useEntityRecord } from '@/composables/useEntityRecord'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { ENTITY_ACTIONS } from '@/composables/useEntityActions'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  id: { type: String, required: true },
})

const DETAIL_ROWS = [
  'nameAr',
  'nameEn',
  'unifiedNumber',
  'entityType',
  'developmentSector',
  'orgSize',
  'licenseNumber',
  'foundedOn',
  'foundingYear',
  'region',
  'city',
  'governanceGrade',
  'employeesCount',
  'annualBeneficiariesCount',
  'hasStrategicPlan',
]
const CONTACT_ROWS = ['authorizedPersonName', 'officialEmail', 'phone', 'websiteUrl']
const CONTACT_ONLY_ROWS = ['authorizedPersonName', 'officialEmail', 'phone']
const LIFECYCLE_ROWS = [
  'createdAt',
  'verifiedAt',
  'marketingConsent',
  'verificationRemindedAt',
  'deactivatedAt',
  'deactivationReason',
  'restoredAt',
]
const DELETION_ROWS = ['deletedAt', 'deletionReason']

const { t } = useI18n()
const router = useRouter()
const { rowsOf, localized } = useEntityDisplay()
const { can } = usePermissions()
const {
  entity,
  status,
  errorMessage,
  frozenRequests,
  frozenStatus,
  isFullRecord,
  load,
  loadFrozenRequests,
  refreshLogo,
} = useEntityRecord(toRef(props, 'id'))
const actionsHost = ref(null)
const historyVersion = ref(0)
const loadHistory = (params) => profileChangesService.listForEntity(props.id, params)

const sections = computed(() => {
  if (!entity.value) return []
  if (!isFullRecord.value)
    return [{ key: 'contact', rows: rowsOf(entity.value, CONTACT_ONLY_ROWS) }]
  const contact = { key: 'contact', rows: rowsOf(entity.value, CONTACT_ROWS) }
  const deletion = entity.value.isDeleted
    ? [{ key: 'deletion', rows: rowsOf(entity.value, DELETION_ROWS) }]
    : []
  const lifecycle = {
    key: 'lifecycle',
    rows: rowsOf(entity.value, LIFECYCLE_ROWS, { skipEmpty: true }),
  }
  return [
    ...deletion,
    { key: 'details', rows: rowsOf(entity.value, DETAIL_ROWS) },
    contact,
    lifecycle,
  ]
})

const backRoute = computed(() =>
  entity.value?.isDeleted ? ROUTE_NAMES.ADMIN_ENTITIES_DELETED : ROUTE_NAMES.ADMIN_ENTITIES,
)

function onDone({ key }) {
  if (key === ENTITY_ACTIONS.PERMANENT_DELETE)
    return router.push({ name: ROUTE_NAMES.ADMIN_ENTITIES_DELETED })
  historyVersion.value += 1
  return load()
}

onMounted(load)
</script>

<template>
  <section class="entity-record">
    <div v-if="status === 'loading'" class="entity-record__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState
      v-else-if="status === 'missing'"
      :title="t('entities.record.missingTitle')"
      :message="t('entities.record.missingMessage')"
    />

    <template v-else-if="entity">
      <router-link class="entity-record__back" :to="{ name: backRoute }">
        <AppIcon name="arrow-left" />
        {{ t(entity.isDeleted ? 'entities.record.backToDeleted' : 'entities.record.backToList') }}
      </router-link>
      <header class="entity-record__header">
        <div class="entity-record__heading">
          <h1 class="entity-record__title">{{ localized(entity.name) }}</h1>
          <span class="entity-record__reference u-ltr">{{ entity.reference }}</span>
          <StatusBadge :status="entity.status" />
          <FrozenBadge v-if="entity.isFrozen" />
        </div>
        <EntityRecordActions :entity="entity" @action="actionsHost.run($event, entity)" />
      </header>

      <EntityLevelCard
        v-if="can(PERMISSIONS.CERTIFICATES_MANAGE)"
        class="entity-record__level"
        :entity-id="entity.id"
        :open-accredited="entity.hasOpenAccreditedRequest === true"
      />

      <aside v-if="entity.isDeleted" class="entity-record__notice" role="note">
        <AppIcon name="snowflake" />
        <p>{{ t('entities.record.deletedNotice') }}</p>
      </aside>

      <div class="entity-record__grid">
        <Card
          v-for="section in sections"
          :key="section.key"
          :title="t(`entities.record.sections.${section.key}`)"
        >
          <EntityDetailsList :rows="section.rows" />
        </Card>
        <EntityLogoPreview
          v-if="isFullRecord"
          :logo="entity.logo ?? null"
          :entity-name="localized(entity.name)"
          :refresh="refreshLogo"
        />
        <FrozenRequestsCard
          v-if="entity.isDeleted"
          :requests="frozenRequests"
          :status="frozenStatus"
          @retry="loadFrozenRequests"
        />
        <EntityAssessmentsCard
          v-if="can(PERMISSIONS.ASSESSMENT_HISTORY_VIEW)"
          :key="`assessments-${historyVersion}`"
          :entity-id="entity.id"
        />
      </div>

      <EntityInsightsPanel
        v-if="can(PERMISSIONS.REPORTS_INSIGHTS_VIEW)"
        class="entity-record__insights"
        :entity-id="entity.id"
      />

      <ProfileChangeHistory
        v-if="isFullRecord"
        :key="historyVersion"
        :loader="loadHistory"
        show-staff-identity
      />

      <EntityActionsHost ref="actionsHost" @done="onDone" />
    </template>
  </section>
</template>
