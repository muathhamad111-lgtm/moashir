<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { useAdvancedResult } from '@/composables/useAdvancedResult'
import { useCertificates } from '@/composables/useCertificates'
import { useToast } from '@/composables/useToast'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const assessmentId = Number(route.params.id)

const { status, errorMessage, result, available, resultKind, feedbackItems, localized, load } =
  useAdvancedResult(assessmentId)
const { downloading, download } = useCertificates()

onMounted(load)

const isWinner = computed(() => (resultKind.value ?? '').includes('winner'))
const resultKindLabel = computed(() =>
  isWinner.value ? t('advanced.result.winner') : t('advanced.result.qualifiedNonWinner'),
)
const overallPercent = computed(() => result.value?.overallPercent ?? 0)
const certificateId = computed(() => result.value?.certificateId ?? null)
const certDownloading = computed(() =>
  certificateId.value ? downloading[certificateId.value] : false,
)

async function onDownloadCertificate() {
  if (!certificateId.value) return
  try {
    const data = await download(certificateId.value)
    if (data?.downloadUrl) toast.success(t('certificates.toasts.downloadReady'))
  } catch {
    toast.error(t('certificates.toasts.downloadFailed'))
  }
}

function goToDashboard() {
  router.push({ name: ROUTE_NAMES.APP_DASHBOARD })
}
</script>

<template>
  <section class="advanced-result">
    <div v-if="status === 'loading'" class="advanced-result__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="5" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <template v-else-if="result">
      <header class="advanced-result__header">
        <div>
          <p class="advanced-result__eyebrow">{{ t('advanced.title') }}</p>
          <h1 class="advanced-result__title">{{ t('advanced.result.title') }}</h1>
        </div>
        <StatusBadge :status="result.status" />
      </header>

      <div v-if="!available" class="advanced-result__pending">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <h2 class="advanced-result__pending-title">{{ t('advanced.result.notYetAvailable') }}</h2>
        <p class="advanced-result__pending-message">{{ t('advanced.result.trackingHint') }}</p>
        <StatusBadge :status="result.status" />
        <BaseButton variant="primary" icon="fa-solid fa-gauge-high" @click="goToDashboard">
          {{ t('advanced.result.backToDashboard') }}
        </BaseButton>
      </div>

      <template v-else>
        <div class="advanced-result__summary">
          <Card>
            <div class="advanced-result__score">
              <div
                class="advanced-result__gauge"
                role="img"
                :aria-label="t('advanced.result.percentAria', { percent: overallPercent })"
              >
                <span class="advanced-result__gauge-value">{{ overallPercent }}%</span>
              </div>
              <div class="advanced-result__score-detail">
                <Badge :variant="isWinner ? 'success' : 'info'">
                  <i
                    :class="isWinner ? 'fa-solid fa-trophy' : 'fa-solid fa-medal'"
                    aria-hidden="true"
                  ></i>
                  {{ resultKindLabel }}
                </Badge>
                <p class="advanced-result__score-hint">{{ t('advanced.result.overallScore') }}</p>
              </div>
            </div>
          </Card>

          <div class="advanced-result__actions-panel">
            <BaseButton
              v-if="certificateId"
              variant="primary"
              icon="fa-solid fa-certificate"
              :loading="certDownloading"
              @click="onDownloadCertificate"
            >
              {{ t('advanced.result.download') }}
            </BaseButton>
          </div>
        </div>

        <div class="advanced-result__confidentiality" role="note">
          <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
          <p>{{ t('advanced.result.feedbackHint') }}</p>
        </div>

        <section class="advanced-result__feedback">
          <div class="advanced-result__feedback-head">
            <h2 class="advanced-result__section-title">{{ t('advanced.result.feedbackTitle') }}</h2>
            <span class="advanced-result__feedback-source">
              <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
              {{ t('advanced.result.feedbackSource') }}
            </span>
          </div>

          <p v-if="!feedbackItems.length" class="advanced-result__feedback-empty">
            {{ t('advanced.result.notYetAvailable') }}
          </p>

          <ul v-else class="advanced-result__items">
            <li
              v-for="item in feedbackItems"
              :key="item.subCriterionId"
              class="advanced-result__item"
            >
              <Card>
                <div class="advanced-result__item-head">
                  <h3 class="advanced-result__item-title">{{ localized(item.name) }}</h3>
                  <Badge variant="info">{{ item.scorePercent }}%</Badge>
                </div>
                <div
                  class="advanced-result__bar-track"
                  role="progressbar"
                  :aria-valuenow="item.scorePercent"
                  :aria-valuemin="0"
                  :aria-valuemax="100"
                  :aria-label="localized(item.name)"
                >
                  <div
                    class="advanced-result__bar"
                    :style="{ inlineSize: `${item.scorePercent}%` }"
                  ></div>
                </div>
                <p v-if="item.comment" class="advanced-result__item-note">
                  <span class="advanced-result__note-label">
                    {{ t('advanced.result.perCriterion') }}
                  </span>
                  {{ localized(item.comment) }}
                </p>
              </Card>
            </li>
          </ul>
        </section>

        <footer class="advanced-result__footer">
          <BaseButton variant="ghost" icon="fa-solid fa-gauge-high" @click="goToDashboard">
            {{ t('advanced.result.backToDashboard') }}
          </BaseButton>
        </footer>
      </template>
    </template>
  </section>
</template>
