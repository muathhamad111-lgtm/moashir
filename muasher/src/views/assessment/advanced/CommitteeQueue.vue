<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/modal/BaseModal.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import { useCommitteeQueue } from '@/composables/useCommittee'
import { useToast } from '@/composables/useToast'
import { ROUTE_NAMES } from '@/constants/routeNames'

const COMMITTEE_CYCLE_ID = 'cycle-committee-2026'
const SCORING_ASSESSMENT_ID = 9401

const { t } = useI18n()
const router = useRouter()
const toast = useToast()

const {
  status,
  errorMessage,
  committeeAssessors,
  disabling,
  allCompleted,
  localized,
  load,
  disable,
} = useCommitteeQueue(COMMITTEE_CYCLE_ID)

onMounted(load)

const columns = computed(() => [
  { key: 'name', label: t('advanced.committee.committeeAssessor') },
  { key: 'status', label: t('advanced.committee.committee_assessor_status') },
  { key: 'actions', label: t('components.actions.label') },
])

function isActive(committeeAssessor) {
  return committeeAssessor.status !== 'excluded' && committeeAssessor.status !== 'disabled'
}

const modalOpen = ref(false)
const activeCommitteeAssessor = ref(null)
const mode = ref('exclude')
const substituteId = ref(null)
const priorNotesVisible = ref(false)
const substituteName = ref('')

const substituteOptions = computed(() =>
  committeeAssessors.value
    .filter((j) => isActive(j) && j.id !== activeCommitteeAssessor.value?.id)
    .map((j) => ({ value: j.id, label: localized(j.name) })),
)

function openDisable(committeeAssessor) {
  activeCommitteeAssessor.value = committeeAssessor
  mode.value = 'exclude'
  substituteId.value = null
  substituteName.value = ''
  priorNotesVisible.value = false
  modalOpen.value = true
}

const canConfirmDisable = computed(() => {
  if (mode.value === 'substitute') return Boolean(substituteId.value)
  return true
})

async function confirmDisable() {
  if (!activeCommitteeAssessor.value || !canConfirmDisable.value) return
  const options =
    mode.value === 'substitute'
      ? {
          mode: 'substitute',
          substituteCommitteeAssessorId: substituteId.value,
          substituteName: substituteName.value || undefined,
          priorNotesVisible: priorNotesVisible.value,
        }
      : { mode: 'exclude' }
  try {
    await disable(activeCommitteeAssessor.value.id, options)
    toast.success(
      mode.value === 'substitute'
        ? t('advanced.committee.toasts.substituted')
        : t('advanced.committee.toasts.excluded'),
    )
    modalOpen.value = false
  } catch {
    toast.error(t('advanced.committee.toasts.disableFailed'))
  }
}

function goToScore() {
  router.push({
    name: ROUTE_NAMES.AWARD_COMMITTEE_SCORE,
    params: { assessmentId: SCORING_ASSESSMENT_ID },
  })
}
</script>

