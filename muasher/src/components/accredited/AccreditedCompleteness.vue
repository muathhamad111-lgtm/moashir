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

const emit = defineEmits(['reveal', 'reveal-main'])
const { t, locale } = useI18n()
const heading = ref(null)

const subs = computed(
  () => new Map(props.criteria.mains.flatMap((main) => main.subs.map((sub) => [sub.id, sub]))),
)
const mains = computed(() => new Map(props.criteria.mains.map((main) => [main.id, main])))

function mainItem(item) {
  const main = mains.value.get(item.mainCriterionId)
  const slot = (main?.attachments ?? []).find((entry) => entry.key === item.attachmentKey)
  return {
    key: `main-${item.mainCriterionId}-${item.attachmentKey}`,
    mainId: item.mainCriterionId,
    subName: localizedText(main?.name, locale.value),
    label: t('accredited.completeness.mainAttachment', {
      name: slot ? localizedText(slot.name, locale.value) : item.attachmentKey,
    }),
  }
}

function slotName(item) {
  const slot = slotsOf(subs.value.get(item.subCriterionId) ?? {}).find(
    (entry) => entry.key === item.attachmentKey,
  )
  return slot ? localizedText(slot.name, locale.value) : item.attachmentKey
}

function subName(item) {
  return localizedText(subs.value.get(item.subCriterionId)?.name, locale.value)
}

const items = computed(() => [
  ...props.missing.missingCv.map((item) => ({
    key: `cv-${item.subCriterionId}`,
    subId: item.subCriterionId,
    subName: subName(item),
    label: t('accredited.completeness.cv'),
  })),
  ...props.missing.missingAttachments.map((item) => ({
    key: `att-${item.subCriterionId}-${item.attachmentKey}`,
    subId: item.subCriterionId,
    subName: subName(item),
    label: t('accredited.completeness.attachment', { name: slotName(item) }),
  })),
  ...props.missing.missingMainAttachments.map(mainItem),
])
const count = computed(() => items.value.length)

function reveal(item) {
  if (item.mainId) emit('reveal-main', item.mainId)
  else emit('reveal', item.subId)
}

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
        <button type="button" class="accredited-completeness__link" @click="reveal(item)">
          <span class="accredited-completeness__sub">{{ item.subName }}</span>
          {{ item.label }}
        </button>
      </li>
    </ul>
  </Card>
</template>
