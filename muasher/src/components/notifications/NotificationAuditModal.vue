<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { useNotificationAudit } from '@/composables/useNotificationAudit'
import { formatDate } from '@/utils/datetime'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  notification: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const { t, locale } = useI18n()
const { status, errorMessage, entries, load, reset } = useNotificationAudit()

const notificationTitle = computed(() => props.notification?.title?.[locale.value] ?? '')

function roleLabel(role) {
  const key = `notifications.roleLabels.${role}`
  const label = t(key)
  return label === key ? role : label
}

function localizedRecipient(recipient) {
  if (!recipient) return ''
  if (typeof recipient === 'string') return recipient
  return recipient[locale.value] || recipient.ar || recipient.en || ''
}

function stamp(value) {
  if (!value) return t('notifications.audit.notYet')
  return formatDate(value, locale.value, { dateStyle: 'medium', timeStyle: 'short' })
}

function close() {
  emit('update:modelValue', false)
}

watch(
  () => props.modelValue,
  (open) => {
    if (open && props.notification) load(props.notification.id)
    else reset()
  },
)
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="t('notifications.audit.title')"
    size="lg"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="notification-audit">
      <p v-if="notification" class="notification-audit__intro">
        <span class="notification-audit__code">{{ notification.code }}</span>
        <span class="notification-audit__subject">{{ notificationTitle }}</span>
      </p>

      <div v-if="status === 'loading'" class="notification-audit__state">
        <Skeleton variant="text" :count="4" />
      </div>
      <ErrorState
        v-else-if="status === 'error'"
        :message="errorMessage"
        @retry="notification && load(notification.id)"
      />
      <EmptyState
        v-else-if="status === 'empty'"
        icon="fa-regular fa-clock"
        :title="t('notifications.audit.emptyTitle')"
        :message="t('notifications.audit.emptyMessage')"
      />
      <div v-else class="notification-audit__table-wrap">
        <table class="notification-audit__table">
          <thead>
            <tr>
              <th scope="col">{{ t('notifications.audit.columns.recipient') }}</th>
              <th scope="col">{{ t('notifications.audit.columns.channel') }}</th>
              <th scope="col">{{ t('notifications.audit.columns.received') }}</th>
              <th scope="col">{{ t('notifications.audit.columns.opened') }}</th>
              <th scope="col">{{ t('notifications.audit.columns.actioned') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in entries" :key="entry.id">
              <td>
                <span class="notification-audit__recipient">
                  {{ localizedRecipient(entry.recipient) }}
                </span>
                <Badge variant="info">{{ roleLabel(entry.recipientRole) }}</Badge>
              </td>
              <td>
                <span class="notification-audit__channel">
                  {{ t(`notifications.channels.${entry.channel}`) }}
                </span>
              </td>
              <td>
                <span class="notification-audit__stamp">{{ stamp(entry.receivedAt) }}</span>
              </td>
              <td>
                <span class="notification-audit__stamp" :class="{ 'is-pending': !entry.openedAt }">
                  {{ stamp(entry.openedAt) }}
                </span>
              </td>
              <td>
                <span
                  class="notification-audit__stamp"
                  :class="{ 'is-pending': !entry.actionedAt }"
                >
                  {{ stamp(entry.actionedAt) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <template #footer>
      <BaseButton variant="secondary" @click="close">
        {{ t('actions.close') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
