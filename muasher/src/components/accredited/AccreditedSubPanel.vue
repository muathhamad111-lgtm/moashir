<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import CvEntryField from './CvEntryField.vue'
import EvidenceSlotGroup from './EvidenceSlotGroup.vue'
import { COMPLETION_ITEM_KINDS } from '@/constants/accredited'
import { evidenceOfSub } from '@/utils/accredited/evidenceRules'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  sub: { type: Object, required: true },
  workspace: { type: Object, required: true },
})

const emit = defineEmits(['upload', 'remove', 'replace', 'download'])
const { t, locale } = useI18n()

const form = computed(() => props.workspace.formState.form.value)
const cvEditable = computed(() => props.workspace.formState.canEdit(props.sub.id, COMPLETION_ITEM_KINDS.CV))
const evidenceEditable = computed(() => props.workspace.formState.canEdit(props.sub.id, COMPLETION_ITEM_KINDS.EVIDENCE))
const isRequested = computed(() => props.workspace.formState.isResubmission.value && (cvEditable.value || evidenceEditable.value))
const cvMissing = computed(() => form.value.missing.missingCv.some((item) => item.subCriterionId === props.sub.id))
const subEvidence = computed(() => evidenceOfSub(form.value.evidence, props.sub.id))

function text(content) {
  return localizedText(content, locale.value)
}
</script>

<template>
  <li :id="`accredited-sub-${sub.id}`" class="accredited-sub" :class="{ 'accredited-sub--requested': isRequested }" tabindex="-1">
    <div class="accredited-sub__head">
      <h3 class="accredited-sub__title">
        <span class="accredited-sub__code u-ltr">{{ sub.code }}</span>
        {{ text(sub.name) }}
      </h3>
      <Badge v-if="isRequested" variant="warning">{{ t('accredited.form.requestedItem') }}</Badge>
    </div>
    <p v-if="sub.description" class="accredited-sub__description">{{ text(sub.description) }}</p>
    <div v-if="sub.points.length" class="accredited-sub__guidance">
      <p class="accredited-sub__guidance-title">{{ t('accredited.form.guidanceTitle') }}</p>
      <ul class="accredited-sub__points">
        <li v-for="point in sub.points" :key="point.id" class="accredited-sub__point">
          <span class="accredited-sub__code u-ltr">{{ point.code }}</span>
          {{ text(point.description) }}
        </li>
      </ul>
    </div>
    <CvEntryField
      v-if="sub.hasCv"
      :sub-id="sub.id"
      :code="sub.code"
      :model-value="workspace.cvDrafts.draftOf(sub.id)"
      :editable="cvEditable"
      :missing="cvMissing"
      @update:model-value="workspace.cvDrafts.setDraft(sub.id, $event)"
    />
    <EvidenceSlotGroup
      :sub="sub"
      :evidence="subEvidence"
      :editable="evidenceEditable"
      :limits="form.uploadLimits"
      :accept="workspace.accept.value"
      :uploads="workspace.uploads"
      @upload="emit('upload', { sub, ...$event })"
      @remove="emit('remove', $event)"
      @replace="emit('replace', $event)"
      @download="emit('download', $event)"
    />
  </li>
</template>
