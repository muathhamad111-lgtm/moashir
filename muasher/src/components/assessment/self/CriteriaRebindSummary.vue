<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import { formatDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  update: { type: Object, required: true },
  context: {
    type: String,
    default: 'self',
    validator: (value) => ['self', 'request', 'review'].includes(value),
  },
})

const TITLE_KEYS = {
  self: 'assessment.self.rebind.title',
  request: 'assessment.self.rebind.titleRequest',
  review: 'assessment.self.rebind.titleReview',
}

const { t, locale } = useI18n()

const GROUPS = ['kept', 'reset', 'added']

const groups = computed(() => GROUPS.map((key) => ({ key, items: props.update[key] })))
const movedOn = computed(() =>
  props.update.movedAt
    ? formatDate(props.update.movedAt, locale.value, {
        dateStyle: 'medium',
        timeZone: 'Asia/Riyadh',
      })
    : '',
)
const triggersKey = computed(() => (props.context === 'self' ? 'triggers' : 'triggersRequest'))
const message = computed(() =>
  t(`assessment.self.rebind.${triggersKey.value}.${props.update.trigger}`, {
    date: movedOn.value,
    scope: t(`assessment.self.rebind.scopes.${props.update.scope}`),
  }),
)

function labelOf(item) {
  return item.label
    ? localizedText(item.label, locale.value)
    : t('assessment.self.rebind.unknownLabel')
}
</script>

<template>
  <AssessmentNotice
    variant="info"
    :title="
      t(TITLE_KEYS[context], {
        from: update.fromVersionNumber,
        to: update.toVersionNumber,
      })
    "
    :message="message"
  >
    <div class="rebind-summary">
      <section v-for="group in groups" :key="group.key" class="rebind-summary__group">
        <h3 class="rebind-summary__title">
          {{
            t(
              `assessment.self.rebind.${group.key}`,
              { count: group.items.length },
              group.items.length,
            )
          }}
        </h3>
        <ul v-if="group.items.length" class="rebind-summary__list">
          <li
            v-for="item in group.items"
            :key="`${item.level}-${item.code}`"
            class="rebind-summary__item"
          >
            {{ labelOf(item) }}
          </li>
        </ul>
      </section>
    </div>
  </AssessmentNotice>
</template>
