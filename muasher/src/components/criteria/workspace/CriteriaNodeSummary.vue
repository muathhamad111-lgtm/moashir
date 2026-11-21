<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import CriteriaAttachmentChips from './CriteriaAttachmentChips.vue'
import { useCriteria } from '@/composables/useCriteria'
import { useScoringTypeOptions } from '@/composables/useScoringTypeOptions'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

// View mode of the editor sheet: values as text, never as disabled inputs (audit CR-1).
const props = defineProps({
  item: { type: Object, required: true },
  section: { type: String, required: true },
})

const { t } = useI18n()
const { weight } = useCriteria()
const { labelOf } = useScoringTypeOptions()

const node = computed(() => props.item.node)
const isPoint = computed(() => props.item.type === NODE.POINT)
const text = (pair, lang) => pair?.[lang] || ''

const yesNo = (value) => t(`criteria.workspace.sheet.${value ? 'yes' : 'no'}`)

function evaluationRows(n) {
  return [{ label: t('criteria.fields.scoringType'), value: labelOf(n.scoringType) }]
}

function advancedRows(n) {
  return [
    {
      label: t('criteria.fields.assessorNoteAr'),
      value: text(n.assessorNoteLabel, 'ar'),
      dir: 'rtl',
    },
    {
      label: t('criteria.fields.assessorNoteEn'),
      value: text(n.assessorNoteLabel, 'en'),
      dir: 'ltr',
    },
    { label: t('criteria.fields.hasCv'), value: yesNo(n.hasCv) },
  ]
}

function basicsRows(n) {
  const names = isPoint.value ? n.description : n.name
  const nameKey = isPoint.value ? 'criteria.fields.description' : 'criteria.fields.name'
  const list = [
    { label: t(`${nameKey}Ar`), value: text(names, 'ar'), dir: 'rtl' },
    { label: t(`${nameKey}En`), value: text(names, 'en'), dir: 'ltr' },
  ]
  if (props.item.type === NODE.MAIN) {
    list.push({ label: t('criteria.fields.weight'), value: weight(n.weight) })
    list.push({ label: t('criteria.fields.group'), value: t(`criteria.groups.${n.group}`) })
  }
  if (!isPoint.value) {
    list.push({
      label: t('criteria.fields.descriptionAr'),
      value: text(n.description, 'ar'),
      dir: 'rtl',
    })
    list.push({
      label: t('criteria.fields.descriptionEn'),
      value: text(n.description, 'en'),
      dir: 'ltr',
    })
  }
  return list
}

const ROWS = { evaluation: evaluationRows, advanced: advancedRows, basics: basicsRows }
const rows = computed(() => (ROWS[props.section] ?? basicsRows)(node.value))
</script>

<template>
  <div v-if="section === 'attachments'" class="criteria-sheet__stack">
    <CriteriaAttachmentChips
      v-if="node.attachments?.length"
      :attachments="node.attachments"
      :label="t('criteria.attachments.title')"
    />
    <p v-else class="criteria-card__empty">{{ t('criteria.attachments.empty') }}</p>
  </div>
  <dl v-else class="criteria-summary">
    <div v-for="row in rows" :key="row.label" class="criteria-summary__row">
      <dt>{{ row.label }}</dt>
      <dd :dir="row.dir">{{ row.value || '—' }}</dd>
    </div>
  </dl>
</template>
