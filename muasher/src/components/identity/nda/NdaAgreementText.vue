<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import RichTextBlocks from '@/components/legal/RichTextBlocks.vue'

const props = defineProps({
  agreement: { type: Object, required: true },
  showChanges: { type: Boolean, default: false },
})

const emit = defineEmits(['reached-end'])

const SCROLL_SLACK = 4

const { t, locale } = useI18n()
const scroller = ref(null)

const blocks = computed(() => props.agreement.body?.[locale.value] ?? [])
const changes = computed(() => props.agreement.changeSummary?.[locale.value] ?? [])

function check() {
  const element = scroller.value
  // No layout yet (or none at all, as in tests): wait for a real measurement before enabling acceptance.
  if (!element || element.scrollHeight === 0) return
  if (element.scrollTop + element.clientHeight >= element.scrollHeight - SCROLL_SLACK) {
    emit('reached-end')
  }
}

onMounted(check)
watch(blocks, () => requestAnimationFrame(check))
</script>

<template>
  <div class="nda-text">
    <p class="nda-text__version">
      {{ t('nda.modal.version', { version: agreement.version }) }}
    </p>
    <section
      v-if="showChanges && changes.length"
      class="nda-text__changes"
      :aria-label="t('nda.modal.changes')"
    >
      <h3 class="nda-text__changes-title">{{ t('nda.modal.changes') }}</h3>
      <RichTextBlocks :blocks="changes" :heading-base="4" />
    </section>
    <div
      ref="scroller"
      class="nda-text__body"
      tabindex="0"
      data-testid="nda-text"
      :aria-label="t('nda.modal.textLabel')"
      @scroll.passive="check"
    >
      <RichTextBlocks :blocks="blocks" :heading-base="3" />
    </div>
  </div>
</template>
