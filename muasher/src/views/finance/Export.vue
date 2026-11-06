<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue'
import PaymentNotice from '@/components/payments/PaymentNotice.vue'
import { useFinanceExport } from '@/composables/useFinanceExport'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { EXPORT_STATUSES } from '@/constants/finance'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatDate } from '@/utils/datetime'
import { withLatinNumerals } from '@/utils/numerals'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  id: { type: String, required: true },
})

const { t, locale } = useI18n()
const { status, record, errorMessage, isQueued, isReady, reload, requestLink } = useFinanceExport(
  props.id,
)
const downloads = useSignedDownload()

useHead({ title: () => t('finance.export.title') })

const TONES = { queued: 'info', ready: 'success', failed: 'danger', streamed: 'success' }
const ICONS = {
  queued: 'gear',
  ready: 'doc',
  failed: 'warning-triangle',
  streamed: 'doc',
}

const rows = computed(() =>
  new Intl.NumberFormat(withLatinNumerals(locale.value)).format(record.value?.rowCount ?? 0),
)
const createdAt = computed(() =>
  record.value
    ? formatDate(record.value.createdAt, locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Riyadh',
      })
    : '',
)
</script>

<template>
  <section class="finance-page">
    <FinancePageHeader :eyebrow="t('finance.eyebrow')" :title="t('finance.export.title')" />
    <RouterLink class="transaction-detail__back" :to="{ name: ROUTE_NAMES.FINANCE_TRANSACTIONS }">
      <AppIcon name="arrow-left" />
      {{ t('finance.transaction.back') }}
    </RouterLink>
    <Skeleton v-if="status === 'loading'" variant="block" />
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="reload" />
    <EmptyState
      v-else-if="status === 'not-found'"
      icon="doc"
      :title="t('finance.export.notFound')"
    />
    <Card v-else>
      <PaymentNotice
        :tone="TONES[record.status]"
        :icon="ICONS[record.status]"
        :title="t(`finance.export.states.${record.status}.title`)"
        :message="t(`finance.export.states.${record.status}.message`, { rows, createdAt })"
        live
      >
        <template #actions>
          <BaseButton
            v-if="isReady"
            variant="primary"
            icon="download"
            :loading="downloads.busy.file === true"
            @click="downloads.download('file', requestLink)"
          >
            {{ t('finance.export.download') }}
          </BaseButton>
          <BaseButton
            v-else-if="isQueued || record.status === EXPORT_STATUSES.FAILED"
            variant="secondary"
            icon="refresh"
            @click="reload"
          >
            {{ t('finance.export.refresh') }}
          </BaseButton>
        </template>
      </PaymentNotice>
    </Card>
  </section>
</template>
