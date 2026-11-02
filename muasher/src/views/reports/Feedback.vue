<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import { useFeedbackReport } from '@/composables/useFeedbackReport'
import { useToast } from '@/composables/useToast'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const assessmentId = Number(route.params.assessmentId)

const { status, errorMessage, report, exporting, isAvailable, localized, load, exportReport } =
  useFeedbackReport(assessmentId)

onMounted(load)

async function onExport() {
  try {
    const data = await exportReport()
    if (data?.exportUrl) toast.success(t('feedback.toasts.exportReady'))
  } catch {
    toast.error(t('feedback.toasts.exportFailed'))
  }
}

function goToResult() {
  router.push({ name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_RESULT, params: { id: assessmentId } })
}
</script>

<template>
  <section class="feedback-report">
    <header class="feedback-report__header">
      <div>
        <p class="feedback-report__eyebrow">{{ t('feedback.eyebrow') }}</p>
        <h1 class="feedback-report__title">{{ t('feedback.title') }}</h1>
        <p class="feedback-report__subtitle">{{ t('feedback.subtitle') }}</p>
      </div>
      <StatusBadge v-if="report" :status="report.status" />
    </header>

    <div v-if="status === 'loading'" class="feedback-report__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="5" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <template v-else-if="report">
      <EmptyState
        v-if="!isAvailable"
        icon="fa-regular fa-hourglass"
        :title="t('feedback.notYet.title')"
        :message="t('feedback.notYet.message')"
      />

      <template v-else>
        <div class="feedback-report__toolbar">
          <p class="feedback-report__confidentiality">
            <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
            {{ t('feedback.confidentialityNote') }}
          </p>
          <BaseButton
            variant="primary"
            icon="fa-solid fa-file-export"
            :loading="exporting"
            @click="onExport"
          >
            {{ t('feedback.export') }}
          </BaseButton>
        </div>

        <ul class="feedback-report__items">
          <li v-for="item in report.items" :key="item.subCriterionId" class="feedback-report__item">
            <Card>
              <div class="feedback-report__item-head">
                <h2 class="feedback-report__item-title">{{ localized(item.name) }}</h2>
                <Badge variant="info">{{ item.scorePercent }}%</Badge>
              </div>
              <p class="feedback-report__comment">
                <span class="feedback-report__comment-label">{{ t('feedback.commentLabel') }}</span>
                {{ localized(item.comment) }}
              </p>
            </Card>
          </li>
        </ul>

        <footer class="feedback-report__footer">
          <BaseButton variant="ghost" icon="fa-solid fa-arrow-right-long" @click="goToResult">
            {{ t('feedback.backToResult') }}
          </BaseButton>
        </footer>
      </template>
    </template>
  </section>
</template>
