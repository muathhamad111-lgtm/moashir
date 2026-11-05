<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import CriteriaTree from '@/components/criteria/CriteriaTree.vue'
import ScoreInput from '@/components/assessment/ScoreInput.vue'
import { useCommitteeScore } from '@/composables/useCommittee'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'

const COMMITTEE_CYCLE_ID = 'cycle-committee-2026'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()
const auth = useAuthStore()

const assessmentId = Number(route.params.assessmentId)
const committeeAssessorId = auth.user?.id ?? 'jur-9404'

const {
  status,
  errorMessage,
  tree,
  scaleKind,
  committeeAssessorStatus,
  entityName,
  scores,
  notes,
  prior,
  saving,
  completing,
  localized,
  load,
  score,
  saveNotes,
  complete,
} = useCommitteeScore(COMMITTEE_CYCLE_ID, committeeAssessorId, assessmentId)

const activeMainId = ref(null)
const noteDrafts = ref({})

onMounted(async () => {
  await load()
  if (tree.value.length) activeMainId.value = tree.value[0].id
})

const isCompleted = computed(() => committeeAssessorStatus.value === 'completed')
const isReadOnly = computed(() => isCompleted.value)
const hasPrior = computed(() => Boolean(prior.value))

const treeNodes = computed(() =>
  tree.value.map((main) => ({
    id: main.id,
    label: localized(main.name),
    meta: t('advanced.form.tree.subCount', { count: main.subs.length }),
    children: main.subs.map((sub) => ({
      id: sub.id,
      label: localized(sub.name),
      meta: hasNote(sub.id)
        ? t('advanced.committee.score.scored')
        : t('advanced.committee.score.pending'),
    })),
  })),
)

const activeMain = computed(
  () => tree.value.find((main) => main.id === activeMainId.value) ?? tree.value[0] ?? null,
)

function selectMain({ node, parent }) {
  activeMainId.value = parent ? parent.id : node.id
}

function hasNote(subId) {
  return Boolean((notes[subId] ?? '').trim())
}

function noteValue(subId) {
  return noteDrafts.value[subId] ?? notes[subId] ?? ''
}

function priorNote(subId) {
  return prior.value?.notes?.find((n) => n.subCriterionId === subId)?.text ?? ''
}

function subRatio(sub) {
  return scores.subs[sub.id] ?? 0
}

function pointRatio(pointId) {
  return scores.points[pointId] ?? 0
}

async function onScoreSub(sub, ratio) {
  try {
    await score({ subCriterionId: sub.id }, ratio)
  } catch {
    toast.error(t('advanced.committee.toasts.completeFailed'))
  }
}

async function onScorePoint(pointId, ratio) {
  try {
    await score({ pointId }, ratio)
  } catch {
    toast.error(t('advanced.committee.toasts.completeFailed'))
  }
}

function setNoteDraft(subId, value) {
  noteDrafts.value = { ...noteDrafts.value, [subId]: value }
}

async function onSaveNote(subId) {
  const text = noteValue(subId)
  try {
    await saveNotes(subId, text)
  } catch {
    toast.error(t('advanced.committee.toasts.completeFailed'))
  }
}

async function onComplete() {
  const accepted = await confirm({
    title: t('advanced.committee.score.complete'),
    message: t('advanced.committee.score.confirmComplete'),
    confirmLabel: t('advanced.committee.score.complete'),
    variant: 'primary',
  })
  if (!accepted) return
  try {
    const result = await complete()
    if (result) toast.success(t('advanced.committee.toasts.completed'))
  } catch {
    toast.error(t('advanced.committee.toasts.completeFailed'))
  }
}

function backToQueue() {
  router.push({ name: ROUTE_NAMES.AWARD_COMMITTEE })
}
</script>

