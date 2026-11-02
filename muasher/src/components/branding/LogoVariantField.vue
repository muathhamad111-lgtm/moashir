<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { LOGO_RULES, LOGO_SURFACES } from '@/constants/branding'
import BaseFileUpload from '@/components/form/BaseFileUpload.vue'
import Badge from '@/components/ui/Badge.vue'

const props = defineProps({
  surface: { type: String, required: true, validator: (value) => LOGO_SURFACES.includes(value) },
  previewUrl: { type: String, required: true },
  fileName: { type: String, default: '' },
  errorKey: { type: String, default: '' },
  custom: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'remove'])

const { t } = useI18n()

const files = computed(() =>
  props.fileName ? [{ id: props.surface, name: props.fileName, status: 'done' }] : [],
)
const error = computed(() => (props.errorKey ? t(props.errorKey) : ''))
const pending = computed(() => Boolean(props.fileName))
</script>

<template>
  <div class="logo-variant" :data-surface="surface">
    <div class="logo-variant__tile">
      <img
        class="logo-variant__image"
        :src="previewUrl"
        :alt="t(`branding.logo.surfaces.${surface}.preview`)"
      />
    </div>
    <div class="logo-variant__body">
      <p class="logo-variant__state">
        <Badge :variant="custom || pending ? 'info' : 'muted'">
          {{ custom || pending ? t('branding.logo.custom') : t('branding.logo.brandDefault') }}
        </Badge>
      </p>
      <BaseFileUpload
        :files="files"
        :label="t(`branding.logo.surfaces.${surface}.label`)"
        :name="`logo_${surface}_background`"
        :accept="LOGO_RULES.accept"
        :hint="t(`branding.logo.surfaces.${surface}.hint`)"
        :error="error"
        :disabled="disabled"
        @select="(selected) => emit('select', selected[0])"
        @remove="emit('remove')"
      />
    </div>
  </div>
</template>
