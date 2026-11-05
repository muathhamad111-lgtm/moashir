<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import { slotsOf } from '@/utils/accredited/evidenceRules'
import { localizedText } from '@/utils/localizedText'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  missing: { type: Object, required: true },
  criteria: { type: Object, required: true },
})

const emit = defineEmits(['reveal'])
const { t, locale } = useI18n()
const heading = ref(null)

const subs = computed(
  () => new Map(props.criteria.mains.flatMap((main) => main.subs.map((sub) => [sub.id, sub]))),
)
const count = computed(
  () => props.missing.missingCv.length + props.missing.missingAttachments.length,
)

function slotName(item) {
  const slot = slotsOf(subs.value.get(item.subCriterionId) ?? {}).find(
    (entry) => entry.key === item.attachmentKey,
  )
  return slot ? localizedText(slot.name, locale.value) : item.attachmentKey
}

const items = computed(() => [
  ...props.missing.missingCv.map((item) => ({
    key: `cv-${item.subCriterionId}`,
    subId: item.subCriterionId,
    code: item.code,
    label: t('accredited.completeness.cv'),
  })),
  ...props.missing.missingAttachments.map((item) => ({
    key: `att-${item.subCriterionId}-${item.attachmentKey}`,
    subId: item.subCriterionId,
    code: item.code,
    label: t('accredited.completeness.attachment', { name: slotName(item) }),
  })),
])

function focus() {
  heading.value?.focus()
}

defineExpose({ focus })
</script>

<template>
  <Card class="accredited-completeness" :class="{ 'accredited-completeness--done': !count }">
    <h2 ref="heading" class="accredited-completeness__title" tabindex="-1">
      <AppIcon :name="count ? 'list-check' : 'check-circle'" />
      {{
        count
          ? t('accredited.completeness.title', { count }, count)
          : t('accredited.completeness.done')
      }}
    </h2>
    <p class="accredited-completeness__hint">{{ t('accredited.completeness.hint') }}</p>
    <ul v-if="count" class="accredited-completeness__list">
      <li v-for="item in items" :key="item.key" class="accredited-completeness__item">
        <button
          type="button"
          class="accredited-completeness__link"
          @click="emit('reveal', item.subId)"
        >
          <span class="accredited-completeness__code u-ltr">{{ item.code }}</span>
          {{ item.label }}
        </button>
      </li>
    </ul>
  </Card>
</template>