<template>
  <section class="committee-score">
    <header class="committee-score__header">
      <div>
        <button type="button" class="committee-score__back" @click="backToQueue">
          <i class="fa-solid fa-arrow-right-long committee-score__back-icon" aria-hidden="true"></i>
          {{ t('advanced.committee.backToQueue') }}
        </button>
        <p class="committee-score__eyebrow">{{ t('advanced.committee.title') }}</p>
        <h1 class="committee-score__title">{{ t('advanced.committee.score.title') }}</h1>
        <p v-if="entityName" class="committee-score__entity">{{ localized(entityName) }}</p>
      </div>
      <StatusBadge :status="committeeAssessorStatus" />
    </header>

    <div v-if="status === 'loading'" class="committee-score__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="6" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <EmptyState
      v-else-if="status === 'empty'"
      icon="fa-regular fa-clipboard"
      :title="t('advanced.form.empty.title')"
      :message="t('advanced.form.empty.message')"
    />

    <template v-else>
      <p class="committee-score__confidential" role="note">
        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
        {{ t('advanced.committee.score.confidential') }}
      </p>

      <div v-if="hasPrior" class="committee-score__prior-banner" role="note">
        <i class="fa-solid fa-eye" aria-hidden="true"></i>
        <div>
          <p class="committee-score__prior-title">
            {{ t('advanced.committee.score.readOnlyPrior') }}
          </p>
          <p class="committee-score__prior-hint">
            {{ t('advanced.committee.priorScoresViewOnly') }}
          </p>
        </div>
      </div>

      <div v-if="isCompleted" class="committee-score__done" role="note">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <p>{{ t('advanced.committee.score.completed') }}</p>
      </div>

      <div class="committee-score__layout">
        <aside class="committee-score__nav" :aria-label="t('advanced.form.navLabel')">
          <CriteriaTree :nodes="treeNodes" :active-id="activeMainId" @select="selectMain" />
        </aside>

        <div class="committee-score__panel">
          <Card v-if="activeMain">
            <template #header>
              <div class="committee-score__main-head">
                <h2 class="card__title-text">{{ localized(activeMain.name) }}</h2>
                <Badge variant="info">
                  {{ t('advanced.form.tree.subCount', { count: activeMain.subs.length }) }}
                </Badge>
              </div>
            </template>

            <p v-if="activeMain.description" class="committee-score__main-desc">
              {{ localized(activeMain.description) }}
            </p>

            <ul class="committee-score__subs">
              <li v-for="sub in activeMain.subs" :key="sub.id" class="committee-score__sub">
                <div class="committee-score__sub-head">
                  <h3 class="committee-score__sub-title">{{ localized(sub.name) }}</h3>
                  <Badge :variant="sub.evaluationType === 'guiding-points' ? 'muted' : 'info'">
                    {{
                      sub.evaluationType === 'guiding-points'
                        ? t('advanced.form.tree.guidance', { count: sub.points.length })
                        : t('advanced.form.tree.direct')
                    }}
                  </Badge>
                </div>
                <p v-if="sub.description" class="committee-score__sub-desc">
                  {{ localized(sub.description) }}
                </p>

                <div v-if="hasPrior" class="committee-score__prior">
                  <p class="committee-score__prior-label">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                    {{ t('advanced.committee.score.readOnlyPrior') }}
                  </p>
                  <p v-if="priorNote(sub.id)" class="committee-score__prior-note">
                    {{ priorNote(sub.id) }}
                  </p>
                </div>

                <div class="committee-score__scoring">
                  <template v-if="sub.evaluationType === 'guiding-points' && sub.points.length">
                    <p class="committee-score__scoring-hint">
                      <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                      {{ t('advanced.form.guidance') }}
                    </p>
                    <ul class="committee-score__points">
                      <li
                        v-for="point in sub.points"
                        :key="point.id"
                        class="committee-score__point"
                      >
                        <p class="committee-score__point-desc">
                          {{ localized(point.description) }}
                        </p>
                        <ScoreInput
                          :model-value="pointRatio(point.id)"
                          :scale-kind="point.scaleKind || scaleKind"
                          :label="localized(point.description)"
                          :disabled="isReadOnly"
                          @update:model-value="onScorePoint(point.id, $event)"
                        />
                      </li>
                    </ul>
                  </template>
                  <ScoreInput
                    v-else
                    :model-value="subRatio(sub)"
                    :scale-kind="scaleKind"
                    :label="localized(sub.name)"
                    :disabled="isReadOnly"
                    @update:model-value="onScoreSub(sub, $event)"
                  />
                </div>

                <BaseTextarea
                  :model-value="noteValue(sub.id)"
                  :label="t('advanced.committee.score.note')"
                  :name="`committee-note-${sub.id}`"
                  :rows="3"
                  :placeholder="t('advanced.committee.score.notePlaceholder')"
                  :disabled="isReadOnly"
                  @update:model-value="setNoteDraft(sub.id, $event)"
                  @blur="onSaveNote(sub.id)"
                />
              </li>
            </ul>
          </Card>
        </div>
      </div>

      <footer v-if="!isReadOnly" class="committee-score__actions">
        <span class="committee-score__saving" role="status">
          <template v-if="saving">
            <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
            {{ t('advanced.committee.score.saving') }}
          </template>
        </span>
        <BaseButton
          variant="primary"
          icon="fa-solid fa-circle-check"
          :loading="completing"
          @click="onComplete"
        >
          {{ t('advanced.committee.score.complete') }}
        </BaseButton>
      </footer>
    </template>
  </section>
</template>
