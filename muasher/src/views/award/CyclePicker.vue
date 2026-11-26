<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import DateStamp from '@/components/accredited/DateStamp.vue'
import CycleStatusBadge from '@/components/award/CycleStatusBadge.vue'
import CycleWindow from '@/components/award/CycleWindow.vue'
import { COLUMN_KINDS } from '@/utils/tableColumns'
import { useAwardCyclePicker } from '@/composables/useAwardCyclePicker'
import { localizedText } from '@/utils/localizedText'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const picker = computed(() => route.meta.picker)
const { status, errorMessage, cycles, openCycles, remember, load } = useAwardCyclePicker(
  picker.value.key,
)
const text = (key) => t(`award.picker.${picker.value.key}.${key}`)

useHead({ title: () => text('title') })

// One cycle in progress: open it straight away, keeping the link's filters. `?list=1` (the back links of the
// cycle pages) always shows the list, so going back never loops.
function targetOf(cycleId) {
  const query = Object.fromEntries(Object.entries(route.query).filter(([key]) => key !== 'list'))
  return { name: picker.value.target, params: { id: cycleId }, query }
}

// The redirect is decided before the list renders (pre-flush watcher) and the table stays in its loading state
// until the navigation ends, so the list never flashes and cannot be clicked mid-redirect.
const redirecting = ref(false)
const tableStatus = computed(() => (redirecting.value ? 'loading' : status.value))

watch(status, async (value) => {
  if (value !== 'success' || route.query.list || openCycles.value.length !== 1) return
  redirecting.value = true
  remember(openCycles.value[0].id)
  try {
    await router.replace(targetOf(openCycles.value[0].id))
  } finally {
    redirecting.value = false
  }
})

const columns = computed(() => [
  { key: 'name', label: t('cycles.columns.name') },
  { key: 'status', label: t('cycles.columns.status') },
  { key: 'committeeEnd', label: t('award.picker.committeeEnd') },
  { key: 'committeeStart', label: t('award.picker.committeeStart'), kind: COLUMN_KINDS.DATE },
  { key: 'submission', label: t('cycles.columns.submission') },
  { key: 'announcementAt', label: t('cycles.columns.announcementAt') },
  { key: 'regions', label: t('cycles.columns.regions') },
  { key: 'counts', label: t('cycles.columns.requests') },
  { key: 'open', label: t('award.picker.open'), action: true },
])
</script>

<template>
  <section class="cycles-page">
    <header class="cycles-page__header">
      <div>
        <p class="cycles-page__eyebrow">{{ t('advanced.eyebrow') }}</p>
        <h1 class="cycles-page__title">{{ text('title') }}</h1>
        <p class="cycles-page__subtitle">{{ text('subtitle') }}</p>
      </div>
    </header>
    <Card>
      <DataTable
        table-key="award-cycle-picker"
        stacked
        :columns="columns"
        :items="cycles"
        :status="tableStatus"
        :error-message="errorMessage"
        :empty-title="t('award.picker.emptyTitle')"
        :empty-message="t('award.picker.emptyMessage')"
        @retry="load"
      >
        <template #cell-name="{ item }"
          >{{ localizedText(item.name, locale) }} <bdi class="u-ltr">{{ item.year }}</bdi></template
        >
        <template #cell-status="{ item }"><CycleStatusBadge :status="item.status" /></template>
        <template #cell-committeeEnd="{ item }"
          ><DateStamp :value="item.committeeEnd" :with-time="false"
        /></template>
        <template #cell-committeeStart="{ item }"
          ><DateStamp :value="item.committeeStart" :with-time="false"
        /></template>
        <template #cell-submission="{ item }"
          ><CycleWindow :start="item.submissionStart" :end="item.submissionEnd"
        /></template>
        <template #cell-announcementAt="{ item }"
          ><DateStamp :value="item.announcementAt" :with-time="false"
        /></template>
        <template #cell-regions="{ item }"
          ><span v-if="item.regions">{{
            t('cycles.list.regionCount', { count: item.regions.length }, item.regions.length)
          }}</span></template
        >
        <template #cell-counts="{ item }"
          ><span v-if="item.counts" class="cycle-table__counts">{{
            t('cycles.list.counts', {
              requests: item.counts.requests,
              qualified: item.counts.qualified,
            })
          }}</span></template
        >
        <template #cell-open="{ item }">
          <RouterLink
            class="cycle-table__link"
            :to="targetOf(item.id)"
            @click="remember(item.id)"
            >{{ text('open') }}</RouterLink
          >
        </template>
      </DataTable>
    </Card>
  </section>
</template>
