<script setup>
import { useI18n } from 'vue-i18n'
import NotificationGroupsAccordion from './NotificationGroupsAccordion.vue'
import NotificationGroupActions from './NotificationGroupActions.vue'
import NotificationMatrixRow from './NotificationMatrixRow.vue'
import { NOTIFICATION_CHANNELS } from '@/constants/notificationCatalogue'

defineProps({
  groups: { type: Array, required: true },
  board: { type: Object, required: true },
  expanded: { type: Boolean, default: false },
})

const emit = defineEmits(['bulk'])
const { t } = useI18n()

function bulkGroup(group, enabled) {
  emit('bulk', { scope: 'codes', codes: group.types.map((type) => type.code), enabled })
}
</script>

<template>
  <NotificationGroupsAccordion :groups="groups" :expanded="expanded">
    <template #group="{ group }">
      <div class="notification-matrix">
        <div class="notification-matrix__head">
          <NotificationGroupActions
            :disabled="board.busy.value"
            @enable="bulkGroup(group, true)"
            @disable="bulkGroup(group, false)"
          />
          <div class="notification-matrix__columns" aria-hidden="true">
            <span v-for="channel in NOTIFICATION_CHANNELS" :key="channel">
              {{ t(`settings.notifications.catalogue.channels.${channel}`) }}
            </span>
          </div>
        </div>
        <ul class="notification-matrix__rows">
          <NotificationMatrixRow
            v-for="type in group.types"
            :key="type.code"
            :type="type"
            :board="board"
          />
        </ul>
      </div>
    </template>
  </NotificationGroupsAccordion>
</template>
