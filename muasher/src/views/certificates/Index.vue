<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import { useCertificates } from '@/composables/useCertificates'
import { useToast } from '@/composables/useToast'
import { withLatinNumerals } from '@/utils/numerals'

const { t, locale } = useI18n()
const toast = useToast()

const { status, errorMessage, certificates, downloading, localized, load, download } = useCertificates()

onMounted(load)

async function onDownload(certificate) {
  try {
    const data = await download(certificate.id)
    if (data?.downloadUrl) toast.success(t('certificates.toasts.downloadReady'))
  } catch {
    toast.error(t('certificates.toasts.downloadFailed'))
  }
}

function issuedDate(certificate) {
  if (!certificate.issuedAt) return '—'
  return new Intl.DateTimeFormat(withLatinNumerals(locale.value === 'ar' ? 'ar-SA' : 'en-GB'), {
    dateStyle: 'medium',
  }).format(new Date(certificate.issuedAt))
}
</script>

<template>
  <section class="certificates">
    <header class="certificates__header">
      <div>
        <p class="certificates__eyebrow">{{ t('certificates.eyebrow') }}</p>
        <h1 class="certificates__title">{{ t('certificates.title') }}</h1>
        <p class="certificates__subtitle">{{ t('certificates.subtitle') }}</p>
      </div>
    </header>

    <div v-if="status === 'loading'" class="certificates__loading">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="3" />
    </div>

    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />

    <EmptyState
      v-else-if="status === 'empty'"
      icon="fa-solid fa-certificate"
      :title="t('certificates.empty.title')"
      :message="t('certificates.empty.message')"
    />

    <ul v-else class="certificates__list">
      <li v-for="certificate in certificates" :key="certificate.id" class="certificates__item">
        <Card>
          <div class="certificates__card">
            <div class="certificates__badge" aria-hidden="true">
              <i class="fa-solid fa-award"></i>
            </div>
            <div class="certificates__meta">
              <div class="certificates__meta-head">
                <h2 class="certificates__name">{{ localized(certificate.entityName) }}</h2>
                <Badge variant="success">{{ t(`certificates.kinds.${certificate.kind}`) }}</Badge>
              </div>
              <p class="certificates__detail">
                <span>{{ t('certificates.scoreLabel', { score: certificate.scorePercent }) }}</span>
                <span class="certificates__dot" aria-hidden="true">•</span>
                <span>{{ t('certificates.issuedOn', { date: issuedDate(certificate) }) }}</span>
              </p>
            </div>
            <div class="certificates__action">
              <BaseButton
                variant="primary"
                icon="fa-solid fa-download"
                :loading="downloading[certificate.id]"
                @click="onDownload(certificate)"
              >
                {{ t('certificates.download') }}
              </BaseButton>
            </div>
          </div>
        </Card>
      </li>
    </ul>
  </section>
</template>
