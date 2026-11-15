<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { failureText } from '@/utils/auth/authFailures'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  failure: { type: Object, required: true },
  group: { type: String, required: true },
})

// A registration under review is news, not an error: it reads as a calm notice.
const NOTICE_KINDS = ['pendingReview']

const { t } = useI18n()
const text = computed(() => failureText(t, props.group, props.failure))
const isNotice = computed(() => NOTICE_KINDS.includes(props.failure.kind))
</script>

<template>
  <div
    class="auth-status auth-failure"
    :class="isNotice ? 'auth-status--success' : 'auth-status--danger'"
    :data-failure="failure.kind"
    :role="isNotice ? 'status' : 'alert'"
  >
    <AppIcon :name="isNotice ? 'check-circle' : 'warning-triangle'" class="auth-status__icon" />
    <p class="auth-status__text">{{ text }}</p>
  </div>
</template>
