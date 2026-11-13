<script setup>
import { computed, nextTick, ref, toRef, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BankAccountChoice from './BankAccountChoice.vue'
import TransferDetails from './TransferDetails.vue'
import ProofUpload from './ProofUpload.vue'
import TransferSubmitFailure from './TransferSubmitFailure.vue'
import { SUBMIT_FAILURES, useBankTransferSubmission } from '@/composables/useBankTransferSubmission'

const props = defineProps({
  purpose: { type: String, required: true },
  awardCycleId: { type: Number, default: null },
  accounts: { type: Array, required: true },
  amount: { type: Object, default: null },
  unifiedNumber: { type: String, default: null },
  canChangeMethod: { type: Boolean, default: false },
})

const emit = defineEmits(['change-method', 'submitted', 'account-unavailable'])
const { t } = useI18n()
const ids = { account: useId(), transfer: useId(), proof: useId() }
const missingRef = ref(null)

const order = computed(() => ({ purpose: props.purpose, awardCycleId: props.awardCycleId }))
const submission = useBankTransferSubmission(order, toRef(props, 'accounts'), {
  onSubmitted: (payment) => emit('submitted', payment),
})
const account = computed(() => submission.selectedAccount.value)
const single = computed(() => props.accounts.length === 1)

watch(submission.missingProof, async (missing) => {
  if (!missing) return
  await nextTick()
  missingRef.value?.focus()
})

watch(
  () => submission.failure.value?.kind,
  (kind) => {
    if (kind === SUBMIT_FAILURES.ACCOUNT || kind === SUBMIT_FAILURES.CHANNEL)
      emit('account-unavailable')
  },
)
</script>

<template>
  <section class="transfer-panel" aria-labelledby="transfer-panel-title">
    <header class="transfer-panel__header">
      <h2 id="transfer-panel-title" class="transfer-panel__title">
        {{ t('portal.transfer.title') }}
      </h2>
      <button
        v-if="canChangeMethod"
        type="button"
        class="transfer-panel__change"
        @click="emit('change-method')"
      >
        <AppIcon name="refresh" />
        {{ t('portal.transfer.changeMethod') }}
      </button>
    </header>

    <ol class="transfer-panel__steps">
      <li class="transfer-step">
        <h3 :id="ids.account" class="transfer-step__title">
          <span class="transfer-step__number">1</span>
          {{
            single
              ? t('portal.transfer.steps.account.single')
              : t('portal.transfer.steps.account.title')
          }}
        </h3>
        <BankAccountChoice
          :accounts="accounts"
          :model-value="submission.accountId.value"
          :unavailable-id="submission.unavailableAccountId.value"
          :labelledby="ids.account"
          @update:model-value="submission.selectAccount"
        />
        <p
          v-if="submission.failure.value?.kind === SUBMIT_FAILURES.ACCOUNT"
          class="transfer-step__hint"
          role="alert"
        >
          {{ t('portal.transfer.account.chooseAnother') }}
        </p>
      </li>

      <li v-if="account" class="transfer-step">
        <h3 :id="ids.transfer" class="transfer-step__title">
          <span class="transfer-step__number">2</span>
          {{ t('portal.transfer.steps.transfer') }}
        </h3>
        <TransferDetails :account="account" :amount="amount" :unified-number="unifiedNumber" />
      </li>

      <li class="transfer-step">
        <h3 :id="ids.proof" class="transfer-step__title">
          <span class="transfer-step__number">3</span>
          {{ t('portal.transfer.steps.proof') }}
        </h3>
        <ProofUpload
          :file="submission.proof.value"
          :state="submission.proofState.value"
          :progress="submission.progress.value"
          :disabled="submission.sending.value"
          :labelledby="ids.proof"
          @select="submission.selectProof"
          @retry="submission.submit"
        />
        <p
          v-if="submission.missingProof.value"
          ref="missingRef"
          class="transfer-step__alert"
          role="alert"
          tabindex="-1"
        >
          <AppIcon name="warning-triangle" />
          {{ t('portal.transfer.proof.missing') }}
        </p>
      </li>
    </ol>

    <TransferSubmitFailure v-if="submission.failure.value" :failure="submission.failure.value" />

    <div class="transfer-panel__submit">
      <BaseButton
        variant="primary"
        size="lg"
        icon="send"
        :loading="submission.sending.value"
        :disabled="!submission.canSend.value"
        @click="submission.submit"
      >
        {{ submission.sending.value ? t('portal.transfer.sending') : t('portal.transfer.submit') }}
      </BaseButton>
      <p v-if="!submission.canSend.value && !submission.sending.value" class="transfer-panel__hint">
        {{ t('portal.transfer.submitHint') }}
      </p>
    </div>
  </section>
</template>
