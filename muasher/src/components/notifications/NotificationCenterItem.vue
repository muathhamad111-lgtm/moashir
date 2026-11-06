<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { notificationIconOf } from '@/constants/notifications'
import { formatRelativeTime, riyadhDateKey } from '@/utils/datetime'
import { notificationGroupOf } from '@/utils/notificationGroups'
import { isInternalPath } from '@/utils/internalPath'
import ReferenceText from '@/components/ui/ReferenceText.vue'
import PathBadge from '@/components/ui/PathBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  notification: { type: Object, required: true },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['open', 'archive'])

const { t, locale } = useI18n()

const icon = computed(() => notificationIconOf(props.notification))
const title = computed(() => props.notification.title?.[locale.value] ?? '')
const body = computed(() => props.notification.body?.[locale.value] ?? '')
const isOlder = computed(() => notificationGroupOf(props.notification.createdAt) === 'older')
const time = computed(() =>
  isOlder.value
    ? riyadhDateKey(props.notification.createdAt)
    : formatRelativeTime(props.notification.createdAt, locale.value),
)
const target = computed(() =>
  isInternalPath(props.notification.link) ? props.notification.link : null,
)
</script>

<template>
  <li
    class="notification-center-item"
    :class="[
      `notification-center-item--${notification.category}`,
      { 'is-unread': !notification.isRead },
    ]"
  >
    <span class="notification-center-item__icon" aria-hidden="true">
      <AppIcon :name="icon" />
    </span>
    <div class="notification-center-item__main">
      <a
        class="notification-center-item__title"
        :href="target ?? '#'"
        @click.prevent="emit('open', notification)"
      >
        <ReferenceText :text="title" />
      </a>
      <p class="notification-center-item__body"><ReferenceText :text="body" /></p>
      <p class="notification-center-item__meta">
        <time class="notification-center-item__time" :datetime="notification.createdAt">
          <bdi>{{ time }}</bdi>
        </time>
        <PathBadge
          v-if="notification.path"
          :path="notification.path"
          :label="notification.pathLabel"
          compact
        />
      </p>
    </div>
    <span v-if="!notification.isRead" class="notification-center-item__unread">
      <span class="notification-center-item__dot" aria-hidden="true"></span>
      <span class="notification-center-item__sr">{{ t('notifications.center.unread') }}</span>
    </span>
    <button
      type="button"
      class="notification-center-item__archive"
      :disabled="busy"
      :aria-label="t('notifications.center.archiveLabel', { title })"
      :title="t('notifications.center.archive')"
      @click="emit('archive', notification)"
    >
      <AppIcon name="folder" />
    </button>
  </li>
</template>
