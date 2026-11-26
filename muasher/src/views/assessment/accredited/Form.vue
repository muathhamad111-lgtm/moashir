<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import PortalAreaHeader from '@/components/portal/PortalAreaHeader.vue'
import AccreditedTaskWorkspace from '@/components/accredited/AccreditedTaskWorkspace.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { REQUEST_TABS } from '@/constants/entityPaths'
import { usePathNames } from '@/composables/usePathNames'

const { t } = useI18n()
const { pathName, pathDescription } = usePathNames()
const route = useRoute()
const router = useRouter()
const assessmentId = computed(() => Number(route.params.id))
const number = ref('')

useHead({ title: () => t('accredited.form.title') })

const breadcrumbs = computed(() => [
  {
    key: 'journey',
    label: t('portal.breadcrumb.journey'),
    to: { name: ROUTE_NAMES.APP_DASHBOARD },
  },
  {
    key: 'accredited',
    label: pathName('accredited'),
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
</script>

<template>
  <section class="portal-area accredited-task-page">
    <PortalAreaHeader
      :breadcrumbs="breadcrumbs"
      :title="pathName('accredited')"
      :subtitle="pathDescription('accredited', 'paths.pages.accredited.lead')"
    />
    <div id="accredited-form-panel" class="portal-area__body accredited-task-page__body">
      <AccreditedTaskWorkspace
        :key="assessmentId"
        :assessment-id="assessmentId"
        @submitted="onSubmitted"
        @loaded="onLoaded"
      />
    </div>
  </section>
</template>
