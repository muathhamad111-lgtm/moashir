<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from '@/components/data-list/DataTable.vue'
import Badge from '@/components/ui/Badge.vue'
import { NOTIFICATION_CATEGORY_TONES } from '@/constants/notificationCatalogue'

defineProps({
  types: { type: Array, default: () => [] },
  status: { type: String, default: 'success' },
  errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t, te } = useI18n()

const columns = computed(() =>
  ['code', 'name', 'module', 'category', 'channels', 'audiences', 'preference'].map((key) => ({
    key,
    label: t(`settings.notifications.catalogue.columns.${key}`),
  })),
)

function typeName(code) {
  const key = `notifications.typeNames.${code}`
  return te(key) ? t(key) : t('settings.notifications.catalogue.unnamed')
}

function channelList(channels) {
  return channels.map((channel) => t(`settings.notifications.catalogue.channels.${channel}`))
}

function audienceList(audiences) {
  return audiences.map((audience) => t(`settings.notifications.catalogue.audiences.${audience}`))
}

function preferenceOf(type) {
  if (type.isMandatory) return t('settings.notifications.catalogue.mandatory')
  if (!type.userConfigurableChannels.length) return t('settings.notifications.catalogue.inAppOnly')
  return t('settings.notifications.catalogue.optional', {
    channels: channelList(type.userConfigurableChannels).join(t('common.listSeparator')),
  })
}
</script>

<template>
  <DataTable
    class="notification-catalogue__table"
    :columns="columns"
    :items="types"
    :status="status"
    row-key="code"
    :error-message="errorMessage"
    :empty-title="t('settings.notifications.catalogue.emptyTitle')"
    :empty-message="t('settings.notifications.catalogue.emptyMessage')"
    @retry="emit('retry')"
  >
    <template #cell-code="{ item }">
      <bdi class="notification-catalogue__code" dir="ltr">{{ item.code }}</bdi>
    </template>
    <template #cell-name="{ item }">{{ typeName(item.code) }}</template>
    <template #cell-module="{ item }">{{ t(`notifications.modules.${item.module}`) }}</template>
    <template #cell-category="{ item }">
      <Badge :variant="NOTIFICATION_CATEGORY_TONES[item.category] ?? 'muted'">
        {{ t(`settings.notifications.catalogue.categories.${item.category}`) }}
      </Badge>
    </template>
    <template #cell-channels="{ item }">
      <ul class="notification-catalogue__chips">
        <li v-for="label in channelList(item.channels)" :key="label">{{ label }}</li>
      </ul>
    </template>
    <template #cell-audiences="{ item }">
      <ul class="notification-catalogue__chips">
        <li v-for="label in audienceList(item.audiences)" :key="label">{{ label }}</li>
      </ul>
    </template>
    <template #cell-preference="{ item }">
      <span
        class="notification-catalogue__preference"
        :class="{ 'notification-catalogue__preference--locked': item.isMandatory }"
        >{{ preferenceOf(item) }}</span
      >
    </template>
  </DataTable>
</template>
