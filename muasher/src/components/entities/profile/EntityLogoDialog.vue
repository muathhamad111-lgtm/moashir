<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { ENTITY_LOGO_RULES } from '@/constants/entityProfile'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  logo: { type: Object, default: null },
  pendingChange: { type: Object, default: null },
  busy: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'upload', 'remove'])
const { t } = useI18n()
const input = ref(null)
const pendingLogo = computed(() => props.pendingChange?.newValue ?? null)
const pendingRemoval = computed(() => props.pendingChange?.removes === true)
const shown = computed(() => pendingLogo.value ?? props.logo)

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
          v-if="shown?.url"
          class="logo-dialog__image"
          :src="shown.url"
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
      <p v-if="pendingLogo || pendingRemoval" class="logo-dialog__pending" role="status">
        {{
          t(pendingRemoval ? 'portal.profile.logo.pendingRemoval' : 'portal.profile.logo.pending')
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
        {{ shown ? t('portal.profile.logo.replace') : t('portal.profile.logo.upload') }}
      </BaseButton>
      <BaseButton
        v-if="shown && !pendingRemoval"
        variant="ghost"
        icon="trash"
        :disabled="busy"
        @click="emit('remove')"
      >
        {{ t('portal.profile.logo.remove') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
