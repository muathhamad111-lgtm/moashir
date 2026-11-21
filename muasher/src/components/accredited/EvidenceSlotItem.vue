<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import EvidenceFileItem from './EvidenceFileItem.vue'
import EvidenceUploadButton from './EvidenceUploadButton.vue'
import { slotKeyOf } from '@/composables/useEvidenceUploads'
import {
  acceptOf,
  countsTowardSlot,
  slotMaxFilesOf,
  slotTypesOf,
} from '@/utils/accredited/evidenceRules'
import { localizedText } from '@/utils/localizedText'

// One attachment of a criterion as the entity fills it; a null attachment is the sub's additional
// evidence.
const props = defineProps({
  attachment: { type: Object, default: null },
  owner: { type: Object, required: true },
  files: { type: Array, required: true },
  editable: { type: Boolean, default: false },
  ownerFull: { type: Boolean, default: false },
  limits: { type: Object, required: true },
  uploads: { type: Object, required: true },
})

const emit = defineEmits(['upload', 'remove', 'replace', 'download'])
const { t, locale } = useI18n()

const key = computed(() => slotKeyOf(props.owner, props.attachment?.key))
const title = computed(() =>
  props.attachment
    ? localizedText(props.attachment.name, locale.value)
    : t('accredited.evidence.general'),
)
const guidance = computed(() => localizedText(props.attachment?.guidance, locale.value))
const types = computed(() => slotTypesOf(props.attachment, props.limits))
const accept = computed(() => acceptOf(types.value))
const maxFiles = computed(() =>
  props.attachment ? slotMaxFilesOf(props.attachment, props.limits) : null,
)
const hasOwnRule = computed(() =>
  Boolean(props.attachment?.allowedTypes?.length || props.attachment?.maxFiles),
)
const isSlotFull = computed(() => maxFiles.value !== null && props.files.length >= maxFiles.value)
const isProvided = computed(() => props.files.some(countsTowardSlot))
const busy = computed(() => Boolean(props.uploads.uploading[key.value]))
</script>

<template>
  <li class="evidence-slots__slot">
    <div class="evidence-slots__head">
      <span class="evidence-slots__title">{{ title }}</span>
      <Badge v-if="attachment?.required" :variant="isProvided ? 'success' : 'warning'">
        {{ isProvided ? t('accredited.evidence.provided') : t('accredited.evidence.required') }}
      </Badge>
      <Badge v-else variant="muted">{{ t('accredited.evidence.optional') }}</Badge>
    </div>
    <p v-if="guidance" class="evidence-slots__guidance">{{ guidance }}</p>
    <p v-if="hasOwnRule" class="evidence-slots__rule">
      {{ t('accredited.evidence.slotRule', { types: types.join(', '), count: maxFiles }) }}
    </p>
    <ul v-if="files.length" class="evidence-slots__files">
      <EvidenceFileItem
        v-for="file in files"
        :key="file.id"
        :file="file"
        :editable="editable"
        :accept="accept"
        :busy="Boolean(uploads.removing[file.id]) || busy"
        :downloading="Boolean(uploads.downloading[file.id])"
        @download="emit('download', $event)"
        @remove="emit('remove', $event)"
        @replace="emit('replace', { ...$event, attachment })"
      />
    </ul>
    <EvidenceUploadButton
      v-if="editable"
      :label="t('accredited.evidence.upload')"
      :accept="accept"
      :disabled="ownerFull || isSlotFull"
      :loading="busy"
      @select="emit('upload', { owner, attachment, file: $event })"
    />
    <p v-if="editable && isSlotFull && !ownerFull" class="evidence-slots__full" role="status">
      {{ t('accredited.evidence.slotFull', { count: maxFiles }) }}
    </p>
    <p v-if="uploads.errors[key]" class="evidence-slots__error" role="alert">
      {{ uploads.errors[key] }}
    </p>
  </li>
</template>
