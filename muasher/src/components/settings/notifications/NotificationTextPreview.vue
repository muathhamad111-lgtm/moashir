<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import NotificationEmailPreview from './NotificationEmailPreview.vue'
import NotificationSmsPreview from './NotificationSmsPreview.vue'
import NotificationInAppPreview from './NotificationInAppPreview.vue'
import { emailPreviewParts } from '@/utils/notificationTextParts'
import { fillSamples } from '@/utils/notificationSamples'

const props = defineProps({
  channel: { type: String, required: true },
  draft: { type: Object, required: true },
  text: { type: Object, required: true },
  locale: { type: String, required: true },
})

const { t, te } = useI18n()

function labelOf(name) {
  const key = `settings.notifications.texts.variableNames.${name}`
  return te(key) ? t(key) : name
}

function sourceOf(part) {
  if (!part) return ''
  const source = props.draft[part] ?? props.text.parts[part]?.current
  return source?.[props.locale] ?? ''
}

function fill(text) {
  return fillSamples(text, props.locale, labelOf)
}

function valueOf(part) {
  return fill(sourceOf(part))
}

const email = computed(() => emailPreviewParts(props.text))
const inAppTitle = computed(() => valueOf(props.text.parts.adminTitle ? 'adminTitle' : 'title'))
const inAppBody = computed(() => valueOf(props.text.parts.adminBody ? 'adminBody' : 'body'))
</script>

<template>
  <section
    class="notification-text__preview"
    :aria-label="t('settings.notifications.texts.preview.title')"
  >
    <h3 class="notification-text__preview-title">
      {{ t('settings.notifications.texts.preview.title') }}
    </h3>
    <NotificationEmailPreview
      v-if="channel === 'email'"
      :subject="valueOf(email.subject)"
      :body="[sourceOf(email.body), ...email.extras.map(sourceOf)].join('\n')"
      :fill="fill"
      :action="valueOf(email.action)"
      :locale="locale"
    />
    <NotificationSmsPreview v-else-if="channel === 'sms'" :text="valueOf('sms')" :locale="locale" />
    <NotificationInAppPreview v-else :title="inAppTitle" :body="inAppBody" :locale="locale" />
  </section>
</template>
