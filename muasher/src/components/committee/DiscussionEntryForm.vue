<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { object, string } from 'yup'
import BaseButton from '@/components/ui/BaseButton.vue'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { COMMITTEE_LIMITS, DISCUSSION_MODES, ENTRY_KINDS } from '@/constants/committee'

const props = defineProps({
  discussion: { type: Object, required: true },
  respond: { type: Function, required: true },
})

const { t } = useI18n()
const isSession = computed(() => props.discussion.mode === DISCUSSION_MODES.SESSION)
const MIN = COMMITTEE_LIMITS.DISCUSSION_TEXT_MIN
const MAX = COMMITTEE_LIMITS.DISCUSSION_TEXT_MAX
let pendingKind = ENTRY_KINDS.OPINION

const schema = computed(() =>
  object({
    body: string()
      .trim()
      .max(MAX, t('award.common.maxChars', { max: MAX }))
      .test(
        'needed',
        t('validation.minChars', { min: MIN }),
        (value) => pendingKind === ENTRY_KINDS.APPROVE || (value ?? '').length >= MIN,
      ),
  }),
)

const form = useValidatedForm({
  schema,
  initialValues: { body: '' },
  action: 'committee.discussion.respond',
  submit: async (values) => {
    const body = values.body.trim()
    await props.respond(props.discussion, { kind: pendingKind, ...(body ? { body } : {}) })
    form.resetForm({ values: { body: '' } })
  },
})

function send(kind) {
  pendingKind = kind
  form.onSubmit()
}

watch(
  () => props.discussion.myEntry,
  (entry) => form.resetForm({ values: { body: entry?.body ?? '' } }),
  { immediate: true },
)
</script>

<template>
  <form
    class="discussion-entry"
    novalidate
    @submit.prevent="send(isSession ? ENTRY_KINDS.OPINION : ENTRY_KINDS.REQUEST_EDIT)"
  >
    <FieldTextarea
      name="body"
      :label="isSession ? t('award.discussion.opinion') : t('award.discussion.editReason')"
      :hint="t('award.discussion.textHint', { min: MIN, max: MAX })"
      :rows="4"
      :required="isSession"
    />
    <div class="discussion-entry__buttons">
      <BaseButton
        v-if="isSession"
        type="submit"
        variant="primary"
        icon="send"
        :loading="form.submitting.value"
        >{{
          discussion.myEntry
            ? t('award.discussion.updateOpinion')
            : t('award.discussion.sendOpinion')
        }}</BaseButton
      >
      <template v-else>
        <BaseButton
          variant="primary"
          icon="check"
          :loading="form.submitting.value"
          @click="send(ENTRY_KINDS.APPROVE)"
          >{{ t('award.discussion.approve') }}</BaseButton
        >
        <BaseButton
          type="submit"
          variant="secondary"
          icon="pen-line"
          :disabled="form.submitting.value"
          >{{ t('award.discussion.requestEdit') }}</BaseButton
        >
      </template>
    </div>
  </form>
</template>
