<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import PathPriceRow from '@/components/settings/prices/PathPriceRow.vue'
import { usePathPrices } from '@/composables/usePathPrices'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'
import { formatRate } from '@/utils/payments/amountFormat'

const props = defineProps({
  priceKey: { type: String, required: true },
})

const { t, locale } = useI18n()
const { status, errorMessage, settings, load, replaceRow } = usePathPrices()
const { prompt, reasonExtra } = useSuperAdminReason()
const row = computed(() => settings.value?.rows.find((item) => item.key === props.priceKey))
const rate = computed(() => formatRate(settings.value?.vatRate ?? '0', locale.value))

onMounted(load)
</script>

<template>
  <section class="path-settings__card" :aria-label="t('settings.paths.priceTitle')">
    <h2 class="path-settings__card-title">{{ t('settings.paths.priceTitle') }}</h2>
    <Skeleton v-if="status === 'loading'" variant="text" :count="3" />
    <ErrorState v-else-if="status !== 'success'" :message="errorMessage" @retry="load" />
    <template v-else-if="row">
      <p class="path-settings__card-note">{{ t('settings.prices.vatNote', { rate }) }}</p>
      <PathPriceRow
        :row="row"
        :limits="settings.limits"
        :reason-extra="reasonExtra"
        @saved="replaceRow"
      />
    </template>
    <ReasonDialog
      v-model="prompt.open.value"
      :title="t('settings.prices.reasonTitle')"
      :submit-label="t('actions.confirm')"
      :action="prompt.provide"
      :action-name="`settings.prices.${priceKey}.reason`"
    />
  </section>
</template>
