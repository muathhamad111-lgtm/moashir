<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/modal/BaseModal.vue'
import DropdownList from '@/components/ui/DropdownList.vue'
import CommitteeAssessorAssignModal from '@/components/committee/CommitteeAssessorAssignModal.vue'
import { useCommitteeManagement } from '@/composables/useCommittee'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { COMMITTEE_CYCLE_ID } from '@/mocks/fixtures/committee'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()
const { can } = usePermissions()

const canManage = computed(() => can(PERMISSIONS.AWARD_COMMITTEE_MANAGE))

const cycleId = route.query.cycle || COMMITTEE_CYCLE_ID
const cameFromCycles = Boolean(route.query.cycle)

const {
  status,
  errorMessage,
  committeeAssessors,
  available,
  cycleName,
  qualifiedCount,
  canRedistribute,
  busy,
  acting,
  localized,
  load,
  addCommitteeAssessor,
  removeCommitteeAssessor,
  reassign,
  redistribute,
} = useCommitteeManagement(cycleId)

function goToCycles() {
  router.push({ name: ROUTE_NAMES.AWARD_CYCLES })
}

onMounted(load)

const columns = computed(() => {
  const base = [
    { key: 'name', label: t('advanced.committeeManagement.committeeAssessor') },
    { key: 'status', label: t('advanced.committee.committee_assessor_status') },
    { key: 'load', label: t('advanced.committeeManagement.assignmentLoad') },
  ]
  if (canManage.value) base.push({ key: 'actions', label: t('components.actions.label') })
  return base
})

function isActive(committeeAssessor) {
  return committeeAssessor.status !== 'excluded' && committeeAssessor.status !== 'disabled'
}

const addOpen = ref(false)
const addOptions = computed(() => available.value.map((c) => ({ value: c.id, label: localized(c.name) })))

async function onAdd(committeeAssessorId) {
  try {
    await addCommitteeAssessor(committeeAssessorId)
    addOpen.value = false
    toast.success(t('advanced.committeeManagement.toasts.added'))
  } catch {
    toast.error(t('advanced.committeeManagement.toasts.addFailed'))
  }
}

async function onRemove(committeeAssessor) {
  const accepted = await confirm({
    title: t('advanced.committeeManagement.removeTitle', { name: localized(committeeAssessor.name) }),
    message: t('advanced.committeeManagement.removeMessage'),
    confirmLabel: t('advanced.committeeManagement.remove'),
    variant: 'danger',
  })
  if (!accepted) return
  try {
    await removeCommitteeAssessor(committeeAssessor.id)
    toast.success(t('advanced.committeeManagement.toasts.removed'))
  } catch {
    toast.error(t('advanced.committeeManagement.toasts.removeFailed'))
  }
}

const reassignOpen = ref(false)
const reassignSource = ref(null)
const reassignAssessmentId = ref(null)
const reassignTargetId = ref(null)
const reassignError = ref(false)

const reassignEntityOptions = computed(() =>
  (reassignSource.value?.assignments ?? []).map((a) => ({
    value: a.assessmentId,
    label: localized(a.entityName),
  })),
)

const reassignTargetOptions = computed(() =>
  committeeAssessors.value
    .filter((j) => isActive(j) && j.id !== reassignSource.value?.id)
    .map((j) => ({ value: j.id, label: localized(j.name) })),
)

function openReassign(committeeAssessor) {
  reassignSource.value = committeeAssessor
  reassignAssessmentId.value = null
  reassignTargetId.value = null
  reassignError.value = false
  reassignOpen.value = true
}

const canConfirmReassign = computed(
  () => Boolean(reassignAssessmentId.value) && Boolean(reassignTargetId.value),
)

async function confirmReassign() {
  if (!canConfirmReassign.value) {
    reassignError.value = true
    return
  }
  try {
    await reassign(reassignSource.value.id, reassignTargetId.value, reassignAssessmentId.value)
    reassignOpen.value = false
    toast.success(t('advanced.committeeManagement.toasts.reassigned'))
  } catch {
    toast.error(t('advanced.committeeManagement.toasts.reassignFailed'))
  }
}

async function onRedistribute() {
  const accepted = await confirm({
    title: t('advanced.committeeManagement.redistributeTitle'),
    message: t('advanced.committeeManagement.redistributeMessage'),
    confirmLabel: t('advanced.committeeManagement.redistribute'),
    variant: 'primary',
  })
  if (!accepted) return
  try {
    await redistribute()
    toast.success(t('advanced.committeeManagement.toasts.redistributed'))
  } catch {
    toast.error(t('advanced.committeeManagement.toasts.redistributeFailed'))
  }
}
</script>

