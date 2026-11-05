<script setup>
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import MobileActionBar from '@/components/portal/MobileActionBar.vue'
import MessageStatusBadge from './MessageStatusBadge.vue'
import MessageBubble from './MessageBubble.vue'
import MessageReplyForm from './MessageReplyForm.vue'
import { useMessageReply } from '@/composables/useMessageReply'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatDate, isoDate, riyadhDateKey } from '@/utils/datetime'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  thread: { type: Object, required: true },
  senderName: { type: String, default: '' },
  senderEmail: { type: String, default: '' },
})

const emit = defineEmits(['replied', 'closed', 'new-message'])
const { t, locale } = useI18n()

const reply = useMessageReply({
  thread: toRef(props, 'thread'),
  onReplied: (data) => emit('replied', data),
  onClosed: () => emit('closed'),
})

const typeLabel = computed(() =>
  t(`public.home.contact.form.enquiryTypes.${props.thread.enquiryType}`),
)
const sentOn = computed(() => isoDate(props.thread.createdAt))
const closedOn = computed(() => isoDate(props.thread.closedAt))
const days = computed(() => {
  const groups = []
  for (const entry of props.thread.entries ?? []) {
    const key = riyadhDateKey(entry.createdAt)
    const last = groups[groups.length - 1]
    if (last?.key === key) last.entries.push(entry)
    else groups.push({ key, label: dayLabel(entry.createdAt), entries: [entry] })
  }
  return groups
})

function dayLabel(value) {
  return formatDate(value, locale.value, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'Asia/Riyadh',
  })
}
</script>

<template>
  <article class="message-thread" :aria-labelledby="`thread-${thread.id}`">
    <header class="message-thread__header">
      <router-link
        class="message-thread__back"
        :to="{ name: ROUTE_NAMES.MESSAGES_INDEX, query: $route.query }"
        :aria-label="t('portal.messages.back')"
      >
        <AppIcon name="arrow-left" />
      </router-link>
      <div class="message-thread__heading">
        <h2 :id="`thread-${thread.id}`" class="message-thread__title">{{ typeLabel }}</h2>
        <p class="message-thread__meta">
          <span
            >{{ t('portal.messages.sentOn') }} <bdi dir="ltr">{{ sentOn }}</bdi></span
          >
          <span class="message-thread__meta-type" aria-hidden="true">·</span>
          <span class="message-thread__meta-type">{{
            t('portal.messages.typeLine', { type: typeLabel })
          }}</span>
          <span aria-hidden="true">·</span>
          <bdi dir="ltr">{{ thread.reference }}</bdi>
        </p>
      </div>
      <MessageStatusBadge :status="thread.status" />
    </header>

    <div class="message-thread__body">
      <section
        v-for="day in days"
        :key="day.key"
        class="message-thread__day"
        :aria-label="day.label"
      >
        <p class="message-thread__day-label">{{ day.label }}</p>
        <ol class="message-thread__entries">
          <MessageBubble
            v-for="entry in day.entries"
            :key="entry.id"
            :entry="entry"
            :sender-name="senderName"
          />
        </ol>
      </section>
      <p v-if="thread.canReply && senderEmail" class="message-thread__email">
        <AppIcon name="envelope" />
        <span>{{ t('portal.messages.alsoEmailed') }}</span>
        <bdi dir="ltr">{{ senderEmail }}</bdi>
      </p>
      <p v-if="thread.closedAt" class="message-thread__system">
        <AppIcon name="check-circle" />
        {{ t('portal.messages.closedBy') }} <bdi dir="ltr">{{ closedOn }}</bdi>
      </p>
    </div>

    <footer
      class="message-thread__footer"
      :class="{ 'message-thread__footer--reply': thread.canReply }"
    >
      <MessageReplyForm
        v-if="thread.canReply"
        v-model="reply.draft.value"
        class="message-thread__composer"
        :sending="reply.sending.value"
        :error="reply.error.value"
        @send="reply.send"
      />
      <div v-else class="message-thread__closed" role="note">
        <AppIcon name="lock" />
        <p>{{ t('portal.messages.closedNotice') }}</p>
        <BaseButton variant="secondary" icon="envelope" @click="emit('new-message')">
          {{ t('portal.messages.new') }}
        </BaseButton>
      </div>
    </footer>

    <MobileActionBar v-if="thread.canReply" :label="t('portal.messages.reply.barLabel')">
      <MessageReplyForm
        v-model="reply.draft.value"
        compact
        :sending="reply.sending.value"
        :error="reply.error.value"
        @send="reply.send"
      />
    </MobileActionBar>
  </article>
</template>
