<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/modal/BaseModal.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import CommitteeAssessorDetailPanel from '@/components/committee/CommitteeAssessorDetailPanel.vue'
import { useSecretaryReview } from '@/composables/useCommittee'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { COMMITTEE_CYCLE_ID } from '@/mocks/fixtures/committee'
import { formatNumber } from '@/utils/formatNumber'

const { t, n } = useI18n()
const router = useRouter()

const {
  status,
  errorMessage,
  entities,
  selected,
  committeeAssessorsStatus,
  committeeAssessorsError,
  committeeAssessors,
  entityMeta,
  detailStatus,
  detail,
  detailError,
  localized,
  load,
  selectEntity,
  clearEntity,
  openCommitteeAssessor,
  retryDetail,
  closeCommitteeAssessor,
} = useSecretaryReview(COMMITTEE_CYCLE_ID)

onMounted(load)

const detailOpen = computed({
  get: () => detailStatus.value !== 'idle',
  set: (value) => {
    if (!value) closeCommitteeAssessor()
  },
})

const entityColumns = computed(() => [
  { key: 'entityName', label: t('advanced.secretary.review.entity') },
  { key: 'committeeAssessors', label: t('advanced.secretary.review.committeeAssessors') },
  { key: 'average', label: t('advanced.secretary.review.average') },
  { key: 'secretaryStatus', label: t('advanced.committee.committee_assessor_status') },
  { key: 'actions', label: t('components.actions.label') },
])

const committeeAssessorColumns = computed(() => [
  { key: 'committeeAssessorName', label: t('advanced.secretary.review.committeeAssessor') },
  { key: 'status', label: t('advanced.secretary.review.committeeAssessorStatus') },
  { key: 'total', label: t('advanced.secretary.review.committeeAssessorTotal') },
  { key: 'actions', label: t('components.actions.label') },
])

function fmt(value) {
  return formatNumber(n, value, 'default')
}

function averageLabel(value) {
  return value == null ? t('advanced.secretary.review.noAverage') : `${fmt(value)}%`
}

function backToApprovals() {
  router.push({ name: ROUTE_NAMES.AWARD_SECRETARY })
}
</script>

