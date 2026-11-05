<script setup>
import { ref, shallowRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseModal from '@/components/modal/BaseModal.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import { secretaryService } from '@/services/secretaryService'
import { errorMessageOf } from '@/utils/award/loadState'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  cycleId: { type: Number, required: true },
  assessmentId: { type: Number, required: true },
  assignment: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])
const { t, locale } = useI18n()
const status = ref('loading')
const errorMessage = ref('')
const evaluation = shallowRef(null)

async function load() {
  status.value = 'loading'
  try {
    evaluation.value = (
      await secretaryService.getEvaluation(props.cycleId, props.assessmentId, props.assignment.id)
    ).data
    status.value = 'ready'
  } catch (error) {
    errorMessage.value = errorMessageOf(error)
    status.value = 'error'
  }
}

watch(
  () => [props.modelValue, props.assignment?.id],
  ([open]) => {
    if (open && props.assignment) load()
  },
  { immediate: true },
)
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    size="lg"
    :title="t('award.secretary.evaluation.title', { name: assignment?.member.user?.name ?? '' })"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div v-if="status === 'loading'" aria-busy="true"><Skeleton variant="text" :count="6" /></div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <div v-else-if="evaluation" class="secretary-evaluation">
      <p class="secretary-evaluation__total">
        {{
          t('award.secretary.evaluation.total', {
            percent: evaluation.result.percent,
            scored: evaluation.progress.scored,
            required: evaluation.progress.required,
          })
        }}
      </p>
      <table class="secretary-evaluation__table">
        <thead>
          <tr>
            <th scope="col">{{ t('award.secretary.evaluation.sub') }}</th>
            <th scope="col">{{ t('award.secretary.evaluation.percent') }}</th>
            <th scope="col">{{ t('award.secretary.evaluation.note') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sub in evaluation.result.sub" :key="sub.subId">
            <td>
              <bdi class="u-ltr">{{ sub.code }}</bdi> {{ localizedText(sub.name, locale) }}
            </td>
            <td>
              <bdi class="u-ltr">{{ sub.percent }}%</bdi>
            </td>
            <td>{{ evaluation.notes.subs[String(sub.subId)] ?? '' }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="evaluation.notes.general" class="secretary-evaluation__general">
        <p class="discussion-card__label">{{ t('award.evaluation.generalNote') }}</p>
        <p class="discussion-card__text">{{ evaluation.notes.general }}</p>
      </div>
    </div>
  </BaseModal>
</template>
