<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import { NOTIFICATION_TYPE_ICONS, NOTIFICATION_TYPE_VARIANTS } from '@/constants/notifications'
import { formatRelativeTime, formatDate } from '@/utils/datetime'

const props = defineProps({
  notification: { type: Object, required: true },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['open', 'mark-read', 'audit'])

const { t, locale } = useI18n()

const icon = computed(
  () => NOTIFICATION_TYPE_ICONS[props.notification.type] ?? NOTIFICATION_TYPE_ICONS.info,
)
const variant = computed(() => NOTIFICATION_TYPE_VARIANTS[props.notification.type] ?? 'info')
const typeLabel = computed(() => t(`notifications.types.${props.notification.type}`))
const title = computed(() => props.notification.title?.[locale.value] ?? '')
const body = computed(() => props.notification.body?.[locale.value] ?? '')
const relativeTime = computed(() => formatRelativeTime(props.notification.createdAt, locale.value))
const absoluteTime = computed(() =>
  formatDate(props.notification.createdAt, locale.value, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }),
)
</script>

<template>
  <li
    class="notification-center-item"
    :class="[`notification-center-item--${notification.type}`, { 'is-unread': !notification.read }]"
  >
    <span class="notification-center-item__icon" aria-hidden="true">
      <i :class="icon"></i>
    </span>
    <div class="notification-center-item__main">
      <div class="notification-center-item__meta">
        <Badge :variant="variant">{{ typeLabel }}</Badge>
        <span class="notification-center-item__code">{{ notification.code }}</span>
        <span v-if="!notification.read" class="notification-center-item__unread" role="status">
          <span class="notification-center-item__dot" aria-hidden="true"></span>
          {{ t('notifications.center.unread') }}
        </span>
      </div>
      <router-link
        class="notification-center-item__title"
        :to="notification.link"
        @click="emit('open', notification)"
      >
        {{ title }}
      </router-link>
      <p class="notification-center-item__body">{{ body }}</p>
      <div class="notification-center-item__footer">
        <time
          class="notification-center-item__time"
          :datetime="notification.createdAt"
          :title="absoluteTime"
        >
          {{ relativeTime }}
        </time>
        <div class="notification-center-item__actions">
          <router-link
            class="notification-center-item__link"
            :to="notification.link"
            @click="emit('open', notification)"
          >
            <i class="fa-solid fa-arrow-up-right-from-square u-flip-in-rtl" aria-hidden="true"></i>
            {{ t('notifications.center.openLink') }}
          </router-link>
          <button
            v-if="!notification.read"
            type="button"
            class="notification-center-item__action"
            :disabled="busy"
            @click="emit('mark-read', notification.id)"
          >
            <i class="fa-solid fa-check" aria-hidden="true"></i>
            {{ t('notifications.center.markRead') }}
          </button>
          <button
            type="button"
            class="notification-center-item__action"
            @click="emit('audit', notification)"
          >
            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
            {{ t('notifications.center.audit') }}
          </button>
        </div>
      </div>
    </div>
  </li>
</template>
