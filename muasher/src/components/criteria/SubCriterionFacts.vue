<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCriteria } from '@/composables/useCriteria'

const props = defineProps({
  sub: { type: Object, required: true },
})

const { t } = useI18n()
const { localized, weight } = useCriteria()

const facts = computed(() => [
  { key: 'weight', label: t('criteria.fields.weight'), value: weight(props.sub.weight) },
  {
    key: 'available',
    label: t('criteria.sub.availableFromMain'),
    value: weight(props.sub.availableWeight),
  },
  {
    key: 'cv',
    label: t('criteria.sub.cvField'),
    value: props.sub.hasCv ? t('criteria.sub.enabled') : t('criteria.sub.disabled'),
  },
  {
    key: 'note',
    label: t('criteria.sub.assessorNoteField'),
    value: localized(props.sub.assessorNoteLabel) || t('criteria.sub.noPrompt'),
  },
  {
    key: 'attachments',
    label: t('criteria.columns.attachments'),
    value: t(
      'criteria.attachments.count',
      { count: props.sub.attachments.length },
      props.sub.attachments.length,
    ),
  },
])
</script>

<template>
  <dl class="criteria-page__facts">
    <div v-for="fact in facts" :key="fact.key" class="criteria-page__fact">
      <dt>{{ fact.label }}</dt>
      <dd>{{ fact.value }}</dd>
    </div>
  </dl>
</template>
