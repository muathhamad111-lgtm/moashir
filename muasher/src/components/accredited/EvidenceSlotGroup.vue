<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EvidenceSlotItem from './EvidenceSlotItem.vue'
import { EVIDENCE_LEVELS, evidenceOfSlot } from '@/utils/accredited/evidenceRules'
import { localizedText } from '@/utils/localizedText'

// The attachments of one criterion. groups: [{ key, point, slots }] — point null for the criterion's
// own attachments, else the indicator they belong to. A sub also takes additional evidence.
const props = defineProps({
  owner: { type: Object, required: true },
  label: { type: String, required: true },
  groups: { type: Array, required: true },
  evidence: { type: Array, required: true },
  editable: { type: Boolean, default: false },
  limits: { type: Object, required: true },
  uploads: { type: Object, required: true },
})

const emit = defineEmits(['upload', 'remove', 'replace', 'download'])
const { t, locale } = useI18n()

const isMain = computed(() => props.owner.level === EVIDENCE_LEVELS.MAIN)
const isFull = computed(
  () =>
    props.limits.maxFilesPerSub !== null && props.evidence.length >= props.limits.maxFilesPerSub,
)
const shownGroups = computed(() => props.groups.filter((group) => group.slots.length))

function filesOf(key) {
  return evidenceOfSlot(props.evidence, props.owner, key)
}
</script>

<template>
  <section
    class="evidence-slots"
    :aria-label="t('accredited.evidence.groupLabel', { name: label })"
  >
    <p class="evidence-slots__limits">
      {{
        t(isMain ? 'accredited.evidence.limitsMain' : 'accredited.evidence.limits', {
          max: limits.maxFileMb,
          count: limits.maxFilesPerSub,
          types: limits.allowedTypes.join(', '),
        })
      }}
    </p>
    <template v-for="group in shownGroups" :key="group.key">
      <p v-if="group.point" class="evidence-slots__indicator">
        {{
          t('accredited.evidence.indicatorGroup', {
            name: localizedText(group.point.description, locale),
          })
        }}
      </p>
      <ul class="evidence-slots__list">
        <EvidenceSlotItem
          v-for="attachment in group.slots"
          :key="attachment.key"
          :attachment="attachment"
          :owner="owner"
          :files="filesOf(attachment.key)"
          :editable="editable"
          :owner-full="isFull"
          :limits="limits"
          :uploads="uploads"
          @upload="emit('upload', $event)"
          @remove="emit('remove', $event)"
          @replace="emit('replace', $event)"
          @download="emit('download', $event)"
        />
      </ul>
    </template>
    <ul v-if="!isMain" class="evidence-slots__list">
      <EvidenceSlotItem
        :owner="owner"
        :files="filesOf(null)"
        :editable="editable"
        :owner-full="isFull"
        :limits="limits"
        :uploads="uploads"
        @upload="emit('upload', $event)"
        @remove="emit('remove', $event)"
        @replace="emit('replace', $event)"
        @download="emit('download', $event)"
      />
    </ul>
    <p v-if="editable && isFull" class="evidence-slots__full" role="status">
      {{
        t(isMain ? 'accredited.evidence.fullMain' : 'accredited.evidence.full', {
          count: limits.maxFilesPerSub,
        })
      }}
    </p>
  </section>
</template>
