<script setup>
import { useI18n } from 'vue-i18n'
import { formatIban } from '@/utils/payments/iban'
import { localizedText } from '@/utils/localizedText'

defineProps({
  account: { type: Object, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <dl class="bank-account-payee">
    <div class="bank-account-payee__row">
      <dt>{{ t('settings.bankAccounts.columns.bank') }}</dt>
      <dd>{{ localizedText(account.bankName, locale) }}</dd>
    </div>
    <div class="bank-account-payee__row">
      <dt>{{ t('settings.bankAccounts.columns.holder') }}</dt>
      <dd>{{ localizedText(account.accountHolderName, locale) }}</dd>
    </div>
    <div class="bank-account-payee__row">
      <dt>{{ t('settings.bankAccounts.columns.iban') }}</dt>
      <dd>
        <bdi dir="ltr" class="bank-account-payee__iban">{{ formatIban(account.iban) }}</bdi>
      </dd>
    </div>
  </dl>
</template>
