<script setup>
import { useI18n } from 'vue-i18n'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const props = defineProps({
  modelValue: { type: String, required: true },
  sending: { type: Boolean, default: false },
  error: { type: String, default: '' },
  compact: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'send'])
const { t } = useI18n()
</script>

<template>
  <form
    class="message-reply"
    :class="{ 'message-reply--compact': props.compact }"
    novalidate
    @submit.prevent="emit('send')"
  >
    <BaseTextarea
      :model-value="modelValue"
      :name="compact ? 'reply-compact' : 'reply'"
      :label="t('portal.messages.reply.label')"
      :rows="compact ? 1 : 4"
      :hint="compact ? '' : t('portal.messages.reply.hint')"
      :error="error"
      :disabled="sending"
      @update:model-value="emit('update:modelValue', $event)"
    />
    <div class="message-reply__footer">
      <p v-if="!compact" class="message-reply__caption">{{ t('portal.messages.reply.caption') }}</p>
      <BaseButton type="submit" variant="primary" icon="send" :loading="sending">
        {{ compact ? t('portal.messages.reply.sendShort') : t('portal.messages.reply.send') }}
      </BaseButton>
    </div>
  </form>
</template>
