<script setup>
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseFileUpload from '@/components/form/BaseFileUpload.vue'
import BaseInput from '@/components/form/BaseInput.vue'
import BaseSelect from '@/components/form/BaseSelect.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useSettings } from '@/composables/useSettings'

const props = defineProps({
  config: { type: Object, required: true },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['save'])
const { t } = useI18n()
const { certificateFontOptions } = useSettings()

const form = reactive({
  entityNamePosition: { x: 50, y: 42 },
  scorePosition: { x: 50, y: 58 },
  fontColor: '#022426',
  fontType: 'iwanzaza',
  fontSize: 28,
  issuanceEnabled: true,
})

watch(
  () => props.config,
  (value) => {
    if (!value) return
    form.entityNamePosition = { ...value.entityNamePosition }
    form.scorePosition = { ...value.scorePosition }
    form.fontColor = value.fontColor
    form.fontType = value.fontType
    form.fontSize = value.fontSize
    form.issuanceEnabled = Boolean(value.issuanceEnabled)
  },
  { immediate: true },
)

const hasTemplate = computed(() => Boolean(props.config?.templateFile))

const namePreviewVars = computed(() => ({
  '--marker-x': `${form.entityNamePosition.x}%`,
  '--marker-y': `${form.entityNamePosition.y}%`,
  '--marker-color': form.fontColor,
}))

const scorePreviewVars = computed(() => ({
  '--marker-x': `${form.scorePosition.x}%`,
  '--marker-y': `${form.scorePosition.y}%`,
  '--marker-color': form.fontColor,
}))

function onSave() {
  emit('save', {
    entityNamePosition: form.entityNamePosition,
    scorePosition: form.scorePosition,
    fontColor: form.fontColor,
    fontType: form.fontType,
    fontSize: Number(form.fontSize) || 0,
    issuanceEnabled: form.issuanceEnabled,
  })
}
</script>

<template>
  <div class="certificate-editor">
    <div class="certificate-editor__form">
      <BaseFileUpload
        name="template"
        :label="t('settings.certificates.template')"
        :hint="hasTemplate ? config.templateFile.name : t('settings.certificates.templateHint')"
        accept="application/pdf,image/*"
      />

      <fieldset class="certificate-editor__positions">
        <legend class="certificate-editor__legend">
          {{ t('settings.certificates.positions') }}
        </legend>
        <div class="certificate-editor__grid">
          <BaseInput
            :model-value="form.entityNamePosition.x"
            name="nameX"
            type="number"
            :label="t('settings.certificates.nameX')"
            @update:model-value="form.entityNamePosition.x = Number($event) || 0"
          />
          <BaseInput
            :model-value="form.entityNamePosition.y"
            name="nameY"
            type="number"
            :label="t('settings.certificates.nameY')"
            @update:model-value="form.entityNamePosition.y = Number($event) || 0"
          />
          <BaseInput
            :model-value="form.scorePosition.x"
            name="scoreX"
            type="number"
            :label="t('settings.certificates.scoreX')"
            @update:model-value="form.scorePosition.x = Number($event) || 0"
          />
          <BaseInput
            :model-value="form.scorePosition.y"
            name="scoreY"
            type="number"
            :label="t('settings.certificates.scoreY')"
            @update:model-value="form.scorePosition.y = Number($event) || 0"
          />
        </div>
      </fieldset>

      <fieldset class="certificate-editor__font">
        <legend class="certificate-editor__legend">{{ t('settings.certificates.font') }}</legend>
        <div class="certificate-editor__grid">
          <BaseSelect
            v-model="form.fontType"
            name="fontType"
            :label="t('settings.certificates.fontType')"
            :options="certificateFontOptions"
          />
          <BaseInput
            :model-value="form.fontSize"
            name="fontSize"
            type="number"
            :label="t('settings.certificates.fontSize')"
            @update:model-value="form.fontSize = Number($event) || 0"
          />
          <BaseInput
            v-model="form.fontColor"
            name="fontColor"
            :label="t('settings.certificates.fontColor')"
            dir="ltr"
          />
        </div>
      </fieldset>

      <ToggleSwitch
        v-model="form.issuanceEnabled"
        name="issuanceEnabled"
        :label="t('settings.certificates.issuance')"
        :description="t('settings.certificates.issuanceDesc')"
      />

      <div class="certificate-editor__actions">
        <BaseButton
          variant="primary"
          :loading="saving"
          icon="fa-solid fa-floppy-disk"
          @click="onSave"
        >
          {{ t('actions.save') }}
        </BaseButton>
      </div>
    </div>

    <div class="certificate-editor__preview" aria-hidden="true">
      <span class="certificate-editor__preview-label">{{
        t('settings.certificates.preview')
      }}</span>
      <div class="certificate-editor__canvas">
        <span class="certificate-editor__marker" :style="namePreviewVars">
          {{ t('settings.certificates.sampleName') }}
        </span>
        <span class="certificate-editor__marker" :style="scorePreviewVars">
          {{ t('settings.certificates.sampleScore') }}
        </span>
      </div>
    </div>
  </div>
</template>