<template>
  <section class="committee-queue">
    <header class="committee-queue__header">
      <div>
        <p class="committee-queue__eyebrow">{{ t('advanced.title') }}</p>
        <h1 class="committee-queue__title">{{ t('advanced.committee.title') }}</h1>
        <p class="committee-queue__subtitle">{{ t('advanced.committee.subtitle') }}</p>
      </div>
    </header>

    <div
      v-if="status === 'success'"
      class="committee-queue__completion"
      :class="{ 'is-complete': allCompleted }"
      role="status"
    >
      <i
        :class="allCompleted ? 'fa-solid fa-circle-check' : 'fa-solid fa-hourglass-half'"
        aria-hidden="true"
      ></i>
      <span>{{ allCompleted ? t('advanced.committee.allCompleted') : t('advanced.committee.awaitingCommitteeAssessors') }}</span>
    </div>

    <Card>
      <DataTable
        :columns="columns"
        :items="committeeAssessors"
        :status="status"
        :error-message="errorMessage"
        row-key="id"
        :empty-title="t('advanced.committee.empty.title')"
        :empty-message="t('advanced.committee.empty.message')"
        @retry="load"
      >
        <template #cell-name="{ item }">
          <span class="committee-queue__committee-assessor">
            <i class="fa-solid fa-user-tie committee-queue__committee-assessor-icon" aria-hidden="true"></i>
            {{ localized(item.name) }}
          </span>
        </template>

        <template #cell-status="{ item }">
          <StatusBadge :status="item.status" />
        </template>

        <template #cell-actions="{ item }">
          <div class="committee-queue__actions">
            <BaseButton
              v-if="isActive(item)"
              variant="ghost"
              size="sm"
              icon="fa-solid fa-pen-to-square"
              @click="goToScore"
            >
              {{ t('advanced.committee.viewScoring') }}
            </BaseButton>
            <BaseButton
              v-if="isActive(item)"
              variant="secondary"
              size="sm"
              icon="fa-solid fa-user-slash"
              :loading="disabling === item.id"
              @click="openDisable(item)"
            >
              {{ t('advanced.committee.disable') }}
            </BaseButton>
            <span v-else class="committee-queue__inactive">
              <i class="fa-solid fa-circle-minus" aria-hidden="true"></i>
              {{ t(`advanced.committee.statuses.${item.status}`) }}
            </span>
          </div>
        </template>
      </DataTable>
    </Card>

    <BaseModal
      v-model="modalOpen"
      size="md"
      :title="t('advanced.committee.disableTitle')"
    >
      <div class="committee-disable">
        <p class="committee-disable__intro">
          {{ t('advanced.committee.disableMessage') }}
        </p>
        <p v-if="activeCommitteeAssessor" class="committee-disable__subject">
          <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
          {{ localized(activeCommitteeAssessor.name) }}
        </p>

        <fieldset class="committee-disable__modes">
          <legend class="committee-disable__legend">{{ t('advanced.committee.disableMode') }}</legend>
          <label class="committee-disable__mode">
            <input
              v-model="mode"
              type="radio"
              value="exclude"
              name="disable-mode"
              class="committee-disable__radio"
            />
            <span class="committee-disable__mode-title">{{ t('advanced.committee.modeExclude') }}</span>
          </label>
          <label class="committee-disable__mode">
            <input
              v-model="mode"
              type="radio"
              value="substitute"
              name="disable-mode"
              class="committee-disable__radio"
            />
            <span class="committee-disable__mode-title">{{ t('advanced.committee.modeSubstitute') }}</span>
          </label>
        </fieldset>

        <div v-if="mode === 'substitute'" class="committee-disable__substitute">
          <div class="committee-disable__field">
            <span id="substitute-committee-assessor-label" class="committee-disable__field-label">
              {{ t('advanced.committee.substituteCommitteeAssessor') }}
            </span>
            <DropdownList
              v-model="substituteId"
              :options="substituteOptions"
              :placeholder="t('advanced.committee.substituteCommitteeAssessor')"
              aria-labelledby="substitute-committee-assessor-label"
            />
          </div>

          <BaseInput
            v-model="substituteName"
            name="substitute-name"
            :label="t('advanced.committee.substituteName')"
            :placeholder="t('advanced.committee.substituteName')"
          />

          <label class="committee-disable__toggle">
            <input
              v-model="priorNotesVisible"
              type="checkbox"
              class="committee-disable__checkbox"
            />
            <span>{{ t('advanced.committee.priorNotesVisible') }}</span>
          </label>

          <p class="committee-disable__hint" role="note">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            {{ t('advanced.committee.priorScoresViewOnly') }}
          </p>
        </div>
      </div>

      <template #footer>
        <BaseButton variant="secondary" @click="modalOpen = false">
          {{ t('actions.cancel') }}
        </BaseButton>
        <BaseButton
          variant="danger"
          icon="fa-solid fa-user-slash"
          :disabled="!canConfirmDisable"
          :loading="disabling === activeCommitteeAssessor?.id"
          @click="confirmDisable"
        >
          {{ t('advanced.committee.confirmDisable') }}
        </BaseButton>
      </template>
    </BaseModal>
  </section>
</template>
