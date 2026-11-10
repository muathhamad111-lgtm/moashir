<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { useAccreditedRequest } from '@/composables/useAccreditedRequest'
import { PATH_KEYS, REQUEST_TABS } from '@/constants/entityPaths'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { STATUS_VARIANTS } from '@/constants/statuses'
import { localizedText } from '@/utils/localizedText'
import { isoDate } from '@/utils/datetime'
import { isolate } from '@/utils/bidi'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import Tabs from '@/components/ui/Tabs.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import PathHeader from '@/components/paths/PathHeader.vue'
import RequestOverviewTab from '@/components/paths/request/RequestOverviewTab.vue'
import RequestResultTab from '@/components/paths/request/RequestResultTab.vue'
import RequestFeedbackTab from '@/components/paths/request/RequestFeedbackTab.vue'
import RequestCertificateTab from '@/components/paths/request/RequestCertificateTab.vue'
import RequestToolsTab from '@/components/paths/request/RequestToolsTab.vue'
import RequestKitTab from '@/components/paths/request/RequestKitTab.vue'

const { t, locale } = useI18n()
const { assessmentId, record, status, tabs, activeTab, selectTab, load, resultState } =
  useAccreditedRequest()

const tabItems = computed(() =>
  tabs.value.map((value) => ({ value, label: t(`paths.request.tabs.${value}`) })),
)
const title = computed(() =>
  record.value?.number
    ? t('paths.request.title', { number: record.value.number })
    : t('routes.paths.request.title'),
)
const trail = computed(() => [
  {
    key: 'accredited',
    label: t('paths.names.accredited'),
    to: { name: ROUTE_NAMES.PATHS_ACCREDITED },
  },
  { key: 'request', label: record.value?.number ?? '', isReference: true },
])
const facts = computed(() =>
  [
    localizedText(record.value?.periodLabel, locale.value),
    record.value?.submittedAt
      ? t('paths.request.submittedOn', { date: isolate(isoDate(record.value.submittedAt)) })
      : '',
    record.value?.completedAt
      ? t('paths.request.completedOn', { date: isolate(isoDate(record.value.completedAt)) })
      : '',
  ].filter(Boolean),
)
const knownStatus = computed(() => record.value?.status in STATUS_VARIANTS)

useHead({ title: () => title.value })
</script>

<template>
  <section class="path-page request-page" data-testid="path-request-page">
    <PathHeader :path-key="PATH_KEYS.ACCREDITED" :title="title" :trail="trail">
      <template v-if="record" #chips>
        <p class="request-page__facts">
          <span v-for="fact in facts" :key="fact" class="request-page__fact">{{ fact }}</span>
        </p>
        <p class="request-page__badges">
          <StatusBadge v-if="knownStatus" :status="record.status" />
        </p>
      </template>
    </PathHeader>
    <div class="path-page__body">
      <Skeleton v-if="status === 'loading'" variant="block" :count="3" />
      <ErrorState
        v-else-if="status === 'error'"
        :message="t('paths.pages.loadError')"
        @retry="load"
      />
      <EmptyState
        v-else-if="status === 'missing'"
        icon="folder"
        :title="t('paths.request.missing.title')"
        :message="t('paths.request.missing.text')"
      />
      <Tabs
        v-else
        class="request-page__tabs"
        :model-value="activeTab"
        :tabs="tabItems"
        @update:model-value="selectTab"
      >
        <RequestOverviewTab v-if="activeTab === REQUEST_TABS.REQUEST" :record="record" />
        <RequestResultTab
          v-else-if="activeTab === REQUEST_TABS.RESULT"
          :record="record"
          :state="resultState"
        />
        <RequestFeedbackTab v-else-if="activeTab === REQUEST_TABS.FEEDBACK" :state="resultState" />
        <RequestCertificateTab
          v-else-if="activeTab === REQUEST_TABS.CERTIFICATE"
          :record="record"
        />
        <RequestToolsTab
          v-else-if="activeTab === REQUEST_TABS.TOOLS"
          :assessment-id="assessmentId"
        />
        <RequestKitTab v-else-if="activeTab === REQUEST_TABS.KIT" :record="record" />
      </Tabs>
    </div>
  </section>
</template>
