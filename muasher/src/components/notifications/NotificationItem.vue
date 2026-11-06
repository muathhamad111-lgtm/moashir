<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { notificationIconOf } from '@/constants/notifications'
import { formatRelativeTime } from '@/utils/datetime'
import { isInternalPath } from '@/utils/internalPath'
import ReferenceText from '@/components/ui/ReferenceText.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  notification: { type: Object, required: true },
  showBody: { type: Boolean, default: true },
})

const emit = defineEmits(['open'])

const { t, locale } = useI18n()

const icon = computed(() => notificationIconOf(props.notification))
const title = computed(() => props.notification.title?.[locale.value] ?? '')
const body = computed(() => props.notification.body?.[locale.value] ?? '')
const time = computed(() => formatRelativeTime(props.notification.createdAt, locale.value))
const target = computed(() =>
  isInternalPath(props.notification.link) ? props.notification.link : null,
)

function open(event) {
  if (!target.value) event.preventDefault()
  emit('open', props.notification)
}
</script>

<template>
  <a
    class="notification-item"
    :class="[`notification-item--${notification.category}`, { 'is-unread': !notification.isRead }]"
    :href="target ?? '#'"
    @click.prevent="open"
  >
    <span class="notification-item__icon">
      <AppIcon :name="icon" />
    </span>
    <span class="notification-item__content">
      <span class="notification-item__title"><ReferenceText :text="title" /></span>
      <span v-if="showBody" class="notification-item__body"><ReferenceText :text="body" /></span>
      <span class="notification-item__meta">
        <span class="notification-item__time">{{ time }}</span>
        <PathBadge
          v-if="notification.path"
          :path="notification.path"
          :label="notification.pathLabel"
          compact
        />
      </span>
    </span>
    <span
      v-if="!notification.isRead"
      class="notification-item__dot"
      role="img"
      :aria-label="t('components.notifications.unreadLabel')"
    ></span>
  </a>
</template>
