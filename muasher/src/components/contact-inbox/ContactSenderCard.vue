<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { localizedText } from '@/utils/localizedText'

defineProps({
  message: { type: Object, required: true },
})

const { t, locale } = useI18n()
</script>

<template>
  <Card class="contact-sender">
    <template #header>
      <h2 class="card__title-text">{{ t('contactInbox.sender.title') }}</h2>
    </template>
    <dl class="contact-sender__facts">
      <div>
        <dt>{{ t('contactInbox.sender.name') }}</dt>
        <dd>{{ message.sender.name }}</dd>
      </div>
      <div>
        <dt>{{ t('contactInbox.sender.email') }}</dt>
        <dd>
          <bdi dir="ltr">{{ message.sender.email }}</bdi>
        </dd>
      </div>
      <div v-if="message.sender.mobile">
        <dt>{{ t('contactInbox.sender.mobile') }}</dt>
        <dd>
          <bdi dir="ltr">{{ message.sender.mobile }}</bdi>
        </dd>
      </div>
      <div v-if="message.sender.organization">
        <dt>{{ t('contactInbox.sender.organization') }}</dt>
        <dd>{{ message.sender.organization }}</dd>
      </div>
      <div>
        <dt>{{ t('contactInbox.sender.account') }}</dt>
        <dd>{{ message.isLinked ? t('contactInbox.linked') : t('contactInbox.guest') }}</dd>
      </div>
      <div v-if="message.entity">
        <dt>{{ t('contactInbox.sender.entity') }}</dt>
        <dd>
          <RouterLink :to="{ name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: message.entity.id } }">
            {{ localizedText(message.entity.name, locale) }}
          </RouterLink>
        </dd>
      </div>
    </dl>
  </Card>
</template>
