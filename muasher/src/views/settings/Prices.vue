<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import PathPriceRow from '@/components/settings/prices/PathPriceRow.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { usePathPrices } from '@/composables/usePathPrices'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { formatRate } from '@/utils/payments/amountFormat'

const { t, locale } = useI18n()
const { status, errorMessage, settings, load, replaceRow } = usePathPrices()
const { prompt, reasonExtra } = useSuperAdminReason()
const rate = computed(() => formatRate(settings.value?.vatRate ?? '0', locale.value))

useHead({ title: () => t('settings.prices.title') })

onMounted(load)
</script>

<template>
  <SettingsSection
    :title="t('settings.prices.title')"
    :description="t('settings.prices.description')"
    :status="status"
    :error-message="errorMessage"
    @retry="load"
  >
    <template v-if="settings">
      <p class="settings-form__note" role="note">
        <AppIcon name="info-circle" />
        {{ t('settings.prices.vatNote', { rate }) }}
      </p>
      <div class="path-prices" data-testid="path-prices">
        <PathPriceRow
          v-for="row in settings.rows"
          :key="row.key"
          :row="row"
          :limits="settings.limits"
          :reason-extra="reasonExtra"
          @saved="replaceRow"
        />
      </div>
    </template>
    <ReasonDialog
      v-model="prompt.open.value"
      :title="t('settings.prices.reasonTitle')"
      :submit-label="t('actions.confirm')"
      :action="prompt.provide"
      action-name="settings.prices.reason"
    />
  </SettingsSection>
</template>
