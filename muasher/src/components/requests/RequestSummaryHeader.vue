<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import RecordSummary from '@/components/records/RecordSummary.vue'
import RecordIdentity from '@/components/records/RecordIdentity.vue'
import RecordStatus from '@/components/records/RecordStatus.vue'
import RecordGroup from '@/components/records/RecordGroup.vue'
import RecordFactList from '@/components/records/RecordFactList.vue'
import EntityHeaderLogo from '@/components/entities/admin/EntityHeaderLogo.vue'
import RequestFactValue from './RequestFactValue.vue'
import RequestHeaderActions from './RequestHeaderActions.vue'
import { usePermissions } from '@/composables/usePermissions'
import { localizedText } from '@/utils/localizedText'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'
import {
  REQUEST_FACT_LABELS,
  requestFactsOf,
  requestStatusSince,
} from '@/utils/requests/requestFactSets'

// The request record's summary (layout A): the actions above; the entity's logo, the path, the number and the
// entity, with the status at the end; then who holds the request and its dates, for this path and state.
const props = defineProps({
  detail: { type: Object, required: true },
  assignmentMode: { type: String, default: null },
  bundleRequesting: { type: Boolean, default: false },
  canReturn: { type: Boolean, default: false },
  canWithdraw: { type: Boolean, default: false },
  refreshLogo: { type: Function, required: true },
})

const emit = defineEmits(['assign', 'bundle', 'reopen', 'force', 'return', 'withdraw'])
const { t, locale } = useI18n()
const { can } = usePermissions()

const backTo = computed(() => ({
  name: can(PERMISSIONS.REQUESTS_QUEUE_VIEW)
    ? ROUTE_NAMES.REQUESTS_INDEX
    : ROUTE_NAMES.REQUESTS_ASSIGNED,
}))
const pathLabel = computed(() => localizedText(props.detail.pathLabel, locale.value))
const entityName = computed(() => localizedText(props.detail.entity?.name, locale.value))
const entityRoute = computed(() =>
  props.detail.entity?.id && can(PERMISSIONS.ENTITIES_VIEW)
    ? { name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: props.detail.entity.id } }
    : null,
)
const facts = computed(() => requestFactsOf(props.detail))
const labelled = (keys) => keys.map((key) => ({ key, label: t(REQUEST_FACT_LABELS[key]) }))
const groups = computed(() =>
  [
    { key: 'assignment', title: t('records.groups.assignment'), facts: facts.value.assignment },
    { key: 'timeline', title: t('records.groups.timeline'), facts: facts.value.timeline },
  ].filter((group) => group.facts.length),
)
</script>

<template>
  <RecordSummary :back-to="backTo" :back-label="t('requests.detail.back')">
    <template #actions>
      <RequestHeaderActions
        :detail="detail"
        :assignment-mode="assignmentMode"
        :bundle-requesting="bundleRequesting"
        :can-return="canReturn"
        :can-withdraw="canWithdraw"
        @assign="emit('assign', $event)"
        @bundle="emit('bundle')"
        @return="emit('return')"
        @withdraw="emit('withdraw')"
        @reopen="emit('reopen')"
        @force="emit('force')"
      />
    </template>
    <template #identity>
      <RecordIdentity :kind="pathLabel" :title="detail.number" title-is-code>
        <template #mark>
          <EntityHeaderLogo
            :logo="detail.entity?.logo ?? null"
            :entity-name="entityName"
            :refresh="refreshLogo"
          />
        </template>
        <template v-if="detail.entity" #subject>
          <router-link
            v-if="entityRoute"
            class="record-identity__link"
            :to="entityRoute"
            data-testid="request-entity"
            >{{ entityName }}</router-link
          >
          <span v-else data-testid="request-entity">{{ entityName }}</span>
          <span aria-hidden="true">·</span>
          <bdi dir="ltr" class="record-identity__code">{{ detail.entity.reference }}</bdi>
        </template>
      </RecordIdentity>
    </template>
    <template #status>
      <RecordStatus :status="detail.status" :since="requestStatusSince(detail)">
        <span v-if="detail.isFrozen" class="record-fact__note record-fact__note--danger">{{
          t('entities.frozen.badge')
        }}</span>
      </RecordStatus>
    </template>
    <template v-if="groups.length" #groups>
      <RecordGroup v-for="group in groups" :key="group.key" :title="group.title">
        <RecordFactList
          v-slot="{ fact }"
          :facts="labelled(group.facts)"
          :columns="groups.length === 1 ? group.facts.length : 2"
        >
          <RequestFactValue :detail="detail" :fact="fact" />
        </RecordFactList>
      </RecordGroup>
    </template>
  </RecordSummary>
</template>
