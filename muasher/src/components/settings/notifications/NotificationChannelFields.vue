<script setup>
import { useI18n } from 'vue-i18n'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import NotificationFormatBar from './NotificationFormatBar.vue'
import NotificationSmsCounter from './NotificationSmsCounter.vue'

defineProps({
  parts: { type: Array, required: true },
  form: { type: Object, required: true },
  text: { type: Object, required: true },
  locale: { type: String, required: true },
})

const { t, te } = useI18n()

const MARKDOWN_PART = 'emailBody'
const LONG_PARTS = ['body', 'line', 'emailBody', 'adminBody', 'sms']
const MARKS = { bold: '**…**', link: '[…](https://)', list: '\n- ' }

function labelOf(part) {
  const key = `settings.notifications.texts.parts.${part}`
  return te(key) ? t(key) : part
}
</script>

<template>
  <div class="notification-channel-fields">
    <div v-for="part in parts" :key="part" class="notification-channel-fields__part">
      <template v-if="text.parts[part]?.isFixed">
        <p class="notification-channel-fields__fixed-label">{{ labelOf(part) }}</p>
        <p class="notification-channel-fields__fixed">
          <AppIcon name="lock" size="sm" />
          <span>{{ text.parts[part].current[locale] }}</span>
        </p>
      </template>
      <template v-else>
        <NotificationFormatBar
          v-if="part === MARKDOWN_PART"
          :disabled="form.busy.value"
          @format="form.setPart(part, locale, form.draft.value[part][locale] + MARKS[$event])"
        />
        <div @focusin="form.remember(part, locale, $event)">
          <BaseTextarea
            :model-value="form.draft.value[part][locale]"
            :name="`${text.key}-${part}-${locale}`"
            :label="labelOf(part)"
            :rows="LONG_PARTS.includes(part) ? 5 : 2"
            :error="form.errorOf(part, locale)"
            :input-dir="locale === 'ar' ? 'rtl' : 'ltr'"
            :disabled="form.busy.value"
            @update:model-value="form.setPart(part, locale, $event)"
          />
        </div>
        <NotificationSmsCounter v-if="part === 'sms'" :text="form.draft.value[part][locale]" />
      </template>
    </div>
  </div>
</template>
