<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import FieldInput from '@/components/form/FieldInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import FinanceSettingsPreview from '@/components/finance/FinanceSettingsPreview.vue'
import { useFinanceSettings } from '@/composables/useFinanceSettings'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const {
  status,
  errorMessage,
  settings,
  editable,
  limits,
  saving,
  reasonOpen,
  load,
  submit,
  confirmReason,
} = useFinanceSettings()

const currency = computed(() => t('finance.currency.SAR'))

function hintFor(field, hint) {
  return editable.value.has(field) ? hint : `${hint} ${t('finance.settings.readOnly')}`
}

onMounted(load)
</script>

<template>
  <SettingsSection
    :title="t('finance.settings.title')"
    :description="t('finance.settings.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <form
      v-if="settings"
      id="finance-settings-form"
      class="settings-form"
      novalidate
      @submit.prevent="submit"
    >
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('finance.settings.pricesTitle') }}</legend>
        <p class="settings-form__note" role="note">
          <AppIcon name="info-circle" />
          {{ t('finance.settings.pricesNote') }}
        </p>
        <div class="settings-form__grid">
          <FieldInput
            name="accreditedPrice"
            :label="t('finance.settings.accreditedPrice')"
            :hint="hintFor('accreditedPrice', t('finance.settings.priceHint', { currency }))"
            inputmode="decimal"
            input-dir="ltr"
            :disabled="!editable.has('accreditedPrice')"
          />
          <FieldInput
            name="bagPrice"
            :label="t('finance.settings.bagPrice')"
            :hint="hintFor('bagPrice', t('finance.settings.priceHint', { currency }))"
            inputmode="decimal"
            input-dir="ltr"
            :disabled="!editable.has('bagPrice')"
          />
        </div>
      </fieldset>

      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('finance.settings.taxTitle') }}</legend>
        <div class="settings-form__grid">
          <FieldInput
            name="vatRate"
            :label="t('finance.settings.vatRate')"
            :hint="hintFor('vatRate', t('finance.settings.vatHint'))"
            inputmode="decimal"
            input-dir="ltr"
            :disabled="!editable.has('vatRate')"
            :required="editable.has('vatRate')"
          />
          <FieldInput
            name="orderValidityMinutes"
            :label="t('finance.settings.orderValidity')"
            :hint="hintFor('orderValidityMinutes', t('finance.settings.orderValidityHint', limits))"
            inputmode="numeric"
            input-dir="ltr"
            :disabled="!editable.has('orderValidityMinutes')"
            :required="editable.has('orderValidityMinutes')"
          />
        </div>
      </fieldset>

      <fieldset v-if="editable.has('vatRegistrationNumber')" class="settings-form__group">
        <legend class="settings-form__legend">
          {{ t('finance.settings.vatNumberTitle') }}
          <span class="settings-form__badge">
            <AppIcon name="lock" />
            {{ t('finance.settings.adminOnly') }}
          </span>
        </legend>
        <FieldInput
          name="vatRegistrationNumber"
          :label="t('finance.settings.vatNumber')"
          :hint="t('finance.settings.vatNumberHint')"
          inputmode="numeric"
          input-dir="ltr"
          autocomplete="off"
        />
        <p
          v-if="settings.vatRegistrationReminder"
          class="settings-form__note"
          role="note"
          data-testid="vat-number-reminder"
        >
          <AppIcon name="info-circle" />
          {{ t('finance.settings.vatNumberReminder') }}
        </p>
      </fieldset>

      <FinanceSettingsPreview :pricing="settings.pricing" />
    </form>

    <template #actions>
      <BaseButton
        v-if="editable.size"
        type="submit"
        form="finance-settings-form"
        variant="primary"
        :loading="saving"
        icon="save"
      >
        {{ t('actions.save') }}
      </BaseButton>
    </template>

    <ReasonDialog
      v-model="reasonOpen"
      :title="t('finance.settings.reasonTitle')"
      :message="t('finance.settings.reasonMessage')"
      :action="confirmReason"
      action-name="settings.finance.reason"
    />
  </SettingsSection>
</template>
