<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

// The certificate card's header actions (v4.1): download first, reissue next, revoke last; or issue, when the
// completed request has no certificate yet. Which of them shows is decided by the card from the permissions.
defineProps({
  canDownload: { type: Boolean, default: false },
  canReissue: { type: Boolean, default: false },
  canRevoke: { type: Boolean, default: false },
  canIssue: { type: Boolean, default: false },
  downloading: { type: Boolean, default: false },
  issuing: { type: Boolean, default: false },
})

const emit = defineEmits(['download', 'reissue', 'revoke', 'issue'])
const { t } = useI18n()
</script>

<template>
  <BaseButton
    v-if="canDownload"
    variant="primary"
    size="sm"
    icon="download"
    :loading="downloading"
    @click="emit('download')"
    >{{ t('requests.certificate.download') }}</BaseButton
  >
  <BaseButton
    v-if="canReissue"
    variant="secondary"
    size="sm"
    icon="refresh"
    @click="emit('reissue')"
    >{{ t('requests.certificate.reissue') }}</BaseButton
  >
  <BaseButton v-if="canRevoke" variant="danger" size="sm" icon="ban" @click="emit('revoke')">{{
    t('requests.certificate.revoke')
  }}</BaseButton>
  <BaseButton
    v-if="canIssue"
    variant="primary"
    size="sm"
    icon="award"
    :loading="issuing"
    @click="emit('issue')"
    >{{ t('requests.certificate.issue') }}</BaseButton
  >
</template>
