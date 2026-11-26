<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import CopyRow from './CopyRow.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useClipboard } from '@/composables/useClipboard'
import { formatIban, isExampleIban, normalizeIban } from '@/utils/payments/iban'
import { formatAmount } from '@/utils/payments/amountFormat'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  account: { type: Object, required: true },
  amount: { type: Object, default: null },
  unifiedNumber: { type: String, default: null },
})

const { t, locale } = useI18n()
const clipboard = useClipboard()

const ibanLabel = computed(() =>
  isExampleIban(props.account.iban)
    ? t('portal.transfer.details.ibanExample')
    : t('portal.transfer.details.iban'),
)
const rows = computed(() =>
  [
    {
      key: 'bank',
      label: t('portal.transfer.details.bank'),
      value: localizedText(props.account.bankName, locale.value),
      latin: false,
    },
    {
      key: 'holder',
      label: t('portal.transfer.details.holder'),
      value: localizedText(props.account.accountHolderName, locale.value),
      latin: false,
    },
    {
      key: 'iban',
      label: ibanLabel.value,
      value: normalizeIban(props.account.iban),
      display: formatIban(props.account.iban),
      wide: true,
    },
    props.amount && {
      key: 'amount',
      label: t('portal.transfer.details.amount'),
      value: props.amount.total,
      display: formatAmount(props.amount.total, locale.value),
    },
    props.unifiedNumber && {
      key: 'unifiedNumber',
      label: t('portal.transfer.details.unifiedNumber'),
      value: props.unifiedNumber,
    },
  ].filter(Boolean),
)
const note = computed(() => localizedText(props.account.payerNote, locale.value))
</script>

<template>
  <div class="transfer-details">
    <dl class="transfer-details__rows">
      <CopyRow
        v-for="row in rows"
        :key="row.key"
        :label="row.label"
        :value="row.value"
        :display="row.display"
        :latin="row.latin !== false"
        :wide="row.wide"
        :copied="clipboard.copiedKey.value === row.key"
        :data-copy="row.key"
        @copy="clipboard.copy(row.key, row.value)"
      />
    </dl>
    <p class="transfer-details__status" role="status" aria-live="polite">
      <template v-if="clipboard.copiedKey.value">{{
        t('portal.transfer.copy.announced', {
          label: rows.find((row) => row.key === clipboard.copiedKey.value)?.label ?? '',
        })
      }}</template>
      <template v-else-if="clipboard.failedKey.value">{{ t('integrations.copy.failed') }}</template>
    </p>
    <ul class="transfer-details__guidance">
      <li>
        <AppIcon name="shield-info" />
        {{ t('portal.transfer.guidance.fullAmount') }}
      </li>
      <li v-if="unifiedNumber">
        <AppIcon name="shield-info" />
        {{ t('portal.transfer.guidance.unifiedNumber') }}
      </li>
      <li>
        <AppIcon name="shield-info" />
        {{ t('portal.transfer.guidance.platformOnly') }}
      </li>
      <li v-if="note">
        <AppIcon name="question-circle" />
        {{ note }}
      </li>
    </ul>
  </div>
</template>
