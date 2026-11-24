<script setup>
import { computed, onMounted, provide, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import CycleStatusBadge from '@/components/award/CycleStatusBadge.vue'
import CycleStageActions from '@/components/award/CycleStageActions.vue'
import { useCycleEditor } from '@/composables/useCycleEditor'
import { useCycleTabs } from '@/composables/useCycleTabs'
import { useCycleTransitions } from '@/composables/useCycleTransitions'
import { useCycleAuthority } from '@/composables/useCycleAuthority'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { AWARD_LIMITS, CYCLE_STATUSES } from '@/constants/advanced'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { CYCLE_WORKSPACE } from '@/utils/award/cycleWorkspace'
import { localizedText } from '@/utils/localizedText'

// One page per cycle (operator, 2026-11-24): the title, the stage actions and the tabs stay in place while the
// details, the filtering, the committee, the secretary desk and the results open below them.
const { t, locale } = useI18n()
const route = useRoute()
const cycleId = computed(() => Number(route.params.id))
const editor = useCycleEditor(cycleId)
const { status, cycle } = editor
const { prompt: reasonPrompt, reasonExtra } = useSuperAdminReason()
const transitions = useCycleTransitions({ editor, reasonExtra })
const { canReopen } = useCycleAuthority()
const tabs = useCycleTabs(cycle)
const reopenOpen = ref(false)

const title = computed(() =>
  cycle.value
    ? `${localizedText(cycle.value.name, locale.value)} ${cycle.value.year ?? ''}`.trim()
    : t('cycles.editor.title'),
)
const editable = computed(
  () => Boolean(cycle.value?.canManage) && cycle.value?.status !== CYCLE_STATUSES.CLOSED,
)

provide(CYCLE_WORKSPACE, editor)

onMounted(editor.load)
watch(cycleId, () => editor.load())
</script>

<template>
  <section class="cycle-detail">
    <header class="cycle-setup__header">
      <RouterLink class="cycle-editor__back" :to="{ name: ROUTE_NAMES.AWARD_CYCLES }">
        <AppIcon name="arrow-left" />
        {{ t('cycles.editor.back') }}
      </RouterLink>
      <div class="cycle-detail__title-row">
        <div class="cycle-setup__title-row">
          <h1 class="cycle-setup__title">{{ title }}</h1>
          <CycleStatusBadge v-if="cycle" :status="cycle.status" />
        </div>
        <BaseButton
          v-if="status === 'ready' && editable"
          variant="secondary"
          icon="pen"
          data-testid="cycle-edit"
          :to="{ name: ROUTE_NAMES.AWARD_CYCLE_SETUP, params: { id: cycleId } }"
        >
          {{ t('cycles.detail.edit') }}
        </BaseButton>
      </div>
      <CycleStageActions
        v-if="status === 'ready' && cycle"
        :cycle="cycle"
        :busy="transitions.busy.value"
        :open-block="cycle.openBlock"
        :blocked="transitions.blocked.value"
        :can-reopen="canReopen"
        @run="transitions.confirmAndRun"
        @reopen="reopenOpen = true"
      />
    </header>
    <nav class="cycle-tabs" :aria-label="t('cycles.workspace.label')">
      <RouterLink
        v-for="tab in tabs"
        :key="tab.key"
        class="cycle-tabs__tab"
        :data-tab="tab.key"
        :to="{ name: tab.route, params: { id: cycleId } }"
        :class="{ 'is-active': tab.names.includes(route.name) }"
        :aria-current="tab.names.includes(route.name) ? 'page' : undefined"
      >
        <AppIcon :name="tab.icon" />
        {{ tab.label }}
      </RouterLink>
    </nav>
    <RouterView />
    <ReasonDialog
      v-model="reasonPrompt.open.value"
      :title="t('cycles.reason.title')"
      :submit-label="t('actions.confirm')"
      :action="reasonPrompt.provide"
      action-name="cycles.superAdminReason"
    />
    <ReasonDialog
      v-model="reopenOpen"
      :title="t('cycles.actions.reopen.title')"
      :subtitle="t('cycles.actions.reopen.message')"
      :submit-label="t('cycles.actions.reopen.confirm')"
      :min-length="AWARD_LIMITS.REOPEN_REASON_MIN"
      :action="transitions.reopen"
      action-name="cycles.reopen"
    />
  </section>
</template>
