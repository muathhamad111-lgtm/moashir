<script setup>
import { useI18n } from 'vue-i18n'
import ContactInboxStatusBadge from './ContactInboxStatusBadge.vue'
import ContactAssignControl from './ContactAssignControl.vue'

defineProps({
  message: { type: Object, required: true },
  assignees: { type: Array, default: () => [] },
  busy: { type: String, default: '' },
  isOpen: { type: Boolean, default: true },
  assignError: { type: String, default: '' },
})

const emit = defineEmits(['assign'])
const { t } = useI18n()
</script>

<template>
  <dl class="contact-controls">
    <div class="contact-controls__item">
      <dt>{{ t('contactInbox.split.status') }}</dt>
      <dd>
        <ContactInboxStatusBadge :status="message.inboxStatus" />
      </dd>
    </div>
    <div class="contact-controls__item">
      <dt>{{ t('contactInbox.split.type') }}</dt>
      <dd>{{ t(`public.home.contact.form.enquiryTypes.${message.enquiryType}`) }}</dd>
    </div>
    <div class="contact-controls__item contact-controls__item--assign">
      <dt>{{ t('contactInbox.assign.label') }}</dt>
      <dd>
        <ContactAssignControl
          :assignee="message.assignee"
          :assignees="assignees"
          :busy="busy === 'assign'"
          :disabled="!isOpen"
          :error="assignError"
          @assign="emit('assign', $event)"
        />
      </dd>
    </div>
  </dl>
</template>
