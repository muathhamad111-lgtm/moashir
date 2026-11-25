<script setup>
import { computed, onMounted, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import EntityDetailsList from '@/components/entities/EntityDetailsList.vue'
import EntityRecordHeader from '@/components/entities/admin/EntityRecordHeader.vue'
import FrozenRequestsCard from '@/components/entities/admin/FrozenRequestsCard.vue'
import EntityActionsHost from '@/components/entities/admin/EntityActionsHost.vue'
import EntityAssessmentsTable from '@/components/entities/admin/EntityAssessmentsTable.vue'
import EntityAccountTimeline from '@/components/entities/admin/EntityAccountTimeline.vue'
import EntityLevelCard from '@/components/entities/admin/EntityLevelCard.vue'
import EntityPendingChangeCard from '@/components/entities/admin/EntityPendingChangeCard.vue'
import EntityDocumentsCard from '@/components/entities/admin/EntityDocumentsCard.vue'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { useEntityRecord } from '@/composables/useEntityRecord'
import { useEntityDisplay } from '@/composables/useEntityDisplay'
import { useBillingRows } from '@/composables/useBillingRows'
import { ENTITY_ACTIONS } from '@/composables/useEntityActions'
import { ROUTE_NAMES } from '@/constants/routeNames'
import {
  CONTACT_ONLY_ROWS,
  CONTACT_ROWS,
  DELETION_ROWS,
  DETAIL_ROWS,
} from '@/constants/entityRecordRows'

const TAB_QUERY = 'tab'

const props = defineProps({
  id: { type: String, required: true },
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { rowsOf } = useEntityDisplay()
const addressRows = useBillingRows()
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
const showsLevelRibbon = computed(() => can(PERMISSIONS.CERTIFICATES_MANAGE))
const historyVersion = ref(0)

const tabs = computed(() => {
  if (!isFullRecord.value) return [{ value: 'contact', label: t('entities.record.tabs.contact') }]
  return [
    'general',
    'contact',
    'address',
    ...(can(PERMISSIONS.ASSESSMENT_HISTORY_VIEW) ? ['assessments'] : []),
    'history',
  ].map((value) => ({ value, label: t(`entities.record.tabs.${value}`) }))
})
const activeTab = computed({
  get: () =>
    tabs.value.find((tab) => tab.value === route.query[TAB_QUERY])?.value ?? tabs.value[0].value,
  set: (value) => router.replace({ query: { ...route.query, [TAB_QUERY]: value } }),
})

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
      <EntityRecordHeader
        :entity="entity"
        :refresh-logo="refreshLogo"
        :shows-level-badge="!showsLevelRibbon"
        @action="actionsHost.run($event, entity)"
      />

      <EntityLevelCard
        v-if="showsLevelRibbon"
        :entity-id="entity.id"
        :open-accredited="entity.hasOpenAccreditedRequest === true"
      />

      <aside v-if="entity.isDeleted" class="entity-record__notice" role="note">
        <AppIcon name="snowflake" />
        <div>
          <p>{{ t('entities.record.deletedNotice') }}</p>
          <EntityDetailsList :rows="rowsOf(entity, DELETION_ROWS)" />
        </div>
      </aside>
      <EntityPendingChangeCard
        v-if="can(PERMISSIONS.ENTITIES_PROFILE_CHANGE_APPROVE)"
        :key="`pending-${historyVersion}`"
        :entity-id="entity.id"
        @decided="onDone({ key: 'profile_change' })"
      />

      <Tabs v-model="activeTab" class="entity-record__tabs" :tabs="tabs">
        <div v-if="activeTab === 'general'" class="entity-record__panel">
          <Card :title="t('entities.record.sections.details')">
            <EntityDetailsList :rows="rowsOf(entity, DETAIL_ROWS)" />
          </Card>
          <Card :title="t('entities.record.sections.tax')">
            <EntityDetailsList :rows="addressRows.taxRowsOf(entity)" />
          </Card>
          <EntityDocumentsCard v-if="entity.documents" :entity="entity" />
        </div>
        <Card v-else-if="activeTab === 'contact'" :title="t('entities.record.sections.contact')">
          <EntityDetailsList
            :rows="rowsOf(entity, isFullRecord ? CONTACT_ROWS : CONTACT_ONLY_ROWS)"
          />
        </Card>
        <Card
          v-else-if="activeTab === 'address'"
          :title="t('entities.record.sections.nationalAddress')"
        >
          <EntityDetailsList :rows="addressRows.addressRowsOf(entity)" />
        </Card>
        <div v-else-if="activeTab === 'assessments'" class="entity-record__panel">
          <FrozenRequestsCard
            v-if="entity.isDeleted"
            :requests="frozenRequests"
            :status="frozenStatus"
            @retry="loadFrozenRequests"
          />
          <EntityAssessmentsTable :key="`assessments-${historyVersion}`" :entity-id="entity.id" />
        </div>
        <EntityAccountTimeline
          v-else-if="activeTab === 'history'"
          :key="`history-${historyVersion}`"
          :entity-id="entity.id"
        />
      </Tabs>

      <EntityActionsHost ref="actionsHost" @done="onDone" />
    </template>
  </section>
</template>
