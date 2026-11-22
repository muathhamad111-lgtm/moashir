<script setup>
import { computed, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import Tabs from '@/components/ui/Tabs.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import NotificationChannelFields from './NotificationChannelFields.vue'
import NotificationVariableChips from './NotificationVariableChips.vue'
import NotificationSmsApproval from './NotificationSmsApproval.vue'
import NotificationLanguageSwitch from './NotificationLanguageSwitch.vue'
import NotificationTextPreview from './NotificationTextPreview.vue'
import { useNotificationTextForm } from '@/composables/useNotificationTextForm'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { NOTIFICATION_CHANNELS } from '@/constants/notificationCatalogue'
import { channelPartsOf } from '@/utils/notificationTextParts'

const props = defineProps({
  text: { type: Object, required: true },
  channelSettings: { type: Array, required: true },
  isCore: { type: Boolean, default: false },
  initialChannel: { type: String, default: '' },
  switching: { type: Boolean, default: false },
})

const emit = defineEmits(['saved', 'reset', 'switch'])
const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const form = useNotificationTextForm(toRef(props, 'text'), toRef(props, 'isCore'), {
  onSaved: (data) => emit('saved', data),
  onReset: () => emit('reset'),
})

const locale = ref('ar')
const channels = computed(() =>
  NOTIFICATION_CHANNELS.filter(
    (channel) => channelPartsOf(props.text, channel, props.isCore).length,
  ),
)
const channel = ref(
  channels.value.includes(props.initialChannel) ? props.initialChannel : channels.value[0],
)
const parts = computed(() => channelPartsOf(props.text, channel.value, props.isCore))

function isOn(value) {
  return props.channelSettings.some(
    (row) =>
      row.channel === value &&
      row.isEnabled &&
      (!props.text.audience || row.audience === props.text.audience),
  )
}

const tabs = computed(() =>
  channels.value.map((value) => ({
    value,
    label: t(`settings.notifications.catalogue.channels.${value}`),
    badge: isOn(value)
      ? t('settings.notifications.texts.channelOn')
      : t('settings.notifications.texts.channelOff'),
  })),
)

async function save() {
  try {
    await form.save()
    toast.success(t('settings.notifications.texts.saved'))
  } catch (error) {
    if (!error?.normalized?.fieldErrors) toast.error(t('settings.notifications.texts.saveFailed'))
  }
}

async function reset() {
  const accepted = await confirm({
    title: t('settings.notifications.texts.resetTitle'),
    message: t('settings.notifications.texts.resetMessage'),
    confirmLabel: t('settings.notifications.texts.reset'),
  })
  if (!accepted) return
  try {
    await form.reset()
    toast.success(t('settings.notifications.texts.resetDone'))
  } catch {
    toast.error(t('settings.notifications.texts.saveFailed'))
  }
}
</script>

<template>
  <form class="notification-text" novalidate @submit.prevent="save">
    <Tabs v-model="channel" class="notification-text__channels" :tabs="tabs">
      <div class="notification-text__bar">
        <ToggleSwitch
          :model-value="isOn(channel)"
          :name="`${text.key}-${channel}`"
          :label="t('settings.notifications.texts.channelSwitch')"
          :disabled="isCore || switching"
          @update:model-value="
            emit('switch', { channel, enabled: $event, audience: text.audience })
          "
        />
        <Badge v-if="text.isCustomized" variant="info">{{
          t('settings.notifications.texts.customized')
        }}</Badge>
        <NotificationLanguageSwitch v-model="locale" />
      </div>
      <div class="notification-text__grid">
        <div class="notification-text__fields">
          <NotificationChannelFields :parts="parts" :form="form" :text="text" :locale="locale" />
          <NotificationSmsApproval
            v-if="channel === 'sms'"
            :approval="text.sms?.approval ?? null"
          />
          <NotificationVariableChips
            :variables="text.variables"
            :required="text.requiredVariables"
            :disabled="form.busy.value"
            @insert="form.insertVariable"
          />
        </div>
        <NotificationTextPreview
          :channel="channel"
          :draft="form.draft.value"
          :text="text"
          :locale="locale"
        />
      </div>
    </Tabs>
    <div class="notification-text__actions">
      <BaseButton
        v-if="text.isCustomized"
        variant="ghost"
        icon="undo"
        :disabled="form.busy.value"
        @click="reset"
      >
        {{ t('settings.notifications.texts.reset') }}
      </BaseButton>
      <BaseButton
        type="submit"
        variant="primary"
        :loading="form.busy.value"
        :disabled="!form.isDirty.value"
      >
        {{ t('settings.notifications.texts.save') }}
      </BaseButton>
    </div>
  </form>
</template>
