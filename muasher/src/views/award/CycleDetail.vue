<script setup>
import { inject, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import PageLoading from '@/components/feedback/PageLoading.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import CycleOverviewCard from '@/components/award/CycleOverviewCard.vue'
import CycleDetailCard from '@/components/award/CycleDetailCard.vue'
import { useCycleOverview } from '@/composables/useCycleSummary'
import { useCycleCommittee } from '@/composables/useCycleCommittee'
import { CYCLE_WORKSPACE } from '@/utils/award/cycleWorkspace'

// The details tab of the cycle workspace: the key facts, then the remaining details in compact cards.
const { t } = useI18n()
const { status, errorMessage, cycle, regions, load } = inject(CYCLE_WORKSPACE)
const committee = useCycleCommittee(cycle)
const { facts, groups } = useCycleOverview(
  () => cycle.value,
  () => regions.value.length,
  () => committee.names.value,
)

watch(() => cycle.value?.id, committee.load, { immediate: true })

useHead({ title: () => t('cycles.detail.title') })
</script>

<template>
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
  <div v-else class="cycle-detail__body">
    <CycleOverviewCard :facts="facts" />
    <div class="cycle-detail__cards">
      <CycleDetailCard
        v-for="group in groups"
        :key="group.key"
        :data-group="group.key"
        :title="group.title"
        :rows="group.rows"
        :text="group.text"
      />
    </div>
  </div>
</template>
