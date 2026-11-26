<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { mailMarkdownBlocks } from '@/utils/mailMarkdown'

const props = defineProps({
  subject: { type: String, default: '' },
  body: { type: String, default: '' },
  fill: { type: Function, default: (text) => text },
  action: { type: String, default: '' },
  locale: { type: String, required: true },
})

const { t } = useI18n()
const blocks = computed(() => mailMarkdownBlocks(props.body, props.fill))
</script>

<template>
  <div
    class="notification-preview notification-preview--email"
    :dir="locale === 'ar' ? 'rtl' : 'ltr'"
    :lang="locale"
  >
    <div class="notification-preview__mail-meta">
      <span>{{ t('settings.notifications.texts.preview.from', {}, { locale }) }}</span>
      <strong>{{ subject }}</strong>
    </div>
    <div class="notification-preview__mail">
      <div class="notification-preview__mail-brand">
        {{ t('settings.notifications.texts.preview.brand', {}, { locale }) }}
      </div>
      <div class="notification-preview__mail-body">
        <template v-for="(block, index) in blocks" :key="index">
          <ul v-if="block.type === 'list'">
            <li v-for="(item, itemIndex) in block.items" :key="itemIndex">
              <template v-for="(token, tokenIndex) in item" :key="tokenIndex">
                <strong v-if="token.type === 'bold'">{{ token.text }}</strong>
                <a
                  v-else-if="token.type === 'link'"
                  :href="token.href"
                  rel="noopener"
                  target="_blank"
                  >{{ token.text }}</a
                >
                <template v-else>{{ token.text }}</template>
              </template>
            </li>
          </ul>
          <p v-else>
            <template v-for="(token, tokenIndex) in block.inlines" :key="tokenIndex">
              <strong v-if="token.type === 'bold'">{{ token.text }}</strong>
              <a
                v-else-if="token.type === 'link'"
                :href="token.href"
                rel="noopener"
                target="_blank"
                >{{ token.text }}</a
              >
              <template v-else>{{ token.text }}</template>
            </template>
          </p>
        </template>
        <span v-if="action" class="notification-preview__mail-button">{{ action }}</span>
      </div>
    </div>
  </div>
</template>
