<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { object as yupObject, string as yupString } from 'yup'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import FormModal from '@/components/modal/FormModal.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import { useSecretary } from '@/composables/useCommittee'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { formatDate } from '@/utils/datetime'
import { ROUTE_NAMES } from '@/constants/routeNames'

const COMMITTEE_CYCLE_ID = 'cycle-committee-2026'

const { t, locale } = useI18n()
const router = useRouter()
const { confirm } = useConfirm()
const toast = useToast()

function goToReview() {
  router.push({ name: ROUTE_NAMES.AWARD_SECRETARY_REVIEW })
}

function goToResults() {
  router.push({ name: ROUTE_NAMES.AWARD_RESULTS })
}

const { status, errorMessage, pending, deciding, localized, load, approve, returnToCommittee } =
  useSecretary(COMMITTEE_CYCLE_ID)

onMounted(load)

function computedLabel(value) {
  if (!value) return '—'
  return formatDate(value, locale.value, { dateStyle: 'medium' })
}

async function onApprove(row) {
  const accepted = await confirm({
    title: t('advanced.secretary.approve'),
    message: t('advanced.secretary.confirmApprove', {
      name: localized(row.entityName),
      average: row.average,
    }),
    confirmLabel: t('advanced.secretary.approve'),
    variant: 'primary',
  })
  if (!accepted) return
  try {
    await approve(row.assessmentId)
    toast.success(t('advanced.secretary.toasts.approved'))
  } catch {
    toast.error(t('advanced.secretary.toasts.approveFailed'))
  }
}

const returnModalOpen = ref(false)
const activeRow = ref(null)

const schema = computed(() =>
  yupObject({
    noteAr: yupString()
      .required(t('validation.required'))
      .min(4, t('validation.minChars', { min: 4 })),
    noteEn: yupString()
      .required(t('validation.required'))
      .min(4, t('validation.minChars', { min: 4 })),
  }),
)

const form = useValidatedForm({
  schema,
  initialValues: { noteAr: '', noteEn: '' },
  action: 'secretary.return.submit',
  submit: async (values) => {
    if (!activeRow.value) return
    await returnToCommittee(activeRow.value.assessmentId, { ar: values.noteAr, en: values.noteEn })
    returnModalOpen.value = false
    toast.success(t('advanced.secretary.toasts.returned'))
  },
})

const [noteAr] = form.defineField('noteAr')
const [noteEn] = form.defineField('noteEn')

function openReturn(row) {
  activeRow.value = row
  form.resetForm({ values: { noteAr: '', noteEn: '' } })
  returnModalOpen.value = true
}
</script>

<template>
  <section class="secretary">
    <header class="secretary__header">
      <div>
        <p class="secretary__eyebrow">{{ t('advanced.title') }}</p>
        <h1 class="secretary__title">{{ t('advanced.secretary.title') }}</h1>
        <p class="secretary__subtitle">{{ t('advanced.secretary.subtitle') }}</p>
      </div>
      <div class="secretary__toolbar">
        <BaseButton variant="secondary" icon="fa-solid fa-users-viewfinder" @click="goToReview">
          {{ t('advanced.secretary.review.openReview') }}
        </BaseButton>
        <BaseButton variant="primary" icon="fa-solid fa-trophy" @click="goToResults">
          {{ t('advanced.secretary.openResults') }}
        </BaseButton>
      </div>
    </header>

    <div v-if="status === 'loading'" class="secretary__loading">
      <Skeleton variant="block" :count="2" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <EmptyState
      v-else-if="status === 'empty'"
      icon="fa-regular fa-circle-check"
      :title="t('advanced.secretary.empty.title')"
      :message="t('advanced.secretary.empty.message')"
    />

    <ul v-else class="secretary__list">
      <li v-for="row in pending" :key="row.assessmentId" class="secretary__item">
        <Card>
          <div class="secretary__row">
            <div class="secretary__entity">
              <h2 class="secretary__entity-name">{{ localized(row.entityName) }}</h2>
              <p class="secretary__meta">
                <span class="secretary__committee-assessors">
                  <i class="fa-solid fa-users" aria-hidden="true"></i>
                  {{
                    t('advanced.secretary.committeeAssessorCount', {
                      count: row.committeeAssessorCount,
                    })
                  }}
                </span>
                <span class="secretary__computed">
                  <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                  {{ t('advanced.secretary.computedAt') }}: {{ computedLabel(row.computedAt) }}
                </span>
              </p>
            </div>

            <div
              class="secretary__average"
              role="group"
              :aria-label="t('advanced.secretary.average')"
            >
              <span class="secretary__average-value">{{ row.average }}</span>
              <span class="secretary__average-label">{{ t('advanced.secretary.average') }}</span>
            </div>

            <div class="secretary__actions">
              <BaseButton
                variant="secondary"
                icon="fa-solid fa-rotate-left"
                :loading="deciding === row.assessmentId"
                @click="openReturn(row)"
              >
                {{ t('advanced.secretary.return') }}
              </BaseButton>
              <BaseButton
                variant="primary"
                icon="fa-solid fa-circle-check"
                :loading="deciding === row.assessmentId"
                @click="onApprove(row)"
              >
                {{ t('advanced.secretary.approve') }}
              </BaseButton>
            </div>
          </div>
        </Card>
      </li>
    </ul>

    <FormModal
      v-model="returnModalOpen"
      size="md"
      :busy="form.submitting.value"
      :title="t('advanced.secretary.returnTitle')"
      :submit-label="t('advanced.secretary.confirmReturn')"
      @submit="form.onSubmit"
    >
      <div class="secretary-return">
        <p class="secretary-return__intro" role="note">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          {{ t('advanced.secretary.returnMessage') }}
        </p>
        <BaseTextarea
          v-model="noteAr"
          name="return-note-ar"
          :rows="3"
          :label="t('advanced.secretary.generalNoteAr')"
          :placeholder="t('advanced.secretary.generalNotePlaceholder')"
          :error="form.errors.value.noteAr"
          required
        />
        <BaseTextarea
          v-model="noteEn"
          name="return-note-en"
          :rows="3"
          :label="t('advanced.secretary.generalNoteEn')"
          :placeholder="t('advanced.secretary.generalNotePlaceholder')"
          :error="form.errors.value.noteEn"
          required
        />
      </div>
    </FormModal>
  </section>
</template>
