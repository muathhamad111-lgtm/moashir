<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import PortalAreaHeader from '@/components/portal/PortalAreaHeader.vue'
import AccreditedTaskWorkspace from '@/components/accredited/AccreditedTaskWorkspace.vue'
import NewMessageDialog from '@/components/messages/NewMessageDialog.vue'
import { assessmentsService } from '@/services/assessmentsService'
import { useToast } from '@/composables/useToast'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { ASSESSMENT_TABS } from '@/constants/assessment'

const COUNT_KEYS = {
  [ASSESSMENT_TABS.CURRENT]: 'open',
  [ASSESSMENT_TABS.HISTORY]: 'total',
  [ASSESSMENT_TABS.RESULTS]: 'withResult',
}
const ENQUIRY_TYPE = 'accredited_assessment'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const assessmentId = computed(() => Number(route.params.id))
const number = ref('')
const counts = ref(null)
const contactOpen = ref(false)

useHead({ title: () => t('accredited.form.title') })

const breadcrumbs = computed(() => [
  {
    key: 'journey',
    label: t('portal.breadcrumb.journey'),
    to: { name: ROUTE_NAMES.APP_DASHBOARD },
  },
  {
    key: 'assessments',
    label: t('portal.assessments.title'),
    to: { name: ROUTE_NAMES.ASSESSMENT_HISTORY },
  },
  {
    key: 'request',
    label: number.value || t('accredited.form.title'),
    isReference: Boolean(number.value),
  },
])
const tabs = computed(() =>
  Object.values(ASSESSMENT_TABS).map((value) => ({
    value,
    label: t(`portal.assessments.tabs.${value}`),
    count: counts.value?.[COUNT_KEYS[value]] ?? null,
  })),
)

function openTab(tab) {
  router.push({ name: ROUTE_NAMES.ASSESSMENT_HISTORY, query: { tab } })
}

function onSubmitted() {
  router.push({ name: ROUTE_NAMES.ASSESSMENT_ACCREDITED })
}

function onLoaded(assessment) {
  number.value = assessment.number
}

function onMessageCreated({ id, reference }) {
  toast.success(t('portal.messages.dialog.sent', { reference }))
  if (id) router.push({ name: ROUTE_NAMES.MESSAGES_THREAD, params: { id } })
}

onMounted(async () => {
  const envelope = await assessmentsService.list({ perPage: 1 }).catch(() => null)
  counts.value = envelope?.meta?.counts ?? null
})
</script>

<template>
  <section class="portal-area accredited-task-page">
    <PortalAreaHeader
      :breadcrumbs="breadcrumbs"
      :title="t('portal.assessments.title')"
      :subtitle="t('portal.assessments.subtitle')"
      :tabs="tabs"
      :tab="ASSESSMENT_TABS.CURRENT"
      :tabs-label="t('portal.assessments.tabsLabel')"
      panel-id="accredited-form-panel"
      @update:tab="openTab"
    />
    <div
      id="accredited-form-panel"
      class="portal-area__body accredited-task-page__body"
      role="tabpanel"
      aria-labelledby="accredited-form-panel-tab-current"
    >
      <AccreditedTaskWorkspace
        :key="assessmentId"
        :assessment-id="assessmentId"
        @submitted="onSubmitted"
        @loaded="onLoaded"
        @contact="contactOpen = true"
      />
    </div>
    <NewMessageDialog
      v-model="contactOpen"
      :enquiry-type="ENQUIRY_TYPE"
      @created="onMessageCreated"
    />
  </section>
</template>