<template>
  <section class="secretary-review">
    <header class="secretary-review__header">
      <div>
        <button type="button" class="secretary-review__back" @click="backToApprovals">
          <i
            class="fa-solid fa-arrow-right-long secretary-review__back-icon"
            aria-hidden="true"
          ></i>
          {{ t('advanced.secretary.review.backToApprovals') }}
        </button>
        <p class="secretary-review__eyebrow">{{ t('advanced.title') }}</p>
        <h1 class="secretary-review__title">{{ t('advanced.secretary.review.title') }}</h1>
        <p class="secretary-review__subtitle">{{ t('advanced.secretary.review.subtitle') }}</p>
      </div>
    </header>

    <p class="secretary-review__independence" role="note">
      <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
      {{ t('advanced.secretary.review.independenceNote') }}
    </p>

    <div class="secretary-review__layout">
      <div class="secretary-review__entities">
        <h2 class="secretary-review__section-title">
          {{ t('advanced.secretary.review.entities') }}
        </h2>
        <Card>
          <DataTable
            :columns="entityColumns"
            :items="entities"
            :status="status"
            :error-message="errorMessage"
            row-key="assessmentId"
            :empty-title="t('advanced.secretary.review.empty.title')"
            :empty-message="t('advanced.secretary.review.empty.message')"
            @retry="load"
          >
            <template #cell-entityName="{ item }">
              <span class="secretary-review__entity">{{ localized(item.entityName) }}</span>
            </template>
            <template #cell-committee-assessors="{ item }">
              <span class="secretary-review__committee-assessors">
                {{
                  t('advanced.secretary.review.completedOf', {
                    completed: item.completedCount,
                    total: item.committeeAssessorCount,
                  })
                }}
              </span>
            </template>
            <template #cell-average="{ item }">
              <span class="secretary-review__avg">{{ averageLabel(item.average) }}</span>
            </template>
            <template #cell-secretaryStatus="{ item }">
              <StatusBadge :status="item.secretaryStatus" />
            </template>
            <template #cell-actions="{ item }">
              <BaseButton
                :variant="selected?.assessmentId === item.assessmentId ? 'primary' : 'ghost'"
                size="sm"
                icon="fa-solid fa-users-viewfinder"
                @click="selectEntity(item)"
              >
                {{ t('advanced.secretary.review.open') }}
              </BaseButton>
            </template>
          </DataTable>
        </Card>
      </div>

      <div class="secretary-review__committee-assessors-panel">
        <h2 class="secretary-review__section-title">
          {{ t('advanced.secretary.review.committeeAssessors') }}
        </h2>
        <Card>
          <div v-if="!selected" class="secretary-review__hint">
            <i class="fa-solid fa-hand-pointer" aria-hidden="true"></i>
            <p>{{ t('advanced.secretary.review.selectHint') }}</p>
          </div>

          <template v-else>
            <div class="secretary-review__selected">
              <div>
                <h3 class="secretary-review__selected-name">
                  {{ localized(selected.entityName) }}
                </h3>
                <p v-if="entityMeta" class="secretary-review__selected-meta">
                  {{ t('advanced.secretary.review.average') }}:
                  {{ averageLabel(entityMeta.average) }}
                </p>
              </div>
              <BaseButton variant="ghost" size="sm" icon="fa-solid fa-xmark" @click="clearEntity">
                {{ t('advanced.secretary.review.close') }}
              </BaseButton>
            </div>

            <div v-if="committeeAssessorsStatus === 'loading'" class="secretary-review__loading">
              <Skeleton variant="block" :count="3" />
            </div>
            <ErrorState
              v-else-if="committeeAssessorsStatus === 'error'"
              :message="committeeAssessorsError"
              @retry="selectEntity(selected)"
            />
            <EmptyState
              v-else-if="committeeAssessorsStatus === 'empty'"
              icon="fa-regular fa-user"
              :title="t('advanced.secretary.review.committeeAssessorsEmpty.title')"
              :message="t('advanced.secretary.review.committeeAssessorsEmpty.message')"
            />
            <DataTable
              v-else
              :columns="committeeAssessorColumns"
              :items="committeeAssessors"
              status="success"
              row-key="committeeAssessorId"
            >
              <template #cell-committeeAssessorName="{ item }">
                <span class="secretary-review__committee-assessor">
                  <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                  {{ localized(item.committeeAssessorName) }}
                  <Badge v-if="item.substituteFor" variant="muted">
                    {{ t('advanced.secretary.review.substituteOf') }}
                  </Badge>
                </span>
              </template>
              <template #cell-status="{ item }">
                <StatusBadge :status="item.status" />
              </template>
              <template #cell-total="{ item }">
                <span class="secretary-review__total">
                  {{ item.total == null ? '—' : `${fmt(item.total)}%` }}
                </span>
              </template>
              <template #cell-actions="{ item }">
                <BaseButton
                  variant="ghost"
                  size="sm"
                  icon="fa-solid fa-eye"
                  @click="openCommitteeAssessor(item.committeeAssessorId)"
                >
                  {{ t('advanced.secretary.review.viewDetail') }}
                </BaseButton>
              </template>
            </DataTable>
          </template>
        </Card>
      </div>
    </div>

    <BaseModal
      v-model="detailOpen"
      size="lg"
      :title="
        detail
          ? t('advanced.secretary.review.detailTitle', {
              name: localized(detail.committeeAssessorName),
              entity: localized(detail.entityName),
            })
          : t('advanced.secretary.review.viewDetail')
      "
    >
      <div class="secretary-review__detail">
        <p class="secretary-review__detail-sub">
          {{ t('advanced.secretary.review.detailSubtitle') }}
        </p>
        <div v-if="detailStatus === 'loading'" class="secretary-review__loading">
          <Skeleton variant="block" :count="4" />
        </div>
        <ErrorState
          v-else-if="detailStatus === 'error'"
          :message="detailError"
          @retry="retryDetail"
        />
        <CommitteeAssessorDetailPanel v-else-if="detail" :detail="detail" :localized="localized" />
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="closeCommitteeAssessor">
          {{ t('advanced.secretary.review.close') }}
        </BaseButton>
      </template>
    </BaseModal>
  </section>
</template>
