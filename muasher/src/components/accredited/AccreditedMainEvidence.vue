<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Badge from '@/components/ui/Badge.vue'
import EvidenceSlotGroup from './EvidenceSlotGroup.vue'
import { COMPLETION_ITEM_KINDS } from '@/constants/accredited'
import { evidenceOfMain, mainOwner } from '@/utils/accredited/evidenceRules'
import { localizedText } from '@/utils/localizedText'

// «شواهد المعيار الرئيسي»: the attachments a main criterion asks for as a whole.
const props = defineProps({
  main: { type: Object, required: true },
  workspace: { type: Object, required: true },
})

const emit = defineEmits(['upload', 'remove', 'replace', 'download'])
const { t, locale } = useI18n()

const form = computed(() => props.workspace.formState.form.value)
const owner = computed(() => mainOwner(props.main))
const editable = computed(() =>
  props.workspace.formState.canEdit(owner.value, COMPLETION_ITEM_KINDS.EVIDENCE),
)
const isRequested = computed(() => props.workspace.formState.isResubmission.value && editable.value)
const isMissing = computed(() =>
  form.value.missing.missingMainAttachments.some((item) => item.mainCriterionId === props.main.id),
)
const groups = computed(() => [{ key: 'own', point: null, slots: props.main.attachments }])
</script>

<template>
  <section
    :id="`accredited-main-evidence-${main.id}`"
    class="accredited-main-evidence"
    :class="{ 'accredited-main-evidence--requested': isRequested }"
    :aria-labelledby="`accredited-main-evidence-title-${main.id}`"
    tabindex="-1"
  >
    <div class="accredited-main-evidence__head">
      <h3 :id="`accredited-main-evidence-title-${main.id}`" class="accredited-main-evidence__title">
        {{ t('accredited.evidence.mainTitle') }}
      </h3>
      <div class="accredited-main-evidence__badges">
        <Badge v-if="isRequested" variant="warning">{{ t('accredited.form.requestedItem') }}</Badge>
        <Badge :variant="isMissing ? 'warning' : 'success'">
          {{
            isMissing
              ? t('portal.assessments.task.subPending')
              : t('portal.assessments.task.subComplete')
          }}
        </Badge>
      </div>
    </div>
    <p class="accredited-main-evidence__hint">{{ t('accredited.evidence.mainHint') }}</p>
    <EvidenceSlotGroup
      :owner="owner"
      :label="localizedText(main.name, locale)"
      :groups="groups"
      :evidence="evidenceOfMain(form.evidence, main.id)"
      :editable="editable"
      :limits="form.uploadLimits"
      :uploads="workspace.uploads"
      @upload="emit('upload', $event)"
      @remove="emit('remove', $event)"
      @replace="emit('replace', $event)"
      @download="emit('download', $event)"
    />
  </section>
</template>
