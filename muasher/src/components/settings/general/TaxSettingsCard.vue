<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import FieldInput from '@/components/form/FieldInput.vue'
import FieldDigits from '@/components/form/FieldDigits.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import SettingsCardState from './SettingsCardState.vue'
import { useFinanceSettings } from '@/composables/useFinanceSettings'
import { formatRate } from '@/utils/payments/amountFormat'

const VAT_NUMBER_DIGITS = 15

const { t, locale } = useI18n()
const {
  status,
  errorMessage,
  settings,
  editable,
  saving,
  reasonOpen,
  load,
  submit,
  confirmReason,
} = useFinanceSettings()

onMounted(load)
</script>

<template>
  <SettingsCardState :status="status" :error-message="errorMessage" @retry="load">
    <form
      v-if="settings"
      class="settings-form general-settings__card"
      data-testid="tax-settings"
      novalidate
      @submit.prevent="submit"
    >
      <fieldset class="settings-form__group">
        <legend class="settings-form__legend">{{ t('settings.general.tax.title') }}</legend>
        <!-- A div, not a p: a p right after the legend is laid out as the section description. -->
        <div
          v-if="!editable.has('vatRate')"
          class="settings-form__note"
          role="note"
          data-testid="vat-rate-statement"
        >
          <AppIcon name="info-circle" />
          <i18n-t keypath="settings.general.tax.vatRateStatement" scope="global" tag="span">
            <template #rate>
              <bdi dir="ltr">{{
                t('settings.general.tax.vatRatePercent', {
                  rate: formatRate(settings.vatRate, locale),
                })
              }}</bdi>
            </template>
          </i18n-t>
        </div>
        <div class="settings-form__grid">
          <FieldDigits
            v-if="editable.has('vatRegistrationNumber')"
            name="vatRegistrationNumber"
            :label="t('settings.general.tax.vatNumber')"
            :maxlength="VAT_NUMBER_DIGITS"
          />
          <FieldInput
            v-if="editable.has('vatRate')"
            name="vatRate"
            :label="t('settings.general.tax.vatRate')"
            :hint="t('settings.general.tax.vatRateSuperAdminHint')"
            inputmode="decimal"
            input-dir="ltr"
            required
          />
          <FieldInput
            name="orderValidityMinutes"
            :label="t('settings.general.tax.orderValidity')"
            inputmode="numeric"
            input-dir="ltr"
            :disabled="!editable.has('orderValidityMinutes')"
            :required="editable.has('orderValidityMinutes')"
          />
        </div>
      </fieldset>
      <div v-if="editable.size" class="general-settings__actions">
        <BaseButton type="submit" variant="primary" :loading="saving" icon="save">
          {{ t('actions.save') }}
        </BaseButton>
      </div>
    </form>
    <ReasonDialog
      v-model="reasonOpen"
      :title="t('finance.settings.reasonTitle')"
      :action="confirmReason"
      action-name="settings.finance.reason"
    />
  </SettingsCardState>
</template>
