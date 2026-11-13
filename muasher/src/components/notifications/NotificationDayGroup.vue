<script setup>
import { useI18n } from 'vue-i18n'
import NotificationCenterItem from './NotificationCenterItem.vue'

defineProps({
  group: { type: Object, required: true },
  busyIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['open', 'archive'])
const { t } = useI18n()
</script>

<template>
  <section class="notification-day-group" :aria-labelledby="`notification-group-${group.key}`">
    <h2 :id="`notification-group-${group.key}`" class="notification-day-group__title">
      {{ t(`notifications.center.groups.${group.key}`) }}
    </h2>
    <ul class="notification-day-group__list">
      <NotificationCenterItem
        v-for="item in group.items"
        :key="item.id"
        :notification="item"
        :busy="busyIds.includes(item.id)"
        @open="emit('open', $event)"
        @archive="emit('archive', $event)"
      />
    </ul>
  </section>
</template>
