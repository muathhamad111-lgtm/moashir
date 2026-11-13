<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import EvidenceFileItem from './EvidenceFileItem.vue'
import EvidenceUploadButton from './EvidenceUploadButton.vue'
import { slotKeyOf } from '@/composables/useEvidenceUploads'
import { countsTowardSlot, evidenceOfSlot, slotsOf } from '@/utils/accredited/evidenceRules'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  sub: { type: Object, required: true },
  evidence: { type: Array, required: true },
  editable: { type: Boolean, default: false },
  limits: { type: Object, required: true },
  accept: { type: String, default: '' },
  uploads: { type: Object, required: true },
})

const emit = defineEmits(['upload', 'remove', 'replace', 'download'])
const { t, locale } = useI18n()

const isFull = computed(
  () =>
    props.limits.maxFilesPerSub !== null && props.evidence.length >= props.limits.maxFilesPerSub,
)

const slots = computed(() => [
  ...slotsOf(props.sub).map((slot) => ({
    key: slot.key,
    title: localizedText(slot.name, locale.value),
    pointCode: slot.pointCode,
    required: slot.required,
    files: evidenceOfSlot(props.evidence, props.sub.id, slot.key),
  })),
  {
    key: null,
    title: t('accredited.evidence.general'),
    pointCode: null,
    required: false,
    files: evidenceOfSlot(props.evidence, props.sub.id, null),
  },
])

function isProvided(slot) {
  return slot.files.some(countsTowardSlot)
}

function slotError(slot) {
  return props.uploads.errors[slotKeyOf(props.sub.id, slot.key)] ?? ''
}
</script>

<template>
  <section
    class="evidence-slots"
    :aria-label="t('accredited.evidence.groupLabel', { code: sub.code })"
  >
    <p class="evidence-slots__limits">
      {{
        t('accredited.evidence.limits', {
          max: limits.maxFileMb,
          count: limits.maxFilesPerSub,
          types: limits.allowedTypes.join(', '),
        })
      }}
    </p>
    <ul class="evidence-slots__list">
      <li v-for="slot in slots" :key="slot.key ?? 'general'" class="evidence-slots__slot">
        <div class="evidence-slots__head">
          <span class="evidence-slots__title">
            <span v-if="slot.pointCode" class="evidence-slots__code u-ltr">{{
              slot.pointCode
            }}</span>
            {{ slot.title }}
          </span>
          <Badge v-if="slot.required" :variant="isProvided(slot) ? 'success' : 'warning'">
            {{
              isProvided(slot)
                ? t('accredited.evidence.provided')
                : t('accredited.evidence.required')
            }}
          </Badge>
          <Badge v-else variant="muted">{{ t('accredited.evidence.optional') }}</Badge>
        </div>
        <ul v-if="slot.files.length" class="evidence-slots__files">
          <EvidenceFileItem
            v-for="file in slot.files"
            :key="file.id"
            :file="file"
            :editable="editable"
            :accept="accept"
            :busy="
              Boolean(uploads.removing[file.id] || uploads.uploading[slotKeyOf(sub.id, slot.key)])
            "
            :downloading="Boolean(uploads.downloading[file.id])"
            @download="emit('download', $event)"
            @remove="emit('remove', $event)"
            @replace="emit('replace', $event)"
          />
        </ul>
        <EvidenceUploadButton
          v-if="editable"
          :label="t('accredited.evidence.upload')"
          :accept="accept"
          :disabled="isFull"
          :loading="Boolean(uploads.uploading[slotKeyOf(sub.id, slot.key)])"
          @select="emit('upload', { key: slot.key, file: $event })"
        />
        <p v-if="slotError(slot)" class="evidence-slots__error" role="alert">
          {{ slotError(slot) }}
        </p>
      </li>
    </ul>
    <p v-if="editable && isFull" class="evidence-slots__full" role="status">
      {{ t('accredited.evidence.full', { count: limits.maxFilesPerSub }) }}
    </p>
  </section>
</template>
