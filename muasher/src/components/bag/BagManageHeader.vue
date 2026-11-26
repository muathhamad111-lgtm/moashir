<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import RecordSummary from '@/components/records/RecordSummary.vue'
import RecordIdentity from '@/components/records/RecordIdentity.vue'
import RecordStatus from '@/components/records/RecordStatus.vue'
import RecordGroup from '@/components/records/RecordGroup.vue'
import RecordFactList from '@/components/records/RecordFactList.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import { bagRequestsService } from '@/services/bagRequestsService'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'
import { KIT_FACT_LABELS, kitFactsOf, kitStatusSince } from '@/utils/requests/kitFactSets'

// The Kit request's summary, on the request record's layout A: the Kit as the kind, the number, the entity and the
// accredited result it rests on, the status at the end; then who evaluates it and its dates.
const props = defineProps({
  requestId: { type: String, required: true },
})

const { t, n, locale } = useI18n()
const { can } = usePermissions()
const request = ref(null)

// The Kit's number (KIT-YYYY-NNNNNN) titles the page; the generic title stands in while it loads.
const number = computed(() => request.value?.number || t('bag.manage.title'))
const labelled = (keys) => keys.map((key) => ({ key, label: t(KIT_FACT_LABELS[key]) }))
const groups = computed(() => {
  if (!request.value) return []
  const facts = kitFactsOf(request.value)
  return [
    { key: 'assignment', title: t('records.groups.assignment'), facts: labelled(facts.assignment) },
    { key: 'timeline', title: t('records.groups.timeline'), facts: labelled(facts.timeline) },
  ].filter((group) => group.facts.length)
})
const since = computed(() => (request.value ? kitStatusSince(request.value) : null))
const entityRoute = computed(() =>
  request.value?.entityId && can(PERMISSIONS.ENTITIES_VIEW)
    ? { name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: request.value.entityId } }
    : null,
)

async function load() {
  request.value = null
  try {
    request.value = (await bagRequestsService.get(props.requestId)).data
  } catch {
    request.value = null
  }
}

onMounted(load)
watch(() => props.requestId, load)
</script>

<template>
  <RecordSummary
    :back-to="{ name: ROUTE_NAMES.BAG_EVALUATOR_REQUESTS }"
    :back-label="t('bag.manage.back')"
  >
    <template #identity>
      <RecordIdentity
        :kind="t('bag.manage.kitBadge')"
        :title="number"
        :title-is-code="Boolean(request?.number)"
      >
        <template #mark><AppIcon name="briefcase" /></template>
        <template v-if="request" #subject>
          <router-link v-if="entityRoute" class="record-identity__link" :to="entityRoute">{{
            localizedText(request.entityName, locale)
          }}</router-link>
          <span v-else data-testid="bag-manage-request">{{
            localizedText(request.entityName, locale)
          }}</span>
          <span aria-hidden="true">·</span>
          <span>{{
            t('bag.manage.referenceLine', {
              value: t('bagRequests.referenceValue', {
                score: n(request.referenceSnapshot.scorePercent, 'default'),
              }),
            })
          }}</span>
        </template>
      </RecordIdentity>
    </template>
    <template v-if="request" #status>
      <RecordStatus :status="request.status" :since="since" />
    </template>
    <template v-if="groups.length" #groups>
      <RecordGroup v-for="group in groups" :key="group.key" :title="group.title">
        <RecordFactList v-slot="{ fact }" :facts="group.facts">
          <template v-if="fact === 'evaluator'">{{
            request.assignedEvaluatorName ?? t('requests.unassigned')
          }}</template>
          <DateStamp v-else :value="request[fact]" :with-time="false" with-hijri />
        </RecordFactList>
      </RecordGroup>
    </template>
  </RecordSummary>
</template>
