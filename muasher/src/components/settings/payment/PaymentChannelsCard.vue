<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import MethodOption from '@/components/payments/MethodOption.vue'
import { CHANNEL_BLOCKERS } from '@/composables/usePaymentChannelsSettings'
import { CHANNEL_CHOICES, CHANNEL_ICONS } from '@/constants/bankTransfer'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  channels: { type: Object, required: true },
  gatewayAnchor: { type: String, required: true },
})

const emit = defineEmits(['add-account'])
const { t, n } = useI18n()

const view = computed(() => props.channels.view.value)

function electronicMeta() {
  return view.value.gatewayReady
    ? { text: t('settings.paymentChannels.meta.gatewayReady'), tone: 'success' }
    : { text: t('settings.paymentChannels.meta.gatewayNotReady'), tone: 'warning' }
}

function transferMeta() {
  const count = view.value.activeBankAccountsCount
  const accounts = count
    ? {
        text: t('settings.paymentChannels.meta.accounts', { count: formatNumber(n, count) }, count),
        tone: 'success',
      }
    : { text: t('settings.paymentChannels.meta.noAccount'), tone: 'danger' }
  const reviewers = view.value.reviewersCount
    ? null
    : { text: t('settings.paymentChannels.meta.noReviewer'), tone: 'warning' }
  return [accounts, reviewers].filter(Boolean)
}

const options = computed(() => {
  if (!view.value) return []
  return [
    {
      value: CHANNEL_CHOICES.ELECTRONIC,
      meta: [electronicMeta()],
    },
    { value: CHANNEL_CHOICES.BANK_TRANSFER, meta: transferMeta() },
    { value: CHANNEL_CHOICES.BOTH, meta: [] },
  ].map((option) => ({
    ...option,
    icon: CHANNEL_ICONS[option.value],
    title: t(`settings.paymentChannels.options.${option.value}.title`),
    description: t(`settings.paymentChannels.options.${option.value}.description`),
  }))
})
</script>

<template>
  <section class="channels-card" aria-labelledby="payment-channels-title">
    <header class="channels-card__header">
      <h2 id="payment-channels-title" class="channels-card__title">
        {{ t('settings.paymentChannels.title') }}
      </h2>
      <p class="channels-card__subtitle">{{ t('settings.paymentChannels.subtitle') }}</p>
    </header>

    <Skeleton v-if="channels.status.value === 'loading'" variant="block" :count="2" />
    <ErrorState
      v-else-if="channels.status.value === 'error'"
      :message="channels.errorMessage.value"
      @retry="channels.load"
    />
    <template v-else>
      <div
        class="channels-card__options"
        role="radiogroup"
        :aria-label="t('settings.paymentChannels.title')"
      >
        <MethodOption
          v-for="option in options"
          :key="option.value"
          :model-value="channels.choice.value"
          name="payment-channels"
          :value="option.value"
          :icon="option.icon"
          :title="option.title"
          :description="option.description"
          :meta="option.meta"
          @update:model-value="channels.select"
        />
      </div>

      <div
        v-if="channels.blocker.value === CHANNEL_BLOCKERS.NO_ACCOUNT"
        class="channels-card__alert channels-card__alert--danger"
        role="alert"
      >
        <AppIcon name="warning-triangle" />
        <p>{{ t('settings.paymentChannels.alerts.noAccount') }}</p>
        <BaseButton variant="ghost" size="sm" icon="plus" @click="emit('add-account')">
          {{ t('settings.bankAccounts.add') }}
        </BaseButton>
      </div>
      <div
        v-else-if="channels.blocker.value === CHANNEL_BLOCKERS.GATEWAY_NOT_READY"
        class="channels-card__alert channels-card__alert--warning"
        role="alert"
      >
        <AppIcon name="warning-triangle" />
        <p>{{ t('settings.paymentChannels.alerts.gatewayNotReady') }}</p>
        <a class="channels-card__link" :href="`#${gatewayAnchor}`">
          <AppIcon name="link" />
          {{ t('settings.paymentChannels.alerts.openGateway') }}
        </a>
      </div>
      <div
        v-if="channels.noReviewer.value"
        class="channels-card__alert channels-card__alert--warning"
        role="note"
      >
        <AppIcon name="warning-triangle" />
        <p>{{ t('settings.paymentChannels.alerts.noReviewer') }}</p>
        <RouterLink class="channels-card__link" :to="{ name: ROUTE_NAMES.ADMIN_USERS }">
          <AppIcon name="users-two" />
          {{ t('settings.paymentChannels.alerts.openUsers') }}
        </RouterLink>
      </div>
      <p class="channels-card__note" role="note">
        <AppIcon name="question-circle" />
        {{ t('settings.paymentChannels.note') }}
      </p>
      <p v-if="channels.saveError.value" class="channels-card__error" role="alert">
        {{ channels.saveError.value }}
      </p>
      <div class="channels-card__actions">
        <BaseButton
          variant="primary"
          icon="check"
          :loading="channels.saving.value"
          :disabled="!channels.canSave.value"
          @click="channels.save"
        >
          {{ t('settings.paymentChannels.save') }}
        </BaseButton>
      </div>
    </template>
  </section>
</template>
