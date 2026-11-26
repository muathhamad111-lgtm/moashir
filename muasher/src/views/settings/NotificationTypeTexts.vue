<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import SettingsSection from '@/components/settings/SettingsSection.vue'
import Tabs from '@/components/ui/Tabs.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import NotificationRecipientsCard from '@/components/settings/notifications/NotificationRecipientsCard.vue'
import NotificationTextEditor from '@/components/settings/notifications/NotificationTextEditor.vue'
import { useNotificationChannels } from '@/composables/useNotificationChannels'
import { useNotificationTypeTexts } from '@/composables/useNotificationTypeTexts'
import { notificationVariantLabel } from '@/utils/notificationVariants'
import { notificationTypeName } from '@/utils/notificationTypeName'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { OTP_NOTIFICATION_CODE } from '@/constants/notificationCatalogue'

const props = defineProps({
  code: { type: String, required: true },
})

const { t, te } = useI18n()
const route = useRoute()
const { type, status, load, replace } = useNotificationTypeTexts(() => props.code)
const channels = useNotificationChannels(() => props.code, replace)
const activeKey = ref('')

const title = computed(() => notificationTypeName(props.code, t, te))
const texts = computed(() => type.value?.texts ?? [])
const activeText = computed(() => texts.value.find((text) => text.key === activeKey.value) ?? null)
const sectionStatus = computed(() => (status.value === 'missing' ? 'success' : status.value))

function tabLabel(text) {
  const audience = text.audience
    ? t(`settings.notifications.texts.audienceTabs.${text.audience}`)
    : t('settings.notifications.texts.audienceTabs.all')
  const shared = texts.value.filter((other) => other.audience === text.audience).length > 1
  return shared ? `${audience} · ${notificationVariantLabel(text.variant, t, te)}` : audience
}

const tabs = computed(() => texts.value.map((text) => ({ value: text.key, label: tabLabel(text) })))

watch(texts, (list) => {
  if (!list.some((text) => text.key === activeKey.value)) activeKey.value = list[0]?.key ?? ''
})
</script>

<template>
  <SettingsSection
    :title="title"
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
        <bdi class="notification-texts__code" dir="ltr">{{ type.code }}</bdi>
        <span v-if="type.isCore" class="notification-type-label__core">
          <AppIcon name="lock" size="sm" />
          {{ t('settings.notifications.catalogue.core') }}
        </span>
      </div>
      <p v-if="type.isCore" class="notification-texts__core-banner" role="note">
        <AppIcon name="lock" />
        {{ t('settings.notifications.texts.coreBanner') }}
      </p>
      <p v-if="type.code === OTP_NOTIFICATION_CODE" class="notification-texts__pointer">
        <router-link :to="{ name: ROUTE_NAMES.ADMIN_SETTINGS_SMS }">
          {{ t('settings.notifications.texts.otpSmsPointer') }}
        </router-link>
      </p>
      <NotificationRecipientsCard :kinds="type.audienceKinds" :audiences="type.audiences" />
      <section class="notification-texts__card">
        <p v-if="!texts.length" class="notification-texts__empty">
          {{ t('settings.notifications.texts.noTexts') }}
        </p>
        <Tabs v-else v-model="activeKey" class="notification-texts__audiences" :tabs="tabs">
          <NotificationTextEditor
            v-if="activeText"
            :key="activeText.key"
            :text="activeText"
            :channel-settings="type.channelSettings ?? []"
            :is-core="type.isCore"
            :initial-channel="String(route.query.channel ?? '')"
            :switching="channels.busy.value"
            @saved="replace"
            @reset="load"
            @switch="channels.update"
          />
        </Tabs>
      </section>
    </div>
  </SettingsSection>
</template>
