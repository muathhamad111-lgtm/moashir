<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import ContactInboxStatusBadge from '@/components/contact-inbox/ContactInboxStatusBadge.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { formatRiyadhDateTime } from '@/utils/datetime'

defineProps({
  items: { type: Array, default: () => [] },
  selectedId: { type: String, default: '' },
})

const { t, locale } = useI18n()
const route = useRoute()

function time(item) {
  return formatRiyadhDateTime(item.lastActivityAt, locale.value)
}
</script>

<template>
  <ol class="contact-list" :aria-label="t('contactInbox.split.listLabel')">
    <li v-for="item in items" :key="item.id">
      <RouterLink
        class="contact-list__item"
        :class="{
          'contact-list__item--selected': item.id === selectedId,
          'contact-list__item--new': item.inboxStatus === 'new',
          'contact-list__item--awaiting': item.inboxStatus === 'awaiting-reply',
        }"
        :aria-current="item.id === selectedId ? 'true' : undefined"
        :data-message-id="item.id"
        :to="{
          name: ROUTE_NAMES.ADMIN_CONTACT_MESSAGE,
          params: { id: item.id },
          query: route.query,
        }"
      >
        <span class="contact-list__row">
          <span class="contact-list__sender" dir="auto">{{ item.sender.name }}</span>
          <time class="contact-list__time" :datetime="item.lastActivityAt">{{ time(item) }}</time>
        </span>
        <span class="contact-list__row">
          <span class="contact-list__excerpt" dir="auto">{{ item.excerpt }}</span>
          <ContactInboxStatusBadge :status="item.inboxStatus" />
        </span>
        <span class="contact-list__meta">
          <bdi dir="ltr">{{ item.reference }}</bdi>
          <span>{{ t(`public.home.contact.form.enquiryTypes.${item.enquiryType}`) }}</span>
          <span dir="auto">{{ item.assignee?.name || t('contactInbox.unassigned') }}</span>
        </span>
      </RouterLink>
    </li>
  </ol>
</template>
