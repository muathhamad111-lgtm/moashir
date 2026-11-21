<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { LOGO_DIMENSIONS, LOGO_RULES, LOGO_SURFACES } from '@/constants/branding'
import BaseFileUpload from '@/components/form/BaseFileUpload.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  surface: { type: String, required: true, validator: (value) => LOGO_SURFACES.includes(value) },
  previewUrl: { type: String, required: true },
  fileName: { type: String, default: '' },
  errorKey: { type: String, default: '' },
  mismatch: { type: Object, default: null },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'remove'])

const { t } = useI18n()

const expected = computed(() => LOGO_DIMENSIONS[props.surface])
const files = computed(() =>
  props.fileName ? [{ id: props.surface, name: props.fileName, status: 'done' }] : [],
)
const error = computed(() => (props.errorKey ? t(props.errorKey) : ''))
const spec = computed(() =>
  t('branding.logo.spec', { ...expected.value, megabytes: LOGO_RULES.maxMegabytes }),
)
</script>

<template>
  <div class="logo-variant" :data-surface="surface">
    <div class="logo-variant__tile">
      <img
        class="logo-variant__image"
        :src="previewUrl"
        :alt="t(`branding.logo.surfaces.${surface}.label`)"
      />
    </div>
    <div class="logo-variant__body">
      <BaseFileUpload
        :files="files"
        :label="t(`branding.logo.surfaces.${surface}.label`)"
        :name="`logo_${surface}`"
        :accept="LOGO_RULES.accept"
        :hint="spec"
        :error="error"
        :disabled="disabled"
        @select="(selected) => emit('select', selected[0])"
        @remove="emit('remove')"
      />
      <p v-if="mismatch" class="logo-variant__warning" role="status">
        <AppIcon name="warning-triangle" />
        <span>{{
          t('branding.logo.dimensionsMismatch', {
            ...mismatch,
            expectedWidth: expected.width,
            expectedHeight: expected.height,
          })
        }}</span>
      </p>
    </div>
  </div>
</template>
