<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import PageLoading from '@/components/feedback/PageLoading.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import CycleStatusBadge from '@/components/award/CycleStatusBadge.vue'
import CycleSetupWizard from '@/components/award/CycleSetupWizard.vue'
import { useCycleEditor } from '@/composables/useCycleEditor'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

const { t, locale } = useI18n()
const route = useRoute()
const cycleId = computed(() => (route.params.id ? Number(route.params.id) : null))
const editor = useCycleEditor(cycleId)
const { status, errorMessage, cycle } = editor
const { prompt: reasonPrompt, reasonExtra } = useSuperAdminReason()
const loads = ref(0)

const title = computed(() =>
  cycle.value
    ? `${localizedText(cycle.value.name, locale.value)} ${cycle.value.year ?? ''}`.trim()
    : t('cycles.setup.newTitle'),
)

const backTo = computed(() =>
  cycleId.value
    ? { name: ROUTE_NAMES.AWARD_CYCLE, params: { id: cycleId.value } }
    : { name: ROUTE_NAMES.AWARD_CYCLES },
)

useHead({ title: () => title.value })

async function load() {
  await editor.load()
  loads.value += 1
}

onMounted(load)
watch(cycleId, (id) => {
  if (cycle.value?.id !== id) load()
})
</script>

<template>
  <section class="cycle-setup">
    <header class="cycle-setup__header">
      <RouterLink class="cycle-editor__back" :to="backTo">
        <AppIcon name="arrow-left" />
        {{ cycleId ? t('cycles.setup.backToCycle') : t('cycles.editor.back') }}
      </RouterLink>
      <div class="cycle-setup__title-row">
        <h1 class="cycle-setup__title">
          {{ status === 'ready' ? title : t('cycles.editor.title') }}
        </h1>
        <CycleStatusBadge v-if="cycle" :status="cycle.status" />
      </div>
    </header>
    <PageLoading v-if="status === 'loading'" />
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="folder"
      :title="t('cycles.editor.missingTitle')"
      :message="t('cycles.editor.missingMessage')"
    />
    <EmptyState
      v-else-if="status === 'blocked'"
      icon="lock"
      :title="t('cycles.editor.blockedTitle')"
      :message="errorMessage || t('cycles.editor.blockedMessage')"
    />
    <CycleSetupWizard v-else :key="loads" :editor="editor" :reason-extra="reasonExtra" />
    <ReasonDialog
      v-model="reasonPrompt.open.value"
      :title="t('cycles.reason.title')"
      :submit-label="t('actions.confirm')"
      :action="reasonPrompt.provide"
      action-name="cycles.superAdminReason"
    />
  </section>
</template>
