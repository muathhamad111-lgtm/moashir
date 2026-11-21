<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'

const props = defineProps({
  settings: { type: Array, required: true },
  isMandatory: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['change'])
const { t } = useI18n()

const SMS = 'sms'

const visible = computed(() =>
  props.settings.filter(
    (setting) => setting.channel !== SMS || setting.isShipped || setting.isSwitchable,
  ),
)

function hintOf(setting) {
  if (props.isMandatory) return t('settings.notifications.channels.lockedMandatory')
  if (!setting.isSwitchable) return t('settings.notifications.channels.lockedChannel')
  if (setting.channel === SMS) return t('settings.notifications.channels.smsHint')
  return setting.isShipped ? '' : t('settings.notifications.channels.addedHint')
}
</script>

<template>
  <fieldset class="notification-channels">
    <legend class="notification-channels__legend">
      {{ t('settings.notifications.channels.title') }}
    </legend>
    <ToggleSwitch
      v-for="setting in visible"
      :key="setting.channel"
      :model-value="setting.isEnabled"
      :name="`channel-${setting.channel}`"
      :label="t(`settings.notifications.catalogue.channels.${setting.channel}`)"
      :description="hintOf(setting)"
      :disabled="disabled || !setting.isSwitchable"
      @update:model-value="emit('change', { channel: setting.channel, enabled: $event })"
    />
  </fieldset>
</template>
