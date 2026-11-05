<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  attachments: { type: Array, required: true },
  files: { type: Array, default: () => [] },
  uploading: { type: Object, default: () => ({}) },
  accept: { type: String, default: '.pdf,.doc,.docx,.png,.jpg,.jpeg' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['upload', 'remove'])

const { t } = useI18n()
const groupId = useId()
const inputs = ref({})

function filesForKey(key) {
  return props.files.filter((file) => file.attachmentKey === key)
}

function isSatisfied(att) {
  if (!att.required) return true
  return filesForKey(att.key).length > 0
}

const requiredMissingCount = computed(
  () => props.attachments.filter((att) => att.required && !isSatisfied(att)).length,
)

function openPicker(key) {
  if (props.disabled || props.uploading[key]) return
  inputs.value[key]?.click()
}

function onChange(key, event) {
  const selected = Array.from(event.target.files ?? [])
  selected.forEach((file) => emit('upload', { attachmentKey: key, file }))
  event.target.value = ''
}

function sizeLabel(file) {
  if (!file.sizeKb) return ''
  if (file.sizeKb >= 1024) return t('evidence.sizeMb', { size: (file.sizeKb / 1024).toFixed(1) })
  return t('evidence.sizeKb', { size: file.sizeKb })
}
</script>

<template>
  <fieldset class="evidence-uploader" :aria-describedby="`${groupId}-summary`">
    <legend class="evidence-uploader__legend">{{ t('evidence.title') }}</legend>

    <p :id="`${groupId}-summary`" class="evidence-uploader__summary" role="status">
      <template v-if="requiredMissingCount > 0">
        <AppIcon name="warning-triangle" />
        {{ t('evidence.requiredMissing', { count: requiredMissingCount }) }}
      </template>
      <template v-else>
        <AppIcon name="check-circle" />
        {{ t('evidence.requiredComplete') }}
      </template>
    </p>

    <ul class="evidence-uploader__slots">
      <li
        v-for="att in attachments"
        :key="att.key"
        class="evidence-uploader__slot"
        :class="{ 'is-missing': att.required && !isSatisfied(att) }"
      >
        <div class="evidence-uploader__slot-head">
          <span class="evidence-uploader__slot-name">
            {{ att.name }}
            <span
              class="evidence-uploader__req"
              :class="
                att.required
                  ? 'evidence-uploader__req--required'
                  : 'evidence-uploader__req--optional'
              "
            >
              {{ att.required ? t('evidence.required') : t('evidence.optional') }}
            </span>
          </span>
        </div>

        <input
          :ref="(el) => (inputs[att.key] = el)"
          type="file"
          :accept="accept"
          hidden
          :aria-label="t('evidence.chooseFor', { name: att.name })"
          @change="onChange(att.key, $event)"
        />

        <ul v-if="filesForKey(att.key).length" class="evidence-uploader__files">
          <li v-for="file in filesForKey(att.key)" :key="file.id" class="evidence-uploader__file">
            <AppIcon name="doc" class="evidence-uploader__file-icon" />
            <span class="evidence-uploader__file-name">{{ file.fileName }}</span>
            <span v-if="sizeLabel(file)" class="evidence-uploader__file-size">{{
              sizeLabel(file)
            }}</span>
            <BaseButton
              variant="ghost"
              size="sm"
              icon-only
              icon="trash"
              :disabled="disabled"
              :aria-label="t('evidence.remove', { name: file.fileName })"
              @click="emit('remove', file.id)"
            />
          </li>
        </ul>

        <button
          type="button"
          class="evidence-uploader__add"
          :disabled="disabled || uploading[att.key]"
          @click="openPicker(att.key)"
        >
          <AppIcon :name="uploading[att.key] ? 'spinner' : 'cloud-upload'" />
          <span>{{ uploading[att.key] ? t('evidence.uploading') : t('evidence.add') }}</span>
        </button>
      </li>
    </ul>
  </fieldset>
</template>
