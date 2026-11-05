<script setup>
import { useI18n } from 'vue-i18n'
import MoneyAmount from '@/components/payments/MoneyAmount.vue'
import { localizedText } from '@/utils/localizedText'

defineProps({
  targets: { type: Array, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <dl v-if="targets.length === 1" class="transfer-targets">
    <div class="transfer-targets__row">
      <dt>{{ t('finance.transfers.fields.reference') }}</dt>
      <dd>
        <bdi dir="ltr">{{ targets[0].reference }}</bdi>
      </dd>
    </div>
    <div class="transfer-targets__row">
      <dt>{{ t('finance.transfers.fields.entity') }}</dt>
      <dd>{{ localizedText(targets[0].entityName, locale) }}</dd>
    </div>
    <div class="transfer-targets__row">
      <dt>{{ t('finance.transfers.fields.amountDue') }}</dt>
      <dd><MoneyAmount :value="targets[0].total" :currency="targets[0].currency" strong /></dd>
    </div>
  </dl>
  <ul v-else class="transfer-targets transfer-targets--list">
    <li v-for="target in targets" :key="target.id" class="transfer-targets__row">
      <span>
        <bdi dir="ltr">{{ target.reference }}</bdi>
        <span aria-hidden="true"> · </span>
        {{ localizedText(target.entityName, locale) }}
      </span>
      <MoneyAmount :value="target.total" :currency="target.currency" strong />
    </li>
  </ul>
</template>
