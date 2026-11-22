<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  kinds: { type: Array, required: true },
  audiences: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <section class="notification-recipients" aria-labelledby="notification-recipients-title">
    <h2 id="notification-recipients-title" class="notification-recipients__title">
      {{ t('settings.notifications.texts.recipients.title') }}
    </h2>
    <ul class="notification-recipients__kinds">
      <li
        v-for="kind in kinds"
        :key="kind"
        class="notification-type-label__audience"
        :data-kind="kind"
      >
        {{ t(`settings.notifications.catalogue.audienceKinds.${kind}`) }}
      </li>
      <li
        v-for="audience in audiences.filter((value) => value !== 'account_holder')"
        :key="audience"
        class="notification-recipients__role"
      >
        {{ t(`settings.notifications.catalogue.audiences.${audience}`) }}
      </li>
    </ul>
    <p class="notification-recipients__note">
      <AppIcon name="lock" size="sm" />
      {{ t('settings.notifications.texts.recipients.note') }}
    </p>
  </section>
</template>
