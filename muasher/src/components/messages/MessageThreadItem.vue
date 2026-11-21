<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import MessageStatusBadge from './MessageStatusBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatRelativeTime } from '@/utils/datetime'

const props = defineProps({
  item: { type: Object, required: true },
  selected: { type: Boolean, default: false },
})

const { t, locale } = useI18n()
const age = computed(() => formatRelativeTime(props.item.lastActivityAt, locale.value))
</script>

<template>
  <li class="message-item" :class="{ 'is-selected': selected, 'is-unread': item.hasUnreadReply }">
    <router-link
      class="message-item__link"
      :to="{ name: ROUTE_NAMES.MESSAGES_THREAD, params: { id: item.id }, query: $route.query }"
      :aria-current="selected ? 'page' : undefined"
    >
      <span class="message-item__head">
        <span v-if="item.hasUnreadReply" class="message-item__dot" aria-hidden="true"></span>
        <span class="message-item__title">{{
          t(`public.home.contact.form.enquiryTypes.${item.enquiryType}`)
        }}</span>
        <span v-if="item.hasUnreadReply" class="message-item__sr">{{
          t('portal.messages.unreadReply')
        }}</span>
        <span class="message-item__age">{{ age }}</span>
      </span>
      <span v-if="item.preview" class="message-item__preview">{{ item.preview }}</span>
      <span class="message-item__foot">
        <bdi class="message-item__reference" dir="ltr">{{ item.reference }}</bdi>
        <MessageStatusBadge :status="item.status" />
      </span>
    </router-link>
  </li>
</template>
