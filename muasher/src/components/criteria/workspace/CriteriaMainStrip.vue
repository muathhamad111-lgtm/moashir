<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useCriteria } from '@/composables/useCriteria'
import { formatShare, shareOf } from '@/utils/weight'

// The main criteria side by side: pick one to show it at full width (no side tree).
defineProps({
  mains: { type: Array, required: true },
  selectedKey: { type: String, default: null },
  modelWeight: { type: String, required: true },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['select', 'add'])
const { t, locale } = useI18n()
const { localized } = useCriteria()

const share = (main, total) => formatShare(shareOf(main.node.weight, total), locale.value)
</script>

<template>
  <nav class="criteria-strip" :aria-label="t('criteria.workspace.strip.label')">
    <ol class="criteria-strip__list">
      <li v-for="(main, position) in mains" :key="main.key" class="criteria-strip__item">
        <button
          type="button"
          class="criteria-strip__step"
          :class="{ 'is-attention': main.attention, 'is-inactive': !main.node.active }"
          :aria-current="main.key === selectedKey ? 'true' : undefined"
          :title="localized(main.node.name)"
          @click="emit('select', main.key)"
        >
          <span class="criteria-strip__number" aria-hidden="true">{{ position + 1 }}</span>
          <span class="criteria-strip__text">
            <span class="criteria-strip__name">{{ localized(main.node.name) }}</span>
            <span class="criteria-strip__meta">
              <bdi dir="ltr">{{ share(main, modelWeight) }}</bdi>
              ·
              <template v-if="main.attention">{{
                t('criteria.workspace.tree.attention')
              }}</template>
              <template v-else>{{
                t(
                  'criteria.workspace.strip.subs',
                  { count: main.children.length },
                  main.children.length,
                )
              }}</template>
            </span>
          </span>
          <AppIcon v-if="main.attention" name="warning-triangle" class="criteria-strip__alert" />
        </button>
      </li>
      <li v-if="canEdit" class="criteria-strip__item criteria-strip__item--add">
        <BaseButton variant="ghost" size="sm" icon="plus" @click="emit('add')">{{
          t('criteria.workspace.tree.addMain')
        }}</BaseButton>
      </li>
    </ol>
  </nav>
</template>
