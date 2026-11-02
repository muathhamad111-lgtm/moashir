<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useToast } from '@/composables/useToast'
import { messagingService } from '@/services/messagingService'
import DemoDataNotice from '@/components/ui/DemoDataNotice.vue'
import { MOCK_MODULES } from '@/constants/mockModules'

const { t } = useI18n()
const toast = useToast()
const sending = ref(false)

async function sendBroadcast() {
  if (sending.value) return
  sending.value = true
  try {
    await messagingService.sendSms({ segment: 'all' })
    toast.success(t('settings.sms.broadcastQueued'))
  } catch (error) {
    toast.error(error?.normalized?.message || t('settings.sms.broadcastFailed'))
  } finally {
    sending.value = false
  }
}

const DEMO_MODULES = [MOCK_MODULES.MESSAGING]
</script>

<template>
  <section class="settings-form__group" aria-labelledby="sms-broadcast-title">
    <h2 id="sms-broadcast-title" class="settings-form__legend">{{ t('settings.sms.broadcastTitle') }}</h2>
    <DemoDataNotice scope="section" :modules="DEMO_MODULES" />
    <p class="settings-form__note" role="note">
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      {{ t('settings.sms.broadcastNote') }}
    </p>
    <div class="settings-form__inline">
      <BaseButton variant="secondary" icon="fa-solid fa-tower-broadcast" :loading="sending" @click="sendBroadcast">
        {{ t('settings.sms.broadcastSend') }}
      </BaseButton>
    </div>
  </section>
</template>
