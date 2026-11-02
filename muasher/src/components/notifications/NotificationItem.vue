<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { NOTIFICATION_TYPE_ICONS } from '@/constants/notifications'
import { formatRelativeTime } from '@/utils/datetime'

const props = defineProps({
  notification: { type: Object, required: true },
})

const emit = defineEmits(['open'])

const { t, locale } = useI18n()

const icon = computed(
  () => NOTIFICATION_TYPE_ICONS[props.notification.type] ?? NOTIFICATION_TYPE_ICONS.info,
)
const title = computed(() => props.notification.title?.[locale.value] ?? '')
const body = computed(() => props.notification.body?.[locale.value] ?? '')
const time = computed(() => formatRelativeTime(props.notification.createdAt, locale.value))
</script>

<template>
  <router-link
    class="notification-item"
    :class="[`notification-item--${notification.type}`, { 'is-unread': !notification.read }]"
    :to="notification.link"
    @click="emit('open', notification)"
  >
    <span class="notification-item__icon">
      <i :class="icon" aria-hidden="true"></i>
    </span>
    <span class="notification-item__content">
      <span class="notification-item__title">{{ title }}</span>
      <span class="notification-item__body">{{ body }}</span>
      <span class="notification-item__time">{{ time }}</span>
    </span>
    <span
      v-if="!notification.read"
      class="notification-item__dot"
      role="img"
      :aria-label="t('components.notifications.unreadLabel')"
    ></span>
  </router-link>
</template>
