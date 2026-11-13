<script setup>
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import SegmentPicker from '@/components/messaging/SegmentPicker.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useEmailCampaign } from '@/composables/useEmailCampaign'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
const {
  segments,
  recipients,
  recipientsLoading,
  compose,
  errors,
  sending,
  valid,
  loadSegments,
  searchRecipients,
  send,
} = useEmailCampaign()

onMounted(loadSegments)
</script>

<template>
  <section class="settings-form__group" aria-labelledby="email-compose-title">
    <h2 id="email-compose-title" class="settings-form__legend">
      {{ t('settings.email.composeTitle') }}
    </h2>
    <p class="settings-form__note" role="note">
      <AppIcon name="info-circle" />
      {{ t('settings.email.composeNote') }}
    </p>
    <BaseInput
      v-model="compose.subject"
      name="composeSubject"
      :label="t('settings.email.subject')"
      :placeholder="t('settings.email.subjectPlaceholder')"
      :error="errors.subject"
      required
    />
    <BaseTextarea
      v-model="compose.body"
      name="composeBody"
      :rows="6"
      :label="t('settings.email.body')"
      :placeholder="t('settings.email.bodyPlaceholder')"
    />
    <SegmentPicker
      v-model="compose.audience"
      :segments="segments"
      :recipients="recipients"
      :recipients-loading="recipientsLoading"
      :error="errors.segment || errors.recipient"
      @search-recipients="searchRecipients"
    />
    <div class="settings-form__inline">
      <BaseButton
        variant="primary"
        icon="envelope"
        :loading="sending"
        :disabled="!valid"
        @click="send"
      >
        {{ t('settings.email.send') }}
      </BaseButton>
    </div>
  </section>
</template>
