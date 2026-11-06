<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@unhead/vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import Pagination from '@/components/data-list/Pagination.vue'
import PortalAreaHeader from '@/components/portal/PortalAreaHeader.vue'
import MessageThreadItem from '@/components/messages/MessageThreadItem.vue'
import MessageThread from '@/components/messages/MessageThread.vue'
import NewMessageDialog from '@/components/messages/NewMessageDialog.vue'
import { useMyMessages } from '@/composables/useMyMessages'
import { useMessageThread } from '@/composables/useMessageThread'
import { usePortalIdentity } from '@/composables/usePortalIdentity'
import { useToast } from '@/composables/useToast'
import { ROUTE_NAMES } from '@/constants/routeNames'
import AppIcon from '@/components/ui/AppIcon.vue'

const PANEL_ID = 'messages-panel'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const identity = usePortalIdentity()

const messageId = computed(() => route.params.id ?? null)
const dialogOpen = ref(false)

const list = useMyMessages()
const conversation = useMessageThread(messageId, { onChange: list.applyThread })

useHead({ title: () => t('portal.messages.title') })

async function onClosed() {
  toast.info(t('portal.messages.reply.closed'))
  await conversation.load()
  await list.load()
}

async function onCreated({ id, reference }) {
  toast.success(t('portal.messages.dialog.sent', { reference }))
  await list.load()
  if (id) router.push({ name: ROUTE_NAMES.MESSAGES_THREAD, params: { id } })
}
</script>

<template>
  <section class="portal-area messages-page" :class="{ 'messages-page--thread': messageId }">
    <PortalAreaHeader
      band
      class="messages-page__header"
      :eyebrow="t('portal.messages.eyebrow')"
      :title="t('portal.messages.title')"
      :subtitle="t('portal.messages.subtitle')"
      :tabs="list.tabs.value"
      :tab="list.tab.value"
      :tabs-label="t('portal.messages.tabsLabel')"
      :panel-id="PANEL_ID"
      @update:tab="list.tab.value = $event"
    >
      <template #actions>
        <BaseButton variant="primary" icon="envelope" @click="dialogOpen = true">
          {{ t('portal.messages.new') }}
        </BaseButton>
      </template>
    </PortalAreaHeader>

    <div class="portal-area__body portal-area__body--aside-start messages-page__body">
      <div
        :id="PANEL_ID"
        class="messages-page__list"
        role="tabpanel"
        :aria-labelledby="`${PANEL_ID}-tab-${list.tab.value}`"
      >
        <Skeleton v-if="list.status.value === 'loading'" variant="block" :count="3" />
        <ErrorState
          v-else-if="list.status.value === 'error'"
          :message="list.errorMessage.value"
          @retry="list.load"
        />
        <EmptyState
          v-else-if="list.status.value === 'empty'"
          icon="chat"
          :title="t(`portal.messages.empty.${list.tab.value}.title`)"
          :message="t(`portal.messages.empty.${list.tab.value}.message`)"
        />
        <ul v-else class="messages-page__items">
          <MessageThreadItem
            v-for="item in list.items.value"
            :key="item.id"
            :item="item"
            :selected="item.id === messageId"
          />
        </ul>
        <Pagination
          v-if="list.lastPage.value > 1"
          :page="list.page.value"
          :last-page="list.lastPage.value"
          @update:page="list.setPage"
        />
        <p class="messages-page__note">
          <AppIcon name="info-circle" />
          {{ t('portal.messages.guestNote') }}
        </p>
      </div>

      <div class="messages-page__thread">
        <div v-if="!messageId" class="messages-page__placeholder portal-surface">
          <AppIcon name="chat" />
          <p class="portal-surface__text">{{ t('portal.messages.choose') }}</p>
        </div>
        <Skeleton v-else-if="conversation.status.value === 'loading'" variant="block" :count="2" />
        <EmptyState
          v-else-if="conversation.status.value === 'missing'"
          icon="folder"
          :title="t('portal.messages.missing.title')"
          :message="t('portal.messages.missing.message')"
        />
        <ErrorState
          v-else-if="conversation.status.value === 'error'"
          :message="conversation.errorMessage.value"
          @retry="conversation.load"
        />
        <MessageThread
          v-else-if="conversation.thread.value"
          :key="conversation.thread.value.id"
          :thread="conversation.thread.value"
          :sender-name="identity.userName.value"
          :sender-email="identity.email.value"
          @replied="conversation.apply"
          @closed="onClosed"
          @new-message="dialogOpen = true"
        />
      </div>
    </div>

    <NewMessageDialog v-model="dialogOpen" @created="onCreated" />
  </section>
</template>