<template>
  <section class="committee-management">
    <header class="committee-management__header">
      <div>
        <BaseButton
          v-if="cameFromCycles"
          variant="ghost"
          size="sm"
          icon="fa-solid fa-arrow-right-long"
          class="committee-management__back"
          @click="goToCycles"
        >
          {{ t('advanced.committeeManagement.backToCycles') }}
        </BaseButton>
        <p class="committee-management__eyebrow">{{ t('advanced.title') }}</p>
        <h1 class="committee-management__title">{{ t('advanced.committeeManagement.title') }}</h1>
        <p v-if="cycleName" class="committee-management__cycle" role="note">
          <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
          {{ t('advanced.committeeManagement.forCycle', { cycle: localized(cycleName) }) }}
        </p>
        <p class="committee-management__subtitle">{{ t('advanced.committeeManagement.subtitle') }}</p>
      </div>
      <div v-if="canManage && status === 'success'" class="committee-management__toolbar">
        <BaseButton
          variant="secondary"
          icon="fa-solid fa-scale-balanced"
          :disabled="!canRedistribute || busy"
          @click="onRedistribute"
        >
          {{ t('advanced.committeeManagement.redistribute') }}
        </BaseButton>
        <BaseButton
          variant="primary"
          icon="fa-solid fa-user-plus"
          :disabled="busy"
          @click="addOpen = true"
        >
          {{ t('advanced.committeeManagement.addCommitteeAssessor') }}
        </BaseButton>
      </div>
    </header>

    <p v-if="!canManage" class="committee-management__gated" role="note">
      <i class="fa-solid fa-lock" aria-hidden="true"></i>
      {{ t('advanced.committeeManagement.gatedNote') }}
    </p>

    <p v-if="status === 'success'" class="committee-management__meta" role="note">
      <i class="fa-solid fa-list-check" aria-hidden="true"></i>
      {{ t('advanced.committeeManagement.qualifiedEntities', { count: qualifiedCount }) }}
    </p>

    <Card>
      <DataTable
        :columns="columns"
        :items="committeeAssessors"
        :status="status"
        :error-message="errorMessage"
        row-key="id"
        :empty-title="t('advanced.committeeManagement.empty.title')"
        :empty-message="t('advanced.committeeManagement.empty.message')"
        @retry="load"
      >
        <template #cell-name="{ item }">
          <span class="committee-management__committee-assessor">
            <i class="fa-solid fa-user-tie committee-management__committee-assessor-icon" aria-hidden="true"></i>
            {{ localized(item.name) }}
            <Badge v-if="item.substituteFor" variant="muted">
              {{ t('advanced.secretary.review.substituteOf') }}
            </Badge>
          </span>
        </template>

        <template #cell-status="{ item }">
          <StatusBadge :status="item.status" />
        </template>

        <template #cell-load="{ item }">
          <span class="committee-management__load">
            <span class="committee-management__load-count">
              {{ t('advanced.committeeManagement.assignments', { count: item.assignmentCount }) }}
            </span>
            <span class="committee-management__load-detail">
              {{ t('advanced.committeeManagement.completedOf', { completed: item.completedCount, total: item.assignmentCount }) }}
            </span>
          </span>
        </template>

        <template #cell-actions="{ item }">
          <div class="committee-management__actions">
            <BaseButton
              v-if="isActive(item) && item.assignmentCount > 0"
              variant="ghost"
              size="sm"
              icon="fa-solid fa-right-left"
              :disabled="busy"
              @click="openReassign(item)"
            >
              {{ t('advanced.committeeManagement.reassign') }}
            </BaseButton>
            <BaseButton
              variant="secondary"
              size="sm"
              icon="fa-solid fa-user-minus"
              :loading="acting === item.id"
              :disabled="busy"
              @click="onRemove(item)"
            >
              {{ t('advanced.committeeManagement.remove') }}
            </BaseButton>
          </div>
        </template>
      </DataTable>
    </Card>

    <CommitteeAssessorAssignModal
      v-model="addOpen"
      :options="addOptions"
      :busy="busy"
      :title="t('advanced.committeeManagement.addTitle')"
      :lead="t('advanced.committeeManagement.addLead')"
      :field-label="t('advanced.committeeManagement.addLabel')"
      :placeholder="t('advanced.committeeManagement.addPlaceholder')"
      :confirm-label="t('advanced.committeeManagement.addAction')"
      :error-message="t('advanced.committeeManagement.chooseError')"
      :empty-message="t('advanced.committeeManagement.noneAvailable')"
      @confirm="onAdd"
    />

    <BaseModal
      v-model="reassignOpen"
      size="sm"
      :title="t('advanced.committeeManagement.reassignTitle')"
    >
      <div class="committee-management-reassign">
        <p class="committee-management-reassign__lead">
          {{ t('advanced.committeeManagement.reassignLead', { from: localized(reassignSource?.name) }) }}
        </p>
        <div class="committee-management-reassign__field">
          <span id="reassign-entity-label" class="committee-management-reassign__label">
            {{ t('advanced.committeeManagement.reassignEntityLabel') }}
          </span>
          <DropdownList
            v-model="reassignAssessmentId"
            :options="reassignEntityOptions"
            :placeholder="t('advanced.committeeManagement.reassignEntityPlaceholder')"
            aria-labelledby="reassign-entity-label"
          />
        </div>
        <div class="committee-management-reassign__field">
          <span id="reassign-target-label" class="committee-management-reassign__label">
            {{ t('advanced.committeeManagement.reassignToLabel') }}
          </span>
          <DropdownList
            v-model="reassignTargetId"
            :options="reassignTargetOptions"
            :placeholder="t('advanced.committeeManagement.reassignToPlaceholder')"
            aria-labelledby="reassign-target-label"
          />
        </div>
        <p v-if="reassignError" class="committee-management-reassign__error" role="alert">
          <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
          {{ t('advanced.committeeManagement.reassignChooseError') }}
        </p>
      </div>
      <template #footer>
        <BaseButton variant="secondary" :disabled="busy" @click="reassignOpen = false">
          {{ t('actions.cancel') }}
        </BaseButton>
        <BaseButton
          variant="primary"
          icon="fa-solid fa-right-left"
          :loading="busy"
          :disabled="!canConfirmReassign"
          @click="confirmReassign"
        >
          {{ t('advanced.committeeManagement.reassignAction') }}
        </BaseButton>
      </template>
    </BaseModal>
  </section>
</template>
