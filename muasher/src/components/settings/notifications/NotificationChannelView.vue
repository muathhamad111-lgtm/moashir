<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Tabs from '@/components/ui/Tabs.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import NotificationGroupsAccordion from './NotificationGroupsAccordion.vue'
import NotificationGroupActions from './NotificationGroupActions.vue'
import NotificationTypeLabel from './NotificationTypeLabel.vue'
import NotificationEditLink from './NotificationEditLink.vue'
import { NOTIFICATION_CHANNELS } from '@/constants/notificationCatalogue'
import { formatNumber } from '@/utils/formatNumber'
import { notificationTypeName } from '@/utils/notificationTypeName'

const props = defineProps({
  groups: { type: Array, required: true },
  types: { type: Array, required: true },
  board: { type: Object, required: true },
  expanded: { type: Boolean, default: false },
})

const emit = defineEmits(['bulk'])
const { t, te, n } = useI18n()
const channel = ref(NOTIFICATION_CHANNELS[0])

const tabs = computed(() =>
  NOTIFICATION_CHANNELS.map((value) => {
    const on = props.board.countOn(props.types, value)
    return {
      value,
      label: t(`settings.notifications.catalogue.channelTabs.${value}`),
      badge: `${formatNumber(n, on)}/${formatNumber(n, props.types.length)}`,
      badgeLabel: t('settings.notifications.catalogue.onCount', { on, total: props.types.length }),
    }
  }),
)

function switchLabel(type, audience) {
  return t('settings.notifications.catalogue.switchLabel', {
    channel: t(`settings.notifications.catalogue.channels.${channel.value}`),
    audience: t(`settings.notifications.catalogue.audienceKinds.${audience}`),
    name: notificationTypeName(type.code, t, te),
  })
}

function bulk(list, enabled) {
  emit('bulk', {
    scope: 'codes',
    codes: list.map((type) => type.code),
    channels: [channel.value],
    enabled,
  })
}
</script>

<template>
  <Tabs v-model="channel" class="notification-catalogue__channels" :tabs="tabs">
    <div class="notification-channel-view">
      <NotificationGroupActions
        :disabled="board.busy.value"
        @enable="bulk(types, true)"
        @disable="bulk(types, false)"
      />
      <NotificationGroupsAccordion :groups="groups" :expanded="expanded">
        <template #group="{ group }">
          <NotificationGroupActions
            class="notification-channel-view__group-bulk"
            :disabled="board.busy.value"
            @enable="bulk(group.types, true)"
            @disable="bulk(group.types, false)"
          />
          <ul class="notification-channel-view__rows">
            <li v-for="type in group.types" :key="type.code" class="notification-channel-view__row">
              <NotificationTypeLabel :type="type" :show-audiences="false" />
              <div class="notification-channel-view__switches">
                <div
                  v-for="audience in type.audienceKinds"
                  :key="audience"
                  class="notification-matrix__cell"
                >
                  <span
                    v-if="type.audienceKinds.length > 1"
                    class="notification-type-label__audience"
                    :data-kind="audience"
                  >
                    {{ t(`settings.notifications.catalogue.audienceKinds.${audience}`) }}
                  </span>
                  <ToggleSwitch
                    :model-value="board.isOn(type, audience, channel)"
                    :name="`${type.code}-${audience}-${channel}`"
                    :label="switchLabel(type, audience)"
                    hide-label
                    :disabled="type.isCore || board.busy.value"
                    @update:model-value="
                      board.switchOne({ type, audience, channel, enabled: $event })
                    "
                  />
                </div>
              </div>
              <NotificationEditLink :code="type.code" :channel="channel" />
            </li>
          </ul>
        </template>
      </NotificationGroupsAccordion>
    </div>
  </Tabs>
</template>
