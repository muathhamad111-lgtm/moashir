<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { failureText } from '@/utils/auth/authFailures'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  failure: { type: Object, required: true },
  group: { type: String, required: true },
})

const { t } = useI18n()
const text = computed(() => failureText(t, props.group, props.failure))
</script>

<template>
  <div
    class="auth-status auth-status--danger auth-failure"
    :data-failure="failure.kind"
    role="alert"
  >
    <AppIcon name="warning-triangle" class="auth-status__icon" />
    <p class="auth-status__text">{{ text }}</p>
  </div>
</template>
