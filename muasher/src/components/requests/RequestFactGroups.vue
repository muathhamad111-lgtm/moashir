<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import RecordGroup from '@/components/records/RecordGroup.vue'
import RecordFactList from '@/components/records/RecordFactList.vue'
import RequestFactValue from './RequestFactValue.vue'
import { REQUEST_FACT_LABELS, requestFactGroupsOf } from '@/utils/requests/requestFactSets'

// The whole request in titled groups, for a page without the record's summary card (the review page's «الطلب» tab):
// the request, who holds it, its dates, then its details, all in the record's one fact list.
const props = defineProps({
  detail: { type: Object, required: true },
})

const { t } = useI18n()
const TITLES = {
  request: 'records.groups.request',
  assignment: 'records.groups.assignment',
  timeline: 'records.groups.timeline',
  details: 'records.groups.details',
}
const groups = computed(() =>
  requestFactGroupsOf(props.detail).map((group) => ({
    ...group,
    title: t(TITLES[group.key]),
    facts: group.facts.map((key) => ({ key, label: t(REQUEST_FACT_LABELS[key]) })),
  })),
)
</script>

<template>
  <div class="record-fact-groups">
    <RecordGroup v-for="group in groups" :key="group.key" :title="group.title">
      <RecordFactList v-slot="{ fact }" :facts="group.facts">
        <RequestFactValue :detail="detail" :fact="fact" />
      </RecordFactList>
    </RecordGroup>
  </div>
</template>
