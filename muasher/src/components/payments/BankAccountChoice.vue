<script setup>
import { useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ibanLast4 } from '@/utils/payments/iban'
import { localizedText } from '@/utils/localizedText'

defineProps({
  accounts: { type: Array, required: true },
  modelValue: { type: String, default: null },
  unavailableId: { type: String, default: null },
  labelledby: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t, locale } = useI18n()
const groupName = `transfer-account-${useId()}`
</script>

<template>
  <div class="account-choice" role="radiogroup" :aria-labelledby="labelledby">
    <label
      v-for="account in accounts"
      :key="account.id"
      class="account-card"
      :class="{
        'is-selected': modelValue === account.id,
        'is-unavailable': unavailableId === account.id,
      }"
      :data-account="account.id"
    >
      <input
        class="account-card__input"
        type="radio"
        :name="groupName"
        :value="account.id"
        :checked="modelValue === account.id"
        @change="emit('update:modelValue', account.id)"
      />
      <span class="account-card__radio" aria-hidden="true"></span>
      <span class="account-card__text">
        <span class="account-card__bank">{{ localizedText(account.bankName, locale) }}</span>
        <span class="account-card__holder">{{
          localizedText(account.accountHolderName, locale)
        }}</span>
        <span class="account-card__last4">
          {{ t('portal.transfer.account.endsWith', { last4: ibanLast4(account.iban) }) }}
        </span>
        <span v-if="unavailableId === account.id" class="account-card__warning">
          <AppIcon name="warning-triangle" />
          {{ t('portal.transfer.account.unavailable') }}
        </span>
      </span>
      <span class="account-card__icon" aria-hidden="true"><AppIcon name="sar" /></span>
    </label>
  </div>
</template>
