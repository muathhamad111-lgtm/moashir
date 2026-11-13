<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { PROOF_RULES, PROOF_STATES, REFUSED_PROOF_STATES } from '@/constants/bankTransfer'
import { formatFileSize } from '@/utils/payments/amountFormat'

const props = defineProps({
  file: { type: Object, default: null },
  state: { type: String, required: true },
  progress: { type: Number, default: 0 },
  disabled: { type: Boolean, default: false },
  labelledby: { type: String, required: true },
})

const emit = defineEmits(['select', 'retry'])
const { t, locale } = useI18n()
const inputRef = ref(null)
const dragging = ref(false)
const hintId = useId()

const STATE_BADGES = {
  [PROOF_STATES.READY]: 'success',
  [PROOF_STATES.ACCEPTED]: 'success',
  [PROOF_STATES.SCANNING]: 'info',
  [PROOF_STATES.WRONG_TYPE]: 'danger',
  [PROOF_STATES.TOO_LARGE]: 'danger',
  [PROOF_STATES.UNSAFE]: 'danger',
  [PROOF_STATES.FAILED]: 'danger',
}

const isRefused = computed(() => REFUSED_PROOF_STATES.includes(props.state))
const isUploading = computed(() => props.state === PROOF_STATES.UPLOADING)
const busy = computed(() => [PROOF_STATES.UPLOADING, PROOF_STATES.SCANNING].includes(props.state))
const badge = computed(() => STATE_BADGES[props.state] ?? null)
const sizeLabel = computed(() =>
  props.file
    ? t('finance.common.sizeKb', { size: formatFileSize(props.file.size, locale.value) })
    : '',
)

function openPicker() {
  if (!props.disabled) inputRef.value?.click()
}

function onChange(event) {
  const [file] = Array.from(event.target.files ?? [])
  if (file) emit('select', file)
  event.target.value = ''
}

function onDrop(event) {
  dragging.value = false
  if (props.disabled) return
  const [file] = Array.from(event.dataTransfer?.files ?? [])
  if (file) emit('select', file)
}
</script>

<template>
  <div class="proof-upload">
    <input
      ref="inputRef"
      type="file"
      name="proof"
      class="proof-upload__input"
      :accept="PROOF_RULES.ACCEPT"
      :disabled="disabled"
      hidden
      @change="onChange"
    />
    <button
      v-if="!file"
      type="button"
      class="proof-upload__dropzone"
      :class="{ 'is-dragging': dragging }"
      :disabled="disabled"
      :aria-labelledby="labelledby"
      :aria-describedby="hintId"
      @click="openPicker"
      @dragenter.prevent="dragging = true"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <span class="proof-upload__icon" aria-hidden="true"><AppIcon name="cloud-upload" /></span>
      <span class="proof-upload__prompt">
        {{ dragging ? t('portal.transfer.proof.drop') : t('portal.transfer.proof.prompt') }}
      </span>
      <span :id="hintId" class="proof-upload__hint">{{
        t('portal.transfer.proof.hint', { max: PROOF_RULES.MAX_MB })
      }}</span>
    </button>

    <div
      v-else
      class="proof-chip"
      :class="{ 'is-refused': isRefused }"
      :aria-busy="busy || undefined"
    >
      <span class="proof-chip__icon" aria-hidden="true"><AppIcon name="doc" /></span>
      <span class="proof-chip__meta">
        <bdi class="proof-chip__name" dir="ltr">{{ file.name }}</bdi>
        <span v-if="isUploading" class="proof-chip__progress">
          <progress
            class="proof-chip__bar"
            :value="progress"
            max="100"
            :aria-label="t('portal.transfer.proof.progress', { name: file.name })"
          ></progress>
          <bdi dir="ltr">{{ progress }}%</bdi>
        </span>
        <span v-else class="proof-chip__detail">{{
          t(`portal.transfer.proof.details.${state}`, { size: sizeLabel, max: PROOF_RULES.MAX_MB })
        }}</span>
      </span>
      <Badge v-if="badge" :variant="badge">
        <AppIcon v-if="state === PROOF_STATES.SCANNING" name="refresh-dots" />
        {{ t(`portal.transfer.proof.states.${state}`) }}
      </Badge>
      <BaseButton
        v-if="!busy"
        variant="ghost"
        size="sm"
        icon-only
        icon="x"
        :disabled="disabled"
        :aria-label="t('portal.transfer.proof.remove', { name: file.name })"
        @click="emit('select', null)"
      />
    </div>

    <div v-if="state === PROOF_STATES.FAILED" class="proof-upload__retry">
      <BaseButton variant="secondary" icon="refresh" :disabled="disabled" @click="emit('retry')">
        {{ t('portal.transfer.proof.retry') }}
      </BaseButton>
    </div>
    <p class="proof-upload__status" aria-live="polite">
      {{ file ? t(`portal.transfer.proof.states.${state}`) : '' }}
    </p>
  </div>
</template>
