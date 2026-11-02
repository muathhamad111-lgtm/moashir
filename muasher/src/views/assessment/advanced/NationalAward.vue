<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import { useNationalAward } from '@/composables/useAwardResults'
import { AWARD_REGIONS } from '@/mocks/fixtures/awardResults'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const { t, n } = useI18n()
const { confirm } = useConfirm()
const toast = useToast()

const { status, errorMessage, candidates, approved, busy, localized, load, setRank, approve } =
  useNationalAward()

useHead({ title: () => t('award.national.title') })
onMounted(load)

const regionName = (key) => AWARD_REGIONS.find((r) => r.key === key)?.name ?? { ar: key, en: key }

const rankOptions = computed(() => [
  { value: 0, label: t('award.results.rank.none') },
  { value: 1, label: t('award.results.rank.first') },
  { value: 2, label: t('award.results.rank.second') },
  { value: 3, label: t('award.results.rank.third') },
])
function rankLabel(rank) {
  return rank === 1
    ? t('award.results.rank.first')
    : rank === 2
      ? t('award.results.rank.second')
      : rank === 3
        ? t('award.results.rank.third')
        : '—'
}

const columns = computed(() => [
  { key: 'entityName', label: t('award.national.columns.entity') },
  { key: 'region', label: t('award.national.columns.region') },
  { key: 'regionalRank', label: t('award.national.columns.regionalRank') },
  { key: 'average', label: t('award.national.columns.average') },
  { key: 'nationalRank', label: t('award.national.columns.nationalRank') },
])

const rankedCount = computed(() => candidates.value.filter((c) => c.nationalRank != null).length)

async function onSetRank(row, value) {
  try {
    await setRank(row.assessmentId, value ? value : null)
  } catch {
    toast.error(t('award.results.toasts.rankFailed'))
  }
}

async function onApprove() {
  const accepted = await confirm({
    title: t('award.national.approveTitle'),
    message: t('award.national.approveMessage'),
    confirmLabel: t('award.national.approve'),
    variant: 'primary',
  })
  if (!accepted) return
  try {
    await approve()
    toast.success(t('award.national.toasts.approved'))
  } catch {
    toast.error(t('award.national.toasts.approveFailed'))
  }
}
</script>

<template>
  <section class="award-national">
    <header class="award-national__header">
      <div>
        <p class="award-national__eyebrow">{{ t('advanced.title') }}</p>
        <h1 class="award-national__title">{{ t('award.national.title') }}</h1>
        <p class="award-national__subtitle">{{ t('award.national.subtitle') }}</p>
      </div>
      <BaseButton
        v-if="status === 'success' && !approved"
        variant="primary"
        icon="fa-solid fa-award"
        :disabled="rankedCount === 0 || busy"
        @click="onApprove"
      >
        {{ t('award.national.approve') }}
      </BaseButton>
      <StatusBadge v-else-if="approved" status="national-winner" />
    </header>

    <p class="award-national__note" role="note">
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      {{ t('award.national.decisionNote') }}
    </p>

    <div v-if="status === 'loading'" class="award-national__loading">
      <Skeleton variant="block" :count="2" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <EmptyState
      v-else-if="status === 'empty'"
      icon="fa-solid fa-flag"
      :title="t('award.national.empty.title')"
      :message="t('award.national.empty.message')"
    />

    <Card v-else>
      <DataTable :columns="columns" :items="candidates" status="success" row-key="assessmentId">
        <template #cell-entityName="{ item }">
          <span class="award-national__entity">{{ localized(item.entityName) }}</span>
        </template>
        <template #cell-region="{ item }">
          <span class="award-national__region">{{ localized(regionName(item.region)) }}</span>
        </template>
        <template #cell-regionalRank="{ item }">
          <span class="award-national__rank">{{ rankLabel(item.regionalRank) }}</span>
        </template>
        <template #cell-average="{ item }">
          <span class="award-national__avg">{{ n(item.average, 'default') }}</span>
        </template>
        <template #cell-nationalRank="{ item }">
          <StatusBadge v-if="approved && item.nationalRank" status="national-winner" />
          <DropdownList
            v-else-if="!approved"
            class="award-national__rank-select"
            :model-value="item.nationalRank ?? 0"
            :options="rankOptions"
            :placeholder="t('award.results.rank.select')"
            @update:model-value="onSetRank(item, $event)"
          />
          <span v-else>{{ rankLabel(item.nationalRank) }}</span>
        </template>
      </DataTable>
    </Card>
  </section>
</template>
