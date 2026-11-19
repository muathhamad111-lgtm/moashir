<script setup>
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Badge from '@/components/ui/Badge.vue'
import NotificationVariableChips from './NotificationVariableChips.vue'
import NotificationSmsApproval from './NotificationSmsApproval.vue'
import { useNotificationTextForm } from '@/composables/useNotificationTextForm'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  text: { type: Object, required: true },
  smsEnabled: { type: Boolean, default: false },
})

const emit = defineEmits(['saved', 'reset'])

const { t, te } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const form = useNotificationTextForm(toRef(props, 'text'), toRef(props, 'smsEnabled'), {
  onSaved: (data) => emit('saved', data),
  onReset: () => emit('reset'),
})

function partLabel(part) {
  const key = `settings.notifications.texts.parts.${part}`
  return te(key) ? t(key) : part
}

async function save() {
  try {
    await form.save()
    toast.success(t('settings.notifications.texts.saved'))
  } catch (error) {
    if (!error?.normalized?.fieldErrors) toast.error(t('settings.notifications.texts.saveFailed'))
  }
}

async function reset() {
  const accepted = await confirm({
    title: t('settings.notifications.texts.resetTitle'),
    message: t('settings.notifications.texts.resetMessage'),
    confirmLabel: t('settings.notifications.texts.reset'),
  })
  if (!accepted) return
  try {
    await form.reset()
    toast.success(t('settings.notifications.texts.resetDone'))
  } catch {
    toast.error(t('settings.notifications.texts.saveFailed'))
  }
}
</script>

<template>
  <form class="notification-text" novalidate @submit.prevent="save">
    <div class="notification-text__status">
      <Badge v-if="text.isCustomized" variant="info">
        {{ t('settings.notifications.texts.customized') }}
      </Badge>
      <Badge v-else variant="muted">{{ t('settings.notifications.texts.shipped') }}</Badge>
    </div>
    <fieldset
      v-for="(value, part) in form.draft.value"
      :key="part"
      class="notification-text__part"
      :disabled="form.busy.value"
    >
      <legend class="notification-text__legend">{{ partLabel(part) }}</legend>
      <div class="notification-text__fields">
        <div @focusin="form.remember(part, 'ar', $event)">
          <BaseTextarea
            v-model="value.ar"
            :name="`${text.key}-${part}-ar`"
            :label="t('settings.notifications.texts.arabic')"
            :rows="part === 'body' || part === 'line' ? 4 : 2"
            :error="form.errorOf(part, 'ar')"
            input-dir="rtl"
            required
          />
        </div>
        <div @focusin="form.remember(part, 'en', $event)">
          <BaseTextarea
            v-model="value.en"
            :name="`${text.key}-${part}-en`"
            :label="t('settings.notifications.texts.english')"
            :rows="part === 'body' || part === 'line' ? 4 : 2"
            :error="form.errorOf(part, 'en')"
            input-dir="ltr"
            required
          />
        </div>
      </div>
      <NotificationSmsApproval v-if="part === 'sms'" :approval="text.sms?.approval ?? null" />
    </fieldset>
    <NotificationVariableChips
      :variables="text.variables"
      :required="text.requiredVariables"
      :disabled="form.busy.value"
      @insert="form.insertVariable"
    />
    <div class="notification-text__actions">
      <BaseButton
        type="submit"
        variant="primary"
        :loading="form.busy.value"
        :disabled="!form.isDirty.value"
      >
        {{ t('settings.notifications.texts.save') }}
      </BaseButton>
      <BaseButton
        v-if="text.isCustomized"
        variant="ghost"
        icon="undo"
        :disabled="form.busy.value"
        @click="reset"
      >
        {{ t('settings.notifications.texts.reset') }}
      </BaseButton>
    </div>
  </form>
</template>
