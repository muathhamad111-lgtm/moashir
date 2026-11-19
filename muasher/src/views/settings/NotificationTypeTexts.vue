<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import Tabs from '@/components/ui/Tabs.vue'
import Badge from '@/components/ui/Badge.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import NotificationTextEditor from '@/components/settings/notifications/NotificationTextEditor.vue'
import NotificationChannelSwitches from '@/components/settings/notifications/NotificationChannelSwitches.vue'
import { useNotificationChannels } from '@/composables/useNotificationChannels'
import { useNotificationTypeTexts } from '@/composables/useNotificationTypeTexts'
import { notificationVariantLabel } from '@/utils/notificationVariants'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  code: { type: String, required: true },
})

const { t, te } = useI18n()
const { type, status, load, replace } = useNotificationTypeTexts(() => props.code)
const activeKey = ref('')
const channels = useNotificationChannels(() => props.code, replace)

const title = computed(() => {
  const key = `notifications.typeNames.${props.code}`
  return te(key) ? t(key) : props.code
})
const texts = computed(() => type.value?.texts ?? [])
const tabs = computed(() =>
  texts.value.map((text) => ({
    value: text.key,
    label: notificationVariantLabel(text.variant, t, te),
  })),
)
const activeText = computed(() => texts.value.find((text) => text.key === activeKey.value) ?? null)
const smsEnabled = computed(() =>
  Boolean(type.value?.channelSettings?.find((row) => row.channel === 'sms')?.isEnabled),
)
const sectionStatus = computed(() => (status.value === 'missing' ? 'success' : status.value))

watch(texts, (list) => {
  if (!list.some((text) => text.key === activeKey.value)) activeKey.value = list[0]?.key ?? ''
})
</script>

<template>
  <SettingsSection
    :title="title"
    :description="t('settings.notifications.texts.description')"
    :status="sectionStatus"
    :back-to="{ name: ROUTE_NAMES.ADMIN_SETTINGS_NOTIFICATIONS }"
    :back-label="t('settings.notifications.texts.back')"
    @retry="load"
  >
    <EmptyState
      v-if="status === 'missing'"
      icon="bell"
      :title="t('settings.notifications.texts.missingTitle')"
      :message="t('settings.notifications.texts.missingMessage')"
    />
    <div v-else-if="type" class="notification-texts">
      <div class="notification-texts__meta">
        <bdi class="notification-catalogue__code" dir="ltr">{{ type.code }}</bdi>
        <Badge v-if="type.isMandatory" variant="warning">
          {{ t('settings.notifications.catalogue.mandatory') }}
        </Badge>
        <Badge v-for="channel in type.channels" :key="channel" variant="muted">
          {{ t(`settings.notifications.catalogue.channels.${channel}`) }}
        </Badge>
      </div>
      <NotificationChannelSwitches
        :settings="type.channelSettings ?? []"
        :is-mandatory="type.isMandatory"
        :disabled="channels.busy.value"
        @change="channels.update"
      />
      <p v-if="!texts.length" class="notification-texts__empty">
        {{ t('settings.notifications.texts.noTexts') }}
      </p>
      <Tabs v-else-if="tabs.length > 1" v-model="activeKey" :tabs="tabs">
        <NotificationTextEditor
          v-if="activeText"
          :key="activeText.key"
          :text="activeText"
          :sms-enabled="smsEnabled"
          @saved="replace"
          @reset="load"
        />
      </Tabs>
      <NotificationTextEditor
        v-else
        :key="texts[0].key"
        :text="texts[0]"
        :sms-enabled="smsEnabled"
        @saved="replace"
        @reset="load"
      />
    </div>
  </SettingsSection>
</template>
