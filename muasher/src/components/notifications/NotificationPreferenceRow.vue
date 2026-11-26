<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseCheckbox from '@/components/form/BaseCheckbox.vue'
import { EDITABLE_NOTIFICATION_CHANNELS } from '@/constants/notifications'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  row: { type: Object, required: true },
  isEnabled: { type: Function, required: true },
})

const emit = defineEmits(['toggle'])
const { t } = useI18n()

const label = computed(() => t(`notifications.typeNames.${props.row.code}`))
const channels = computed(() =>
  props.row.channels.filter((channel) => EDITABLE_NOTIFICATION_CHANNELS.includes(channel.channel)),
)
</script>

<template>
  <li class="notification-preference-row">
    <span class="notification-preference-row__label">
      {{ label }}
      <span v-if="row.isMandatory" class="notification-preference-row__locked">
        <AppIcon name="lock" />
        {{ t('notifications.preferences.mandatory') }}
      </span>
    </span>
    <span class="notification-preference-row__channels">
      <BaseCheckbox
        v-for="channel in channels"
        :key="channel.channel"
        :model-value="isEnabled(row.code, channel.channel)"
        :label="t(`notifications.channels.${channel.channel}`)"
        :name="`${row.code}-${channel.channel}`"
        :disabled="!channel.configurable"
        @update:model-value="emit('toggle', row.code, channel.channel, $event)"
      />
    </span>
  </li>
</template>
