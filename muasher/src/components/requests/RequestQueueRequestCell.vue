<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { localizedText } from '@/utils/localizedText'

// The request column of the queue: the number (link to the request), then the entity's name.
defineProps({
  item: { type: Object, required: true },
  to: { type: Object, required: true },
})

const { locale } = useI18n()
</script>

<template>
  <span class="request-queue__request">
    <RouterLink class="request-queue__link" :to="to">
      <bdi dir="ltr">{{ item.number }}</bdi>
    </RouterLink>
    <span v-if="item.entity" class="request-queue__entity-name">{{
      localizedText(item.entity.name, locale)
    }}</span>
  </span>
</template>
