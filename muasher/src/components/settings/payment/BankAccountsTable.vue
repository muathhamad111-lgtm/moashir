<script setup>
import { useI18n } from 'vue-i18n'
import AppMenu from '@/components/ui/AppMenu.vue'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { formatIban, isExampleIban } from '@/utils/payments/iban'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  accounts: { type: Array, required: true },
  busyId: { type: String, default: null },
  copiedId: { type: String, default: null },
})

const emit = defineEmits(['action', 'move', 'copy'])
const { t, locale } = useI18n()

function actionsOf(account) {
  return [
    { key: 'note', label: t('settings.bankAccounts.actions.note'), icon: 'pen' },
    { key: 'copy', label: t('settings.bankAccounts.actions.copy'), icon: 'copy' },
    account.isActive
      ? {
          key: 'deactivate',
          label: t('settings.bankAccounts.actions.deactivate'),
          icon: 'eye-slash',
        }
      : {
          key: 'activate',
          label: t('settings.bankAccounts.actions.activate'),
          icon: 'check-circle',
        },
    {
      key: 'delete',
      label: t('settings.bankAccounts.actions.delete'),
      icon: 'trash',
      variant: 'danger',
    },
  ]
}

function cardActionsOf(account, index) {
  const moves = [
    !isFirst(index) && {
      key: 'moveUp',
      label: t('settings.bankAccounts.actions.up'),
      icon: 'arrow-up',
    },
    !isLast(index) && {
      key: 'moveDown',
      label: t('settings.bankAccounts.actions.down'),
      icon: 'arrow-down',
    },
  ].filter(Boolean)
  return [...moves, ...actionsOf(account)]
}

function usageOf(account) {
  return account.isUsed
    ? t('settings.bankAccounts.usage.used')
    : t('settings.bankAccounts.usage.unused')
}

function bankOf(account) {
  return localizedText(account.bankName, locale.value)
}

function isFirst(index) {
  return index === 0
}

function isLast(index) {
  return index === props.accounts.length - 1
}
</script>

<template>
  <div class="bank-accounts">
    <div class="bank-accounts__table-wrap">
      <table class="table bank-accounts__table">
        <thead class="table__head">
          <tr>
            <th class="table__cell" scope="col">{{ t('settings.bankAccounts.columns.order') }}</th>
            <th class="table__cell" scope="col">{{ t('settings.bankAccounts.columns.bank') }}</th>
            <th class="table__cell" scope="col">{{ t('settings.bankAccounts.columns.holder') }}</th>
            <th class="table__cell" scope="col">{{ t('settings.bankAccounts.columns.iban') }}</th>
            <th class="table__cell" scope="col">{{ t('settings.bankAccounts.columns.status') }}</th>
            <th class="table__cell" scope="col">
              <span class="bank-accounts__sr">{{ t('components.actions.label') }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(account, index) in accounts"
            :key="account.id"
            class="table__row"
            :aria-busy="busyId === account.id || undefined"
          >
            <td class="table__cell">
              <span class="bank-accounts__order">
                <span class="bank-accounts__position">{{ index + 1 }}</span>
                <BaseButton
                  variant="ghost"
                  size="sm"
                  icon-only
                  icon="arrow-up"
                  :disabled="isFirst(index) || busyId !== null"
                  :aria-label="t('settings.bankAccounts.actions.moveUp', { bank: bankOf(account) })"
                  @click="emit('move', account, 'up')"
                />
                <BaseButton
                  variant="ghost"
                  size="sm"
                  icon-only
                  icon="arrow-down"
                  :disabled="isLast(index) || busyId !== null"
                  :aria-label="
                    t('settings.bankAccounts.actions.moveDown', { bank: bankOf(account) })
                  "
                  @click="emit('move', account, 'down')"
                />
              </span>
            </td>
            <td class="table__cell bank-accounts__bank">{{ bankOf(account) }}</td>
            <td class="table__cell">{{ localizedText(account.accountHolderName, locale) }}</td>
            <td class="table__cell">
              <span class="bank-accounts__iban">
                <span class="bank-accounts__iban-row">
                  <bdi dir="ltr" class="bank-accounts__iban-value">{{
                    formatIban(account.iban)
                  }}</bdi>
                  <BaseButton
                    variant="ghost"
                    size="sm"
                    icon-only
                    :icon="copiedId === account.id ? 'check' : 'copy'"
                    :aria-label="
                      t('settings.bankAccounts.actions.copyFor', { bank: bankOf(account) })
                    "
                    @click="emit('copy', account)"
                  />
                </span>
                <span class="bank-accounts__usage">
                  {{ usageOf(account) }}
                  <Badge v-if="isExampleIban(account.iban)" variant="warning">{{
                    t('settings.bankAccounts.example')
                  }}</Badge>
                </span>
              </span>
            </td>
            <td class="table__cell">
              <Badge :variant="account.isActive ? 'success' : 'muted'">{{
                t(`settings.bankAccounts.status.${account.isActive ? 'active' : 'inactive'}`)
              }}</Badge>
            </td>
            <td class="table__cell">
              <AppMenu
                :actions="actionsOf(account)"
                :label="t('settings.bankAccounts.actions.menu', { bank: bankOf(account) })"
                @select="emit('action', $event, account)"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <ul class="bank-accounts__cards">
      <li v-for="(account, index) in accounts" :key="account.id" class="bank-account-card">
        <div class="bank-account-card__head">
          <span class="bank-account-card__title">
            <span class="bank-accounts__position">{{ index + 1 }}</span>
            {{ bankOf(account) }}
          </span>
          <Badge :variant="account.isActive ? 'success' : 'muted'">{{
            t(`settings.bankAccounts.status.${account.isActive ? 'active' : 'inactive'}`)
          }}</Badge>
          <AppMenu
            :actions="cardActionsOf(account, index)"
            :label="t('settings.bankAccounts.actions.menu', { bank: bankOf(account) })"
            @select="emit('action', $event, account)"
          />
        </div>
        <p class="bank-account-card__holder">
          {{ localizedText(account.accountHolderName, locale) }}
        </p>
        <bdi dir="ltr" class="bank-account-card__iban">{{ formatIban(account.iban) }}</bdi>
        <p class="bank-accounts__usage">
          {{ usageOf(account) }}
          <Badge v-if="isExampleIban(account.iban)" variant="warning">{{
            t('settings.bankAccounts.example')
          }}</Badge>
        </p>
      </li>
    </ul>
  </div>
</template>
