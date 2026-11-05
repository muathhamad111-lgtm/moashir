<script setup>
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { useNotificationPreferences } from '@/composables/useNotificationPreferences'
import { toast } from '@/composables/useToast'
import NotificationPreferenceRow from './NotificationPreferenceRow.vue'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const { groups, status, saving, errorMessage, changes, load, isEnabled, setEnabled, save } =
  useNotificationPreferences()

watch(
  () => props.modelValue,
  (open) => {
    if (open) load()
  },
  { immediate: true },
)

function close() {
  emit('update:modelValue', false)
}

async function submit() {
  if (!(await save())) return
  toast.success(t('notifications.preferences.saved'))
  close()
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="lg"
    :title="t('notifications.preferences.title')"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="notification-preferences">
      <p class="notification-preferences__intro">{{ t('notifications.preferences.intro') }}</p>
      <Skeleton v-if="status === 'loading'" variant="text" :count="6" />
      <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
      <p v-else-if="status === 'empty'" class="notification-preferences__empty">
        {{ t('notifications.preferences.empty') }}
      </p>
      <template v-else>
        <section
          v-for="group in groups"
          :key="group.module"
          class="notification-preferences__group"
          :aria-labelledby="`preferences-module-${group.module}`"
        >
          <h3 :id="`preferences-module-${group.module}`" class="notification-preferences__module">
            {{ t(`notifications.modules.${group.module}`) }}
          </h3>
          <ul class="notification-preferences__rows">
            <NotificationPreferenceRow
              v-for="row in group.rows"
              :key="row.code"
              :row="row"
              :is-enabled="isEnabled"
              @toggle="setEnabled"
            />
          </ul>
        </section>
        <p v-if="errorMessage" class="notification-preferences__error" role="alert">
          {{ errorMessage }}
        </p>
      </template>
    </div>
    <template #footer>
      <BaseButton variant="ghost" @click="close">{{ t('actions.cancel') }}</BaseButton>
      <BaseButton :loading="saving" :disabled="!changes.length" @click="submit">
        {{ t('notifications.preferences.save') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
