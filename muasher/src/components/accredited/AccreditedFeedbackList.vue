<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import { FEEDBACK_FIELDS } from '@/constants/accredited'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  feedback: { type: Array, required: true },
})

const FIELD_MODIFIERS = { strengths: 'strengths', improvementPoints: 'improvement', notes: 'notes' }

const { t, locale } = useI18n()
const heading = ref(null)

const items = computed(() =>
  props.feedback.map((item) => ({
    ...item,
    fields: FEEDBACK_FIELDS.filter((field) => item[field]).map((field) => ({
      field,
      text: item[field],
    })),
  })),
)

function focus() {
  heading.value?.scrollIntoView({ block: 'start' })
  heading.value?.focus()
}

defineExpose({ focus })
</script>

<template>
  <Card class="accredited-feedback">
    <h2 ref="heading" class="accredited-feedback__title" tabindex="-1">
      {{ t('accredited.result.feedbackTitle') }}
    </h2>
    <p class="accredited-feedback__hint">{{ t('accredited.result.feedbackHint') }}</p>
    <ul class="accredited-feedback__list">
      <li v-for="item in items" :key="item.subId" class="accredited-feedback__item">
        <div class="accredited-feedback__head">
          <h3 class="accredited-feedback__sub">
            <span class="accredited-feedback__code u-ltr">{{ item.code }}</span>
            {{ localizedText(item.name, locale) }}
          </h3>
          <span v-if="item.percent" class="accredited-feedback__percent u-ltr"
            >{{ item.percent }}%</span
          >
        </div>
        <dl v-if="item.fields.length" class="accredited-feedback__fields">
          <div
            v-for="entry in item.fields"
            :key="entry.field"
            class="accredited-feedback__field"
            :class="`accredited-feedback__field--${FIELD_MODIFIERS[entry.field]}`"
          >
            <dt>{{ t(`accredited.feedback.${entry.field}`) }}</dt>
            <dd>{{ entry.text }}</dd>
          </div>
        </dl>
        <p v-else class="accredited-feedback__empty">{{ t('accredited.result.noFeedback') }}</p>
      </li>
    </ul>
  </Card>
</template>
