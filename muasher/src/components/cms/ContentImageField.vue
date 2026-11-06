<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseFileUpload from '@/components/form/BaseFileUpload.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { CMS_IMAGE_RULES } from '@/constants/cms'
import { useImageUpload } from '@/composables/useImageUpload'
import { contentImageUrl } from '@/utils/contentImage'

const props = defineProps({
  modelValue: { type: Object, default: null },
  upload: { type: Function, required: true },
  label: { type: String, required: true },
  name: { type: String, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const { uploading, error, send } = useImageUpload(props.upload)

const preview = computed(() => contentImageUrl(props.modelValue))
const files = computed(() =>
  uploading.value ? [{ id: props.name, name: t('cms.image.uploading'), status: 'uploading' }] : [],
)

async function onSelect(selected) {
  const image = await send(selected[0])
  if (image) emit('update:modelValue', image)
}
</script>

<template>
  <div class="content-image-field">
    <div v-if="preview" class="content-image-field__preview">
      <img
        class="content-image-field__image"
        :src="preview"
        :alt="t('cms.image.previewAlt', { label })"
      />
      <BaseButton
        variant="ghost"
        size="sm"
        icon="trash"
        :disabled="disabled || uploading"
        @click="emit('update:modelValue', null)"
      >
        {{ t('cms.image.remove') }}
      </BaseButton>
    </div>
    <BaseFileUpload
      :files="files"
      :label="label"
      :name="name"
      :accept="CMS_IMAGE_RULES.accept"
      :hint="t('cms.image.hint')"
      :error="error"
      :disabled="disabled || uploading"
      @select="onSelect"
    />
  </div>
</template>
