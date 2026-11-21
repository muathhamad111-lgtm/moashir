<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'

// The full list behind the compact criteria-version alert: what kept its score, what needs a
// new one and what was added (design §B.3.6).
const props = defineProps({
  update: { type: Object, required: true },
  cause: { type: String, default: '' },
})

const { t, locale } = useI18n()

const GROUPS = ['kept', 'reset', 'added']

const groups = computed(() => GROUPS.map((key) => ({ key, items: props.update[key] })))

function labelOf(item) {
  return item.label
    ? localizedText(item.label, locale.value)
    : t('assessment.self.rebind.unknownLabel')
}
</script>

<template>
  <div class="rebind-details">
    <p v-if="cause" class="rebind-details__cause">{{ cause }}</p>
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
            <bdi dir="ltr" class="rebind-summary__code">{{ item.code }}</bdi>
            <span>{{ labelOf(item) }}</span>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
