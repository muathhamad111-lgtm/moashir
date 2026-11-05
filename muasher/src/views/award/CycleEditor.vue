<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import CycleStatusBadge from '@/components/award/CycleStatusBadge.vue'
import CycleForm from '@/components/award/CycleForm.vue'
import CycleActionsCard from '@/components/award/CycleActionsCard.vue'
import CycleStaffPanel from '@/components/award/CycleStaffPanel.vue'
import { useCycleEditor } from '@/composables/useCycleEditor'
import { useCycleTransitions } from '@/composables/useCycleTransitions'
import { useCycleStaff } from '@/composables/useCycleStaff'
import { useCycleAuthority } from '@/composables/useCycleAuthority'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { AWARD_LIMITS } from '@/constants/advanced'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const cycleId = computed(() => (route.params.id ? Number(route.params.id) : null))
const editor = useCycleEditor(cycleId)
const {
  status,
  errorMessage,
  cycle,
  published,
  regions,
  blockingCycle,
  isNew,
  initialValues,
  locked,
} = editor
const { prompt: reasonPrompt, reasonExtra } = useSuperAdminReason()
const transitions = useCycleTransitions({ editor, reasonExtra })
const staff = useCycleStaff({ editor, reasonExtra })
const { isSystemAdministrator, canCreate, canReopen } = useCycleAuthority()
const reopenOpen = ref(false)

const title = computed(() =>
  isNew.value
    ? t('cycles.editor.newTitle')
    : `${localizedText(cycle.value?.name, locale.value)} ${cycle.value?.year ?? ''}`.trim(),
)
const editable = computed(() => (isNew.value ? canCreate.value : Boolean(cycle.value?.canManage)))

useHead({ title: () => title.value || t('cycles.editor.title') })

function onCreated(detail) {
  router.replace({ name: ROUTE_NAMES.AWARD_CYCLE, params: { id: detail.id } })
}

onMounted(editor.load)
watch(cycleId, editor.load)
</script>

<template>
  <section class="cycle-editor">
    <header class="cycle-editor__header">
      <RouterLink class="cycle-editor__back" :to="{ name: ROUTE_NAMES.AWARD_CYCLES }">
        <i class="fa-solid fa-arrow-left-long u-flip-in-rtl" aria-hidden="true"></i>
        {{ t('cycles.editor.back') }}
      </RouterLink>
      <div class="cycle-editor__title-row">
        <h1 class="cycle-editor__title">
          {{ status === 'ready' ? title : t('cycles.editor.title') }}
        </h1>
        <CycleStatusBadge v-if="cycle" :status="cycle.status" />
      </div>
    </header>
    <div v-if="status === 'loading'" class="cycle-editor__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="editor.load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="fa-regular fa-folder-open"
      :title="t('cycles.editor.missingTitle')"
      :message="t('cycles.editor.missingMessage')"
    />
    <EmptyState
      v-else-if="status === 'blocked'"
      icon="fa-solid fa-lock"
      :title="t('cycles.editor.blockedTitle')"
      :message="errorMessage || t('cycles.editor.blockedMessage')"
    />
    <div v-else class="cycle-editor__layout">
      <Card class="cycle-editor__main" :title="t('cycles.editor.settings')">
        <CycleForm
          :key="cycle?.updatedAt ?? 'new'"
          :cycle-id="cycleId"
          :initial-values="initialValues"
          :locked="locked"
          :regions="regions"
          :published="published"
          :can-set-participation="isSystemAdministrator"
          :editable="editable"
          :reason-extra="reasonExtra"
          @created="onCreated"
          @updated="editor.apply"
          @locked="editor.lockFields"
        />
      </Card>
      <aside v-if="cycle" class="cycle-editor__aside">
        <CycleActionsCard
          :cycle="cycle"
          :busy="transitions.busy.value"
          :blocking-cycle="blockingCycle"
          :blocked="transitions.blocked.value"
          :can-reopen="canReopen"
          @run="transitions.confirmAndRun"
          @reopen="reopenOpen = true"
        />
        <CycleStaffPanel
          v-if="cycle.revealsStaff"
          :staff="staff"
          :editable="cycle.canManage && cycle.status !== 'closed'"
        />
      </aside>
    </div>
    <ReasonDialog
      v-model="reasonPrompt.open.value"
      :title="t('cycles.reason.title')"
      :message="t('cycles.reason.message')"
      :submit-label="t('actions.confirm')"
      :action="reasonPrompt.provide"
      action-name="cycles.superAdminReason"
    />
    <ReasonDialog
      v-model="reopenOpen"
      :title="t('cycles.actions.reopen.title')"
      :message="t('cycles.actions.reopen.message')"
      :submit-label="t('cycles.actions.reopen.confirm')"
      :min-length="AWARD_LIMITS.REOPEN_REASON_MIN"
      :action="transitions.reopen"
      action-name="cycles.reopen"
    />
  </section>
</template>
