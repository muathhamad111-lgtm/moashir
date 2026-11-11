<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ENTITY_LOGO_RULES } from '@/constants/entityProfile'

defineProps({
  modelValue: { type: Boolean, required: true },
  logo: { type: Object, default: null },
  busy: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'upload', 'remove'])
const { t } = useI18n()
const input = ref(null)

function choose() {
  input.value?.click()
}

function onChange(event) {
  const [file] = event.target.files ?? []
  event.target.value = ''
  if (file) emit('upload', file)
}
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    :title="t('portal.profile.logo.title')"
    size="sm"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="logo-dialog" data-testid="logo-dialog">
      <div class="logo-dialog__preview">
        <img
          v-if="logo?.url"
          class="logo-dialog__image"
          :src="logo.url"
          :alt="t('portal.profile.logo.alt')"
        />
        <AppIcon v-else name="image" class="logo-dialog__placeholder" />
      </div>
      <p class="logo-dialog__rules">
        {{
          t('portal.profile.logo.rules', {
            min: ENTITY_LOGO_RULES.minSide,
            max: ENTITY_LOGO_RULES.maxSide,
          })
        }}
      </p>
      <p class="logo-dialog__note">{{ t('portal.profile.logo.note') }}</p>
      <p v-if="error" class="logo-dialog__error" role="alert">{{ error }}</p>
      <input
        ref="input"
        class="logo-dialog__input"
        type="file"
        :accept="ENTITY_LOGO_RULES.accept"
        :aria-label="t('portal.profile.logo.choose')"
        tabindex="-1"
        @change="onChange"
      />
    </div>
    <template #footer>
      <BaseButton variant="primary" icon="upload" :loading="busy" @click="choose">
        {{ logo ? t('portal.profile.logo.replace') : t('portal.profile.logo.upload') }}
      </BaseButton>
      <BaseButton v-if="logo" variant="ghost" icon="trash" :disabled="busy" @click="emit('remove')">
        {{ t('portal.profile.logo.remove') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
