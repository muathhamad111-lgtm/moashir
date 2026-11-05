<script setup>
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import TemplateEditor from '@/components/settings/TemplateEditor.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useSettings } from '@/composables/useSettings'

const props = defineProps({
  type: { type: Object, required: true },
  placeholders: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['save'])
const { t } = useI18n()
const { localized } = useSettings()

const expanded = ref(false)
const form = reactive({
  emailEnabled: props.type.emailEnabled,
  smsEnabled: props.type.smsEnabled,
  template: { ...props.type.template },
})

watch(
  () => props.type,
  (value) => {
    form.emailEnabled = value.emailEnabled
    form.smsEnabled = value.smsEnabled
    form.template = { ...value.template }
  },
)

function onSave() {
  emit('save', {
    code: props.type.code,
    payload: {
      emailEnabled: form.emailEnabled,
      smsEnabled: form.smsEnabled,
      template: form.template,
    },
  })
}
</script>

<template>
  <Card class="notification-type">
    <template #header>
      <button
        type="button"
        class="notification-type__head"
        :aria-expanded="expanded"
        @click="expanded = !expanded"
      >
        <span class="notification-type__title-wrap">
          <Badge variant="muted">{{ type.code }}</Badge>
          <span class="notification-type__label">{{ localized(type.label) }}</span>
        </span>
        <span class="notification-type__channels">
          <Badge :variant="form.emailEnabled ? 'info' : 'muted'">
            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
            {{ t('settings.notifications.email') }}
          </Badge>
          <Badge :variant="form.smsEnabled ? 'info' : 'muted'">
            <i class="fa-solid fa-comment-sms" aria-hidden="true"></i>
            {{ t('settings.notifications.sms') }}
          </Badge>
          <i
            class="fa-solid fa-chevron-down notification-type__chevron"
            :class="{ 'is-open': expanded }"
            aria-hidden="true"
          ></i>
        </span>
      </button>
    </template>

    <div v-if="expanded" class="notification-type__body">
      <div class="notification-type__toggles">
        <ToggleSwitch
          v-model="form.emailEnabled"
          :name="`${type.code}-email`"
          :label="t('settings.notifications.emailChannel')"
        />
        <ToggleSwitch
          v-model="form.smsEnabled"
          :name="`${type.code}-sms`"
          :label="t('settings.notifications.smsChannel')"
        />
      </div>
      <TemplateEditor
        v-model="form.template"
        :name="type.code"
        :label="t('settings.notifications.templateLabel')"
        :placeholders="placeholders"
        :rows="3"
      />
      <div class="notification-type__actions">
        <BaseButton
          variant="primary"
          size="sm"
          :loading="saving"
          icon="fa-solid fa-floppy-disk"
          @click="onSave"
        >
          {{ t('actions.save') }}
        </BaseButton>
      </div>
    </div>
  </Card>
</template>
