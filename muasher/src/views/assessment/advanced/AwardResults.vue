<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { useAwardResults } from '@/composables/useAwardResults'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t, n } = useI18n()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

const { status, errorMessage, regionGroups, busy, localized, load, setRank, approveRegion } =
  useAwardResults()

useHead({ title: () => t('award.results.title') })
onMounted(load)

const rankOptions = computed(() => [
  { value: 0, label: t('award.results.rank.none') },
  { value: 1, label: t('award.results.rank.first') },
  { value: 2, label: t('award.results.rank.second') },
  { value: 3, label: t('award.results.rank.third') },
])

function rankMedal(rank) {
  return rank === 1 ? 'is-gold' : rank === 2 ? 'is-silver' : rank === 3 ? 'is-bronze' : ''
}
function rankLabel(rank) {
  return rank === 1
    ? t('award.results.rank.first')
    : rank === 2
      ? t('award.results.rank.second')
      : rank === 3
        ? t('award.results.rank.third')
        : ''
}

async function onSetRank(row, value) {
  try {
    await setRank(row.assessmentId, value ? value : null)
  } catch {
    toast.error(t('award.results.toasts.rankFailed'))
  }
}

async function onApproveRegion(group) {
  const accepted = await confirm({
    title: t('award.results.approveTitle'),
    message: t('award.results.approveMessage', { region: localized(group.name) }),
    confirmLabel: t('award.results.approve'),
    variant: 'primary',
  })
  if (!accepted) return
  try {
    await approveRegion(group.key)
    toast.success(t('award.results.toasts.approved'))
  } catch {
    toast.error(t('award.results.toasts.approveFailed'))
  }
}

function goToNational() {
  router.push({ name: ROUTE_NAMES.AWARD_NATIONAL })
}
</script>

<template>
  <section class="award-results">
    <header class="award-results__header">
      <div>
        <p class="award-results__eyebrow">{{ t('advanced.title') }}</p>
        <h1 class="award-results__title">{{ t('award.results.title') }}</h1>
        <p class="award-results__subtitle">{{ t('award.results.subtitle') }}</p>
      </div>
      <BaseButton variant="secondary" icon="fa-solid fa-flag" @click="goToNational">
        {{ t('award.results.toNational') }}
      </BaseButton>
    </header>

    <p class="award-results__note" role="note">
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      {{ t('award.results.decisionNote') }}
    </p>

    <div v-if="status === 'loading'" class="award-results__loading">
      <Skeleton variant="block" :count="3" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <div v-else class="award-results__regions">
      <Card v-for="group in regionGroups" :key="group.key" class="award-results__region">
        <div class="award-results__region-head">
          <div class="award-results__region-title">
            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
            <h2>{{ localized(group.name) }}</h2>
            <StatusBadge v-if="group.approved" status="approved" />
          </div>
          <BaseButton
            v-if="!group.approved"
            variant="primary"
            icon="fa-solid fa-circle-check"
            size="sm"
            :disabled="group.rankedCount === 0 || busy"
            @click="onApproveRegion(group)"
          >
            {{ t('award.results.approve') }}
          </BaseButton>
        </div>

        <ul class="award-results__list">
          <li v-for="row in group.results" :key="row.assessmentId" class="award-results__row">
            <span v-if="row.regionalRank" class="award-results__medal" :class="rankMedal(row.regionalRank)">
              <i class="fa-solid fa-medal" aria-hidden="true"></i>
              <span class="award-results__medal-label">{{ rankLabel(row.regionalRank) }}</span>
            </span>
            <span v-else class="award-results__medal award-results__medal--empty" aria-hidden="true">—</span>

            <span class="award-results__entity">{{ localized(row.entityName) }}</span>

            <span class="award-results__scores">
              <span class="award-results__avg">{{ t('award.results.average', { value: n(row.average, 'default') }) }}</span>
              <span class="award-results__initial">{{ t('award.results.initial', { value: n(row.initialScore, 'default') }) }}</span>
            </span>

            <StatusBadge v-if="group.approved" :status="row.status" class="award-results__status" />
            <DropdownList
              v-else
              class="award-results__rank"
              :model-value="row.regionalRank ?? 0"
              :options="rankOptions"
              :placeholder="t('award.results.rank.select')"
              @update:model-value="onSetRank(row, $event)"
            />
          </li>
        </ul>
      </Card>
    </div>
  </section>
</template>
