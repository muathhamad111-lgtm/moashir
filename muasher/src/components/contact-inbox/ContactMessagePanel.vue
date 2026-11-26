<script setup>
import { computed, nextTick, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import ContactPanelControls from './ContactPanelControls.vue'
import ContactThreadEntries from './ContactThreadEntries.vue'
import ContactComposer from './ContactComposer.vue'
import { useContactThread } from '@/composables/useContactThread'
import { CONTACT_ENTRY_KINDS } from '@/constants/contactInbox'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'
import { formatRiyadhDateTime } from '@/utils/datetime'

const props = defineProps({
  messageId: { type: String, required: true },
  assignees: { type: Array, default: () => [] },
})

const emit = defineEmits(['changed'])
const { t, locale } = useI18n()
const thread = useContactThread(toRef(props, 'messageId'))
const { message, status, errorMessage, busy, fieldErrors, isOpen, reasonPrompt } = thread

// A contact message has no subject field: the first line of the sender's first message reads as one.
const subject = computed(() => {
  const first = message.value?.entries.find((entry) => entry.kind === CONTACT_ENTRY_KINDS.MESSAGE)
  return first?.body.split('\n')[0] ?? ''
})
const closedLine = computed(() =>
  t('contactInbox.thread.closedByAt', {
    name: message.value?.closedBy?.name ?? '',
    time: formatRiyadhDateTime(message.value?.closedAt, locale.value),
  }),
)
const body = ref(null)
const firstError = (key) => fieldErrors.value?.[key]?.[0] ?? ''

async function run(action, ...args) {
  const done = await action(...args)
  if (done) emit('changed')
  return done
}

async function send(action, { body, done }) {
  if (await run(action, body)) done()
}

watch(
  () => props.messageId,
  () => thread.load(),
  { immediate: true },
)

// Like a chat, the conversation opens at its latest entry and follows new ones.
watch(
  () => [message.value?.id, message.value?.entries.length],
  () =>
    nextTick(() => {
      if (body.value) body.value.scrollTop = body.value.scrollHeight
    }),
)
</script>

<template>
  <article class="contact-panel" :aria-busy="status === 'loading'" data-testid="contact-panel">
    <header class="contact-panel__header">
      <RouterLink
        class="contact-panel__back"
        :to="{ name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES }"
        :aria-label="t('contactInbox.split.back')"
      >
        <AppIcon name="arrow-left" class="contact-panel__back-icon" />
        <span>{{ t('contactInbox.split.back') }}</span>
      </RouterLink>
      <div v-if="message" class="contact-panel__heading">
        <h2 class="contact-panel__title" dir="auto">{{ subject }}</h2>
        <p class="contact-panel__line">
          <span class="contact-panel__name" dir="auto">{{ message.sender.name }}</span>
          <RouterLink
            v-if="message.entity"
            class="contact-panel__entity"
            dir="auto"
            :to="{ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: message.entity.id } }"
            >{{ localizedText(message.entity.name, locale) }}</RouterLink
          >
          <span v-else-if="message.sender.organization" dir="auto">{{
            message.sender.organization
          }}</span>
          <span class="contact-panel__kind">{{
            message.isLinked ? t('contactInbox.linked') : t('contactInbox.guest')
          }}</span>
        </p>
        <p class="contact-panel__line contact-panel__line--muted">
          <span
            ><bdi dir="ltr">{{ message.reference }}</bdi></span
          >
          <span
            ><bdi dir="ltr">{{ message.sender.email }}</bdi></span
          >
        </p>
      </div>
      <RouterLink
        class="contact-panel__close"
        :to="{ name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES }"
        :aria-label="t('contactInbox.split.closePanel')"
        :title="t('contactInbox.split.closePanel')"
      >
        <AppIcon name="x" />
      </RouterLink>
    </header>

    <div v-if="status === 'loading'" class="contact-panel__body">
      <Skeleton variant="text" :count="6" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="thread.load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="envelope"
      :title="t('contactInbox.thread.missingTitle')"
      :message="t('contactInbox.thread.missingMessage')"
    />
    <template v-else-if="message">
      <ContactPanelControls
        :message="message"
        :assignees="assignees"
        :busy="busy"
        :is-open="isOpen"
        :assign-error="firstError('assigneeId')"
        @assign="run(thread.assign, $event)"
      />
      <div ref="body" class="contact-panel__body">
        <ContactThreadEntries :entries="message.entries" />
      </div>
      <footer class="contact-panel__composer">
        <div v-if="!isOpen" class="contact-panel__closed" role="status">
          <AppIcon name="lock" class="contact-panel__closed-icon" />
          <p class="contact-panel__closed-text">
            <strong>{{ t('contactInbox.thread.closedTitle') }}</strong>
            <span>{{ closedLine }}</span>
          </p>
          <BaseButton
            variant="secondary"
            size="sm"
            icon="refresh"
            :loading="busy === 'reopen'"
            @click="run(thread.reopen)"
            >{{ t('contactInbox.thread.reopen') }}</BaseButton
          >
        </div>
        <ContactComposer
          v-else
          :key="`${message.id}-${message.status}`"
          :busy="busy"
          :server-error="firstError('body')"
          @reply="send(thread.reply, $event)"
          @note="send(thread.addNote, $event)"
          @close-message="run(thread.close)"
        />
      </footer>
    </template>

    <ReasonDialog
      v-model="reasonPrompt.open.value"
      :title="t('review.reasonTitle')"
      :submit-label="t('actions.confirm')"
      :action="reasonPrompt.provide"
      action-name="contactInbox.superAdminReason"
    />
  </article>
</template>
