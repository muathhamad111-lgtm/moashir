<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import EntityDeleteDialog from './EntityDeleteDialog.vue'
import PermanentDeleteDialog from './PermanentDeleteDialog.vue'
import EntityEditDialog from './EntityEditDialog.vue'
import { useEntityActionRunner } from '@/composables/useEntityActionRunner'
import { ENTITY_ACTIONS as A } from '@/composables/useEntityActions'
import { localizedText } from '@/utils/localizedText'

const emit = defineEmits(['done'])

const { t, locale } = useI18n()
const { state, run, reasonAction, finish, closeDialog } = useEntityActionRunner({
  onDone: (outcome) => emit('done', outcome),
})

const entityName = computed(() => localizedText(state.entity?.name, locale.value))

function dialogModel(key) {
  return computed({
    get: () => state.dialog === key,
    set: (open) => {
      if (!open) closeDialog()
    },
  })
}

const deleteOpen = dialogModel(A.DELETE)
const permanentOpen = dialogModel(A.PERMANENT_DELETE)
const editOpen = dialogModel(A.EDIT)

defineExpose({ run })
</script>

<template>
  <ReasonDialog
    v-if="state.entity && state.reasonKey"
    :key="state.reasonKey"
    v-model="state.reasonOpen"
    :title="t(`entities.reasonDialogs.${state.reasonKey}.title`)"
    :message="t(`entities.reasonDialogs.${state.reasonKey}.message`, { name: entityName })"
    :submit-label="t(`entities.actions.${state.reasonKey}`)"
    :action="reasonAction"
    :action-name="`entities.${state.reasonKey}.submit`"
    @done="finish(state.reasonKey, state.entity, $event)"
  />
  <EntityDeleteDialog
    v-if="state.entity"
    v-model="deleteOpen"
    :entity="state.entity"
    @deleted="finish(A.DELETE, state.entity)"
  />
  <PermanentDeleteDialog
    v-if="state.entity"
    v-model="permanentOpen"
    :entity="state.entity"
    @erased="finish(A.PERMANENT_DELETE, state.entity, $event)"
  />
  <EntityEditDialog
    v-if="state.entity"
    v-model="editOpen"
    :entity="state.entity"
    @saved="finish(A.EDIT, state.entity, $event)"
  />
</template>
