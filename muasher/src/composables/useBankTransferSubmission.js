import { computed, ref, unref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useIdempotencyKey } from '@/composables/useIdempotencyKey'
import { success } from '@/composables/useSuccess'
import { paymentsService } from '@/services/paymentsService'
import {
  BANK_TRANSFER_CODES,
  PROOF_FILE_CODES,
  PROOF_STATES,
  REFUSED_PROOF_STATES,
} from '@/constants/bankTransfer'
import { logger } from '@/utils/logger'
import { proofIssue } from '@/utils/payments/proofFile'
import { errorCodeOf, financeErrorMessage } from '@/utils/payments/financeErrors'

const PERCENT = 100

export const SUBMIT_FAILURES = Object.freeze({
  OPEN_ORDER: 'open-order',
  ACCOUNT: 'account',
  CHANNEL: 'channel',
  BLOCKED: 'blocked',
  OTHER: 'other',
})

const FAILURE_BY_CODE = Object.freeze({
  [BANK_TRANSFER_CODES.OPEN_ORDER_EXISTS]: SUBMIT_FAILURES.OPEN_ORDER,
  [BANK_TRANSFER_CODES.NOT_SELECTABLE]: SUBMIT_FAILURES.ACCOUNT,
  [BANK_TRANSFER_CODES.CHANNEL_NOT_AVAILABLE]: SUBMIT_FAILURES.CHANNEL,
  [BANK_TRANSFER_CODES.ENTITY_DELETED]: SUBMIT_FAILURES.BLOCKED,
  [BANK_TRANSFER_CODES.ENTITY_NOT_ACTIVE]: SUBMIT_FAILURES.BLOCKED,
})

function firstAccountId(accounts) {
  return accounts[0]?.id ?? null
}

export function useBankTransferSubmission(purpose, accounts, { onSubmitted = () => {} } = {}) {
  const i18n = useI18n()
  const keys = useIdempotencyKey()
  const accountId = ref(firstAccountId(unref(accounts)))
  const proof = ref(null)
  const proofState = ref(PROOF_STATES.EMPTY)
  const progress = ref(0)
  const sending = ref(false)
  const missingProof = ref(false)
  const failure = ref(null)
  const unavailableAccountId = ref(null)

  const selectedAccount = computed(
    () => unref(accounts).find((account) => account.id === accountId.value) ?? null,
  )
  const isRefused = computed(() => REFUSED_PROOF_STATES.includes(proofState.value))
  const canSend = computed(
    () =>
      Boolean(selectedAccount.value) &&
      proofState.value === PROOF_STATES.READY &&
      !sending.value &&
      accountId.value !== unavailableAccountId.value,
  )

  watch(
    () => unref(accounts),
    (list) => {
      if (!list.some((account) => account.id === accountId.value))
        accountId.value = firstAccountId(list)
    },
  )

  function selectAccount(id) {
    accountId.value = id
    if (failure.value?.kind === SUBMIT_FAILURES.ACCOUNT) failure.value = null
  }

  function selectProof(file) {
    proof.value = file ?? null
    progress.value = 0
    missingProof.value = false
    failure.value = null
    if (!file) {
      proofState.value = PROOF_STATES.EMPTY
      return
    }
    proofState.value = proofIssue(file) ?? PROOF_STATES.READY
  }

  function trackUpload(event) {
    if (!event.total) return
    progress.value = Math.min(PERCENT, Math.round((event.loaded / event.total) * PERCENT))
    if (progress.value >= PERCENT) proofState.value = PROOF_STATES.SCANNING
  }

  function failureOf(error) {
    const code = errorCodeOf(error)
    return {
      kind: FAILURE_BY_CODE[code] ?? SUBMIT_FAILURES.OTHER,
      code,
      meta: error?.normalized?.meta ?? {},
      message: financeErrorMessage(i18n, error, 'portal.transfer.errors.failed'),
    }
  }

  function rejected(error) {
    if (error?.response) keys.renew()
    const fileState = PROOF_FILE_CODES[errorCodeOf(error)]
    if (fileState) {
      proofState.value = fileState
      return
    }
    const next = failureOf(error)
    if (next.kind === SUBMIT_FAILURES.ACCOUNT) unavailableAccountId.value = accountId.value
    proofState.value =
      next.kind === SUBMIT_FAILURES.OTHER ? PROOF_STATES.FAILED : PROOF_STATES.READY
    failure.value = next
  }

  async function submit() {
    if (sending.value) return null
    if (!proof.value || proofState.value === PROOF_STATES.EMPTY) {
      missingProof.value = true
      return null
    }
    if (!canSend.value && proofState.value !== PROOF_STATES.FAILED) return null
    sending.value = true
    failure.value = null
    proofState.value = PROOF_STATES.UPLOADING
    logger.breadcrumb('form', 'payments.submitTransfer', { fields: ['account', 'proof'] })
    try {
      const envelope = await paymentsService.submitTransfer(
        { purpose: unref(purpose), bankAccountId: accountId.value, proof: proof.value },
        keys.current(),
        { onUploadProgress: trackUpload },
      )
      keys.renew()
      proofState.value = PROOF_STATES.ACCEPTED
      success.show({
        title: i18n.t('portal.transfer.submittedTitle'),
        message: envelope.data?.reference ?? '',
      })
      onSubmitted(envelope.data)
      return envelope.data
    } catch (error) {
      rejected(error)
      return null
    } finally {
      sending.value = false
    }
  }

  return {
    accountId,
    proof,
    proofState,
    progress,
    sending,
    missingProof,
    failure,
    unavailableAccountId,
    selectedAccount,
    isRefused,
    canSend,
    selectAccount,
    selectProof,
    submit,
  }
}
