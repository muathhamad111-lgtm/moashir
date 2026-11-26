<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import BankAccountsTable from './BankAccountsTable.vue'
import BankAccountFormDialog from './BankAccountFormDialog.vue'
import BankAccountNoteDialog from './BankAccountNoteDialog.vue'
import BankAccountRetireDialog from './BankAccountRetireDialog.vue'
import PendingTransfersDialog from './PendingTransfersDialog.vue'
import { RETIRE_ACTIONS } from '@/composables/useBankAccountsSettings'
import { useClipboard } from '@/composables/useClipboard'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { isExampleIban } from '@/utils/payments/iban'

const props = defineProps({
  accounts: { type: Object, required: true },
  adding: { type: Boolean, default: false },
})

const emit = defineEmits(['update:adding'])
const { t } = useI18n()
const { can } = usePermissions()
const clipboard = useClipboard()

const noteAccount = ref(null)
const retireTarget = ref(null)

const addOpen = computed({
  get: () => props.adding,
  set: (value) => emit('update:adding', value),
})
const noteOpen = computed({
  get: () => noteAccount.value !== null,
  set: (value) => {
    if (!value) noteAccount.value = null
  },
})
const retireOpen = computed({
  get: () => retireTarget.value !== null,
  set: (value) => {
    if (!value) retireTarget.value = null
  },
})
const list = computed(() => props.accounts.accounts.value)
const hasExample = computed(() => list.value.some((account) => isExampleIban(account.iban)))
const canReview = computed(() => can(PERMISSIONS.FINANCE_TRANSFERS_REVIEW))

const ACTIONS = {
  note: (account) => (noteAccount.value = account),
  copy: (account) => clipboard.copy(account.id, account.iban),
  deactivate: (account) => (retireTarget.value = { account, mode: RETIRE_ACTIONS.DEACTIVATE }),
  delete: (account) => (retireTarget.value = { account, mode: RETIRE_ACTIONS.DELETE }),
  activate: (account) => props.accounts.activate(account),
  moveUp: (account) => props.accounts.move(account, 'up'),
  moveDown: (account) => props.accounts.move(account, 'down'),
}

function onAction(key, account) {
  ACTIONS[key]?.(account)
}

function retire(account) {
  return retireTarget.value?.mode === RETIRE_ACTIONS.DELETE
    ? props.accounts.remove(account)
    : props.accounts.deactivate(account)
}

async function retryBlocked() {
  const blocked = props.accounts.blocked.value
  props.accounts.closeBlocked()
  if (!blocked) return
  if (blocked.action === RETIRE_ACTIONS.DELETE) await props.accounts.remove(blocked.account)
  else await props.accounts.deactivate(blocked.account)
}
</script>

<template>
  <section class="bank-accounts-card" aria-labelledby="bank-accounts-title">
    <header class="bank-accounts-card__header">
      <div>
        <h2 id="bank-accounts-title" class="bank-accounts-card__title">
          {{ t('settings.bankAccounts.title') }}
        </h2>
        <p class="bank-accounts-card__subtitle">{{ t('settings.bankAccounts.subtitle') }}</p>
      </div>
      <BaseButton
        v-if="accounts.status.value === 'success' && !accounts.isEmpty.value"
        variant="primary"
        icon="plus"
        @click="addOpen = true"
      >
        {{ t('settings.bankAccounts.add') }}
      </BaseButton>
    </header>

    <Skeleton v-if="accounts.status.value === 'loading'" variant="text" :count="4" />
    <ErrorState
      v-else-if="accounts.status.value === 'error'"
      :message="accounts.errorMessage.value || t('settings.bankAccounts.loadFailed')"
      @retry="accounts.load"
    />
    <EmptyState
      v-else-if="accounts.isEmpty.value"
      icon="sar"
      :title="t('settings.bankAccounts.empty.title')"
      :message="t('settings.bankAccounts.empty.message')"
    >
      <template #action>
        <BaseButton variant="primary" icon="plus" @click="addOpen = true">
          {{ t('settings.bankAccounts.add') }}
        </BaseButton>
      </template>
    </EmptyState>
    <template v-else>
      <BankAccountsTable
        :accounts="list"
        :busy-id="accounts.busyId.value"
        :copied-id="clipboard.copiedKey.value"
        @action="onAction"
        @move="accounts.move"
        @copy="(account) => clipboard.copy(account.id, account.iban)"
      />
      <p class="bank-accounts-card__status" role="status" aria-live="polite">
        <template v-if="clipboard.copiedKey.value">{{
          t('settings.bankAccounts.copied')
        }}</template>
        <template v-else-if="clipboard.failedKey.value">{{
          t('integrations.copy.failed')
        }}</template>
      </p>
      <p v-if="hasExample" class="bank-accounts-card__example">
        <Badge variant="warning">{{ t('settings.bankAccounts.example') }}</Badge>
        {{ t('settings.bankAccounts.exampleNote') }}
      </p>
    </template>

    <BankAccountFormDialog v-model="addOpen" :action="accounts.create" />
    <BankAccountNoteDialog v-model="noteOpen" :account="noteAccount" :action="accounts.saveNote" />
    <BankAccountRetireDialog
      v-model="retireOpen"
      :account="retireTarget?.account ?? null"
      :mode="retireTarget?.mode"
      :action="retire"
    />
    <PendingTransfersDialog
      :blocked="accounts.blocked.value"
      :can-review="canReview"
      @close="accounts.closeBlocked"
      @retry="retryBlocked"
      @decided="accounts.reload"
    />
  </section>
</template>
