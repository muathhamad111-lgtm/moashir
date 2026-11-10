<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import PortalAreaHeader from '@/components/portal/PortalAreaHeader.vue'
import AccreditedTaskWorkspace from '@/components/accredited/AccreditedTaskWorkspace.vue'
import NewMessageDialog from '@/components/messages/NewMessageDialog.vue'
import { useToast } from '@/composables/useToast'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { REQUEST_TABS } from '@/constants/entityPaths'

const ENQUIRY_TYPE = 'accredited_assessment'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const assessmentId = computed(() => Number(route.params.id))
const number = ref('')
const contactOpen = ref(false)

useHead({ title: () => t('accredited.form.title') })

const breadcrumbs = computed(() => [
  {
    key: 'journey',
    label: t('portal.breadcrumb.journey'),
    to: { name: ROUTE_NAMES.APP_DASHBOARD },
  },
  {
    key: 'accredited',
    label: t('paths.names.accredited'),
    to: { name: ROUTE_NAMES.PATHS_ACCREDITED },
  },
  {
    key: 'request',
    label: number.value || t('accredited.form.title'),
    isReference: Boolean(number.value),
  },
])
function onSubmitted() {
  router.push({
    name: ROUTE_NAMES.PATHS_ACCREDITED_REQUEST,
    params: { id: String(assessmentId.value) },
    query: { tab: REQUEST_TABS.REQUEST },
  })
}

function onLoaded(assessment) {
  number.value = assessment.number
}

function onMessageCreated({ id, reference }) {
  toast.success(t('portal.messages.dialog.sent', { reference }))
  if (id) router.push({ name: ROUTE_NAMES.MESSAGES_THREAD, params: { id } })
}
</script>

<template>
  <section class="portal-area accredited-task-page">
    <PortalAreaHeader
      :breadcrumbs="breadcrumbs"
      :title="t('paths.names.accredited')"
      :subtitle="t('paths.pages.accredited.lead')"
    />
    <div id="accredited-form-panel" class="portal-area__body accredited-task-page__body">
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
