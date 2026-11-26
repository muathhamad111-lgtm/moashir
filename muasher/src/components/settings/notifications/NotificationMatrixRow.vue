<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import NotificationTypeLabel from './NotificationTypeLabel.vue'
import NotificationEditLink from './NotificationEditLink.vue'
import { NOTIFICATION_CHANNELS } from '@/constants/notificationCatalogue'
import { notificationTypeName } from '@/utils/notificationTypeName'

const props = defineProps({
  type: { type: Object, required: true },
  board: { type: Object, required: true },
})

const { t, te } = useI18n()
const name = computed(() => notificationTypeName(props.type.code, t, te))
const isShared = computed(() => props.type.audienceKinds.length > 1)

function switchLabel(audience, channel) {
  return t('settings.notifications.catalogue.switchLabel', {
    channel: t(`settings.notifications.catalogue.channels.${channel}`),
    audience: t(`settings.notifications.catalogue.audienceKinds.${audience}`),
    name: name.value,
  })
}
</script>

<template>
  <li class="notification-matrix__row">
    <NotificationTypeLabel
      :type="type"
      :show-audiences="false"
      class="notification-matrix__label"
    />
    <div class="notification-matrix__audiences">
      <div
        v-for="audience in type.audienceKinds"
        :key="audience"
        class="notification-matrix__audience"
      >
        <span v-if="isShared" class="notification-type-label__audience" :data-kind="audience">
          {{ t(`settings.notifications.catalogue.audienceKinds.${audience}`) }}
        </span>
        <div class="notification-matrix__switches">
          <div
            v-for="channel in NOTIFICATION_CHANNELS"
            :key="channel"
            class="notification-matrix__cell"
            :data-channel="channel"
          >
            <span class="notification-matrix__cell-label" aria-hidden="true">
              {{ t(`settings.notifications.catalogue.channelsShort.${channel}`) }}
            </span>
            <ToggleSwitch
              :model-value="board.isOn(type, audience, channel)"
              :name="`${type.code}-${audience}-${channel}`"
              :label="switchLabel(audience, channel)"
              hide-label
              :disabled="type.isCore || board.busy.value"
              @update:model-value="board.switchOne({ type, audience, channel, enabled: $event })"
            />
          </div>
        </div>
      </div>
    </div>
    <NotificationEditLink :code="type.code" class="notification-matrix__edit" />
  </li>
</template>
