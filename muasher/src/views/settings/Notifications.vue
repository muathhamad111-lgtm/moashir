<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import Tabs from '@/components/ui/Tabs.vue'
import NotificationTypeCard from '@/components/settings/NotificationTypeCard.vue'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import { useSettings } from '@/composables/useSettings'
import { useToast } from '@/composables/useToast'
import { settingsService } from '@/services/settingsService'
import { formatDate } from '@/utils/datetime'
import { TEMPLATE_PLACEHOLDERS } from '@/constants/templatePlaceholders'

const { t, locale } = useI18n()
const { localized } = useSettings()
const toast = useToast()

const activeTab = ref('settings')
const tabs = computed(() => [
  { value: 'settings', label: t('settings.notifications.tabSettings') },
  { value: 'audit', label: t('settings.notifications.tabAudit') },
])

const types = ref([])
const status = ref('loading')
const errorMessage = ref('')
const savingCode = ref('')
const placeholders = TEMPLATE_PLACEHOLDERS

async function loadTypes() {
  status.value = 'loading'
  errorMessage.value = ''
  try {
    const envelope = await settingsService.listNotificationTypes()
    types.value = envelope.data ?? []
    status.value = 'success'
  } catch (error) {
    errorMessage.value = error?.normalized?.message || t('common.error')
    status.value = 'error'
  }
}

async function saveType({ code, payload }) {
  savingCode.value = code
  try {
    const envelope = await settingsService.updateNotificationType(code, payload)
    types.value = types.value.map((item) => (item.code === code ? envelope.data : item))
    toast.success(t('settings.toasts.saved'))
  } catch (error) {
    toast.error(error?.normalized?.message || t('settings.toasts.saveFailed'))
  } finally {
    savingCode.value = ''
  }
}

const auditItems = ref([])
const auditStatus = ref('loading')
const auditError = ref('')

async function loadAudit() {
  auditStatus.value = 'loading'
  auditError.value = ''
  try {
    const envelope = await settingsService.listNotificationAudit()
    auditItems.value = envelope.data ?? []
    auditStatus.value = auditItems.value.length ? 'success' : 'empty'
  } catch (error) {
    auditError.value = error?.normalized?.message || t('common.error')
    auditStatus.value = 'error'
  }
}

const auditColumns = computed(() => [
  { key: 'code', label: t('settings.notifications.auditColumns.code') },
  { key: 'recipient', label: t('settings.notifications.auditColumns.recipient') },
  { key: 'channel', label: t('settings.notifications.auditColumns.channel') },
  { key: 'receivedAt', label: t('settings.notifications.auditColumns.received') },
  { key: 'openedAt', label: t('settings.notifications.auditColumns.opened') },
  { key: 'actionedAt', label: t('settings.notifications.auditColumns.actioned') },
])

function stamp(value) {
  if (!value) return t('settings.notifications.notYet')
  return formatDate(value, locale.value, { dateStyle: 'medium', timeStyle: 'short' })
}

onMounted(() => {
  loadTypes()
  loadAudit()
})
</script>

<template>
  <SettingsSection
    :title="t('settings.notifications.title')"
    :description="t('settings.notifications.description')"
    :status="activeTab === 'settings' ? status : 'success'"
    :error-message="errorMessage"
    @retry="loadTypes"
  >
    <Tabs v-model="activeTab" :tabs="tabs">
      <template v-if="activeTab === 'settings'">
        <ul class="notification-type__list">
          <li v-for="type in types" :key="type.code">
            <NotificationTypeCard
              :type="type"
              :placeholders="placeholders"
              :saving="savingCode === type.code"
              @save="saveType"
            />
          </li>
        </ul>
      </template>

      <template v-else>
        <DataTable
          :columns="auditColumns"
          :items="auditItems"
          :status="auditStatus"
          :error-message="auditError"
          :empty-title="t('settings.notifications.auditEmpty')"
          @retry="loadAudit"
        >
          <template #cell-code="{ item }">
            <Badge variant="muted">{{ item.code }}</Badge>
          </template>
          <template #cell-recipient="{ item }">
            {{ localized(item.recipient) }}
          </template>
          <template #cell-channel="{ item }">
            <Badge variant="info">{{ t(`settings.notifications.channels.${item.channel}`) }}</Badge>
          </template>
          <template #cell-receivedAt="{ item }">
            <span class="settings-form__nowrap">{{ stamp(item.receivedAt) }}</span>
          </template>
          <template #cell-openedAt="{ item }">
            <span class="settings-form__nowrap">{{ stamp(item.openedAt) }}</span>
          </template>
          <template #cell-actionedAt="{ item }">
            <span class="settings-form__nowrap">{{ stamp(item.actionedAt) }}</span>
          </template>
        </DataTable>
      </template>
    </Tabs>
  </SettingsSection>
</template>
