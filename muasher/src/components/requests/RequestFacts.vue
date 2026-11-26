<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import RecordFactList from '@/components/records/RecordFactList.vue'
import RequestFactValue from './RequestFactValue.vue'
import { REQUEST_FACT_LABELS, requestFactsOf } from '@/utils/requests/requestFactSets'

// The request's data in the overview, in the record's one fact list: only the details this path and state have
// (requestFactSets), never the summary card's assignment or dates again.
const props = defineProps({
  detail: { type: Object, required: true },
})

const { t } = useI18n()
const facts = computed(() =>
  requestFactsOf(props.detail).details.map((key) => ({ key, label: t(REQUEST_FACT_LABELS[key]) })),
)
</script>

<template>
  <RecordFactList v-slot="{ fact }" :facts="facts">
    <RequestFactValue :detail="detail" :fact="fact" />
  </RecordFactList>
</template>
