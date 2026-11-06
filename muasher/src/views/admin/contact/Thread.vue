<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import { useRoute, RouterLink } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import MessageStatusBadge from '@/components/messages/MessageStatusBadge.vue'
import ContactSenderCard from '@/components/contact-inbox/ContactSenderCard.vue'
import ContactThreadEntries from '@/components/contact-inbox/ContactThreadEntries.vue'
import ContactComposer from '@/components/contact-inbox/ContactComposer.vue'
import ContactAssignControl from '@/components/contact-inbox/ContactAssignControl.vue'
import { useContactThread } from '@/composables/useContactThread'
import { useContactAssignees } from '@/composables/useContactAssignees'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatRiyadhDateTime } from '@/utils/datetime'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t, locale } = useI18n()
const route = useRoute()
const messageId = computed(() => String(route.params.id))
const thread = useContactThread(messageId)
const { message, status, errorMessage, busy, fieldErrors, isOpen, reasonPrompt } = thread
const { assignees, load: loadAssignees } = useContactAssignees()

useHead({ title: () => message.value?.reference || t('contactInbox.thread.title') })

const firstError = (key) => fieldErrors.value?.[key]?.[0] ?? ''
const createdAt = computed(() => formatRiyadhDateTime(message.value?.createdAt, locale.value))

async function send(action, { body, done }) {
  if (await action(body)) done()
}

onMounted(() => {
  thread.load()
  loadAssignees()
})
</script>

<template>
  <section class="contact-thread">
    <header class="contact-thread__header">
      <RouterLink class="contact-thread__back" :to="{ name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES }">
        <AppIcon name="arrow-left" />
        {{ t('contactInbox.thread.back') }}
      </RouterLink>
      <h1 class="contact-thread__title">
        <span>{{ t('contactInbox.thread.title') }}</span>
        <bdi v-if="message" dir="ltr">{{ message.reference }}</bdi>
      </h1>
      <p v-if="message" class="contact-thread__meta">
        <MessageStatusBadge :status="message.status" />
        <span>{{ t(`public.home.contact.form.enquiryTypes.${message.enquiryType}`) }}</span>
        <span>
          {{ t('contactInbox.thread.received') }} <bdi>{{ createdAt }}</bdi>
        </span>
      </p>
    </header>

    <div v-if="status === 'loading'" aria-busy="true">
      <Skeleton variant="block" /><Skeleton variant="text" :count="4" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="thread.load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="envelope"
      :title="t('contactInbox.thread.missingTitle')"
      :message="t('contactInbox.thread.missingMessage')"
    />
    <div v-else-if="message" class="contact-thread__layout">
      <div class="contact-thread__main">
        <Card>
          <template #header>
            <h2 class="card__title-text">{{ t('contactInbox.thread.conversation') }}</h2>
          </template>
          <ContactThreadEntries :entries="message.entries" />
        </Card>
        <Card>
          <template #header>
            <h2 class="card__title-text">{{ t('contactInbox.composer.title') }}</h2>
          </template>
          <p v-if="!isOpen" class="contact-thread__closed" role="note">
            {{ t('contactInbox.thread.closedNote') }}
          </p>
          <ContactComposer
            :key="message.status"
            :can-reply="isOpen"
            :busy="busy"
            :server-error="firstError('body')"
            @reply="send(thread.reply, $event)"
            @note="send(thread.addNote, $event)"
          />
        </Card>
      </div>
      <aside class="contact-thread__aside">
        <Card>
          <template #header>
            <h2 class="card__title-text">{{ t('contactInbox.thread.handling') }}</h2>
          </template>
          <ContactAssignControl
            :assignee="message.assignee"
            :assignees="assignees"
            :busy="busy === 'assign'"
            :disabled="!isOpen"
            :error="firstError('assigneeId')"
            @assign="thread.assign"
          />
          <div class="contact-thread__state">
            <BaseButton
              v-if="isOpen"
              variant="secondary"
              size="sm"
              icon="check-circle"
              :loading="busy === 'close'"
              @click="thread.close"
              >{{ t('contactInbox.thread.close') }}</BaseButton
            >
            <BaseButton
              v-else
              variant="secondary"
              size="sm"
              icon="refresh"
              :loading="busy === 'reopen'"
              @click="thread.reopen"
              >{{ t('contactInbox.thread.reopen') }}</BaseButton
            >
            <p v-if="message.closedBy" class="contact-thread__closed-by">
              {{ t('contactInbox.thread.closedBy', { name: message.closedBy.name }) }}
            </p>
          </div>
        </Card>
        <ContactSenderCard :message="message" />
      </aside>
    </div>

    <ReasonDialog
      v-model="reasonPrompt.open.value"
      :title="t('review.reasonTitle')"
      :message="t('review.reasonMessage')"
      :submit-label="t('actions.confirm')"
      :action="reasonPrompt.provide"
      action-name="contactInbox.superAdminReason"
    />
  </section>
</template>
