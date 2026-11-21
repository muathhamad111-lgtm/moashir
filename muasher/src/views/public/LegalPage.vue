<script setup>
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import RichTextBlocks from '@/components/legal/RichTextBlocks.vue'
import { useLegalDocumentsStore } from '@/stores/legalDocuments'
import { LEGAL_STATES } from '@/constants/legal'
import { isoDate } from '@/utils/datetime'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  kind: { type: String, required: true },
})

const { t, locale } = useI18n()
const store = useLegalDocumentsStore()
const state = computed(() => store.stateOf(props.kind))
const documentData = computed(() => store.documents[props.kind] ?? null)
const title = computed(
  () => localizedText(documentData.value?.title, locale.value) || t(`legal.names.${props.kind}`),
)
const updatedOn = computed(() => isoDate(documentData.value?.updatedAt))

function blocksOf(section) {
  return section.blocks?.[locale.value] ?? section.blocks?.ar ?? []
}

function print() {
  window.print()
}

useHead({ title: () => title.value })

watch(
  () => props.kind,
  (kind) => store.load(kind),
)
onMounted(() => store.load(props.kind))
</script>

<template>
  <article class="legal-page" :aria-labelledby="`legal-${kind}`">
    <Skeleton v-if="state === LEGAL_STATES.LOADING" variant="text" :count="8" />
    <EmptyState
      v-else-if="state === LEGAL_STATES.MISSING"
      icon="doc"
      :title="t('legal.missing.title', { name: t(`legal.names.${kind}`) })"
      :message="t('legal.missing.message')"
    />
    <ErrorState
      v-else-if="state === LEGAL_STATES.ERROR"
      :message="t('legal.failed')"
      @retry="store.load(kind, { force: true })"
    />
    <template v-else-if="documentData">
      <header class="legal-page__header">
        <p class="legal-page__eyebrow">{{ t('legal.eyebrow') }}</p>
        <h1 :id="`legal-${kind}`" class="legal-page__title">{{ title }}</h1>
        <dl class="legal-page__meta">
          <div v-if="documentData.version">
            <dt>{{ t('legal.version') }}</dt>
            <dd>
              <bdi dir="ltr">{{ documentData.version }}</bdi>
            </dd>
          </div>
          <div v-if="updatedOn">
            <dt>{{ t('legal.updatedOn') }}</dt>
            <dd>
              <bdi dir="ltr">{{ updatedOn }}</bdi>
            </dd>
          </div>
        </dl>
        <BaseButton
          class="legal-page__print"
          variant="secondary"
          size="sm"
          icon="printer"
          @click="print"
        >
          {{ t('legal.print') }}
        </BaseButton>
      </header>
      <section
        v-for="section in documentData.sections"
        :key="section.id"
        class="legal-page__section"
      >
        <h2 v-if="section.title" class="legal-page__section-title">
          {{ localizedText(section.title, locale) }}
        </h2>
        <RichTextBlocks :blocks="blocksOf(section)" :heading-base="section.title ? 3 : 2" />
      </section>
    </template>
  </article>
</template>
