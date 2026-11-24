import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { awardCycleService } from '@/services/awardCycleService'
import { useToast } from '@/composables/useToast'
import { CANDIDATE_ROLES, MEMBER_ROLES } from '@/constants/advanced'

const ROLE_FIELDS = {
  [MEMBER_ROLES.FILTERING_OFFICER]: 'filteringOfficerIds',
  [MEMBER_ROLES.AWARD_SECRETARY]: 'awardSecretaryIds',
  [CANDIDATE_ROLES.INITIAL_ASSESSOR]: 'assessorIds',
}
const MEMBER_FIELD_ROLES = [MEMBER_ROLES.FILTERING_OFFICER, MEMBER_ROLES.AWARD_SECRETARY]

function idsOf(members, role) {
  return members.filter((member) => member.role === role).map((member) => member.id)
}

function serverSelection(cycle) {
  return {
    [MEMBER_ROLES.FILTERING_OFFICER]: idsOf(cycle?.members ?? [], MEMBER_ROLES.FILTERING_OFFICER),
    [MEMBER_ROLES.AWARD_SECRETARY]: idsOf(cycle?.members ?? [], MEMBER_ROLES.AWARD_SECRETARY),
    [CANDIDATE_ROLES.INITIAL_ASSESSOR]: (cycle?.initialAssessors ?? [])
      .filter((link) => link.isActive)
      .map((link) => link.id),
  }
}

function sameIds(left, right) {
  return JSON.stringify([...left].sort()) === JSON.stringify([...right].sort())
}

function roleOfField(field) {
  return Object.keys(ROLE_FIELDS).find((role) => field.split('.')[0] === ROLE_FIELDS[role])
}

function errorsByRole(fieldErrors) {
  const errors = {}
  Object.entries(fieldErrors ?? {}).forEach(([field, messages]) => {
    const role = roleOfField(field)
    if (role && !errors[role]) errors[role] = Array.isArray(messages) ? messages[0] : messages
  })
  return errors
}

function useStaffCandidates(cycle) {
  const { t } = useI18n()
  const toast = useToast()
  const candidates = ref({})
  const loading = ref(false)

  function optionsFor(role) {
    const known = new Map((candidates.value[role] ?? []).map((person) => [person.id, person.name]))
    const current =
      role === CANDIDATE_ROLES.INITIAL_ASSESSOR
        ? (cycle.value?.initialAssessors ?? [])
        : (cycle.value?.members ?? []).filter((member) => member.role === role)
    current.forEach((person) => known.set(person.id, known.get(person.id) ?? person.name))
    return [...known.entries()].map(([value, label]) => ({ value, label }))
  }

  async function loadCandidates() {
    loading.value = true
    try {
      const roles = Object.values(CANDIDATE_ROLES)
      const lists = await Promise.all(
        roles.map((role) => awardCycleService.listCandidates({ role })),
      )
      candidates.value = Object.fromEntries(roles.map((role, index) => [role, lists[index].data]))
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    } finally {
      loading.value = false
    }
  }

  return { loading, optionsFor, loadCandidates }
}

export function useCycleStaff({ editor, reasonExtra }) {
  const { t } = useI18n()
  const toast = useToast()
  const cycle = computed(() => editor.cycle.value)
  const selection = ref(serverSelection(null))
  const errors = ref({})
  const saving = ref(false)
  const membersChanged = computed(() =>
    MEMBER_FIELD_ROLES.some(
      (role) => !sameIds(selection.value[role], serverSelection(cycle.value)[role]),
    ),
  )
  const assessorsChanged = computed(
    () =>
      !sameIds(
        selection.value[CANDIDATE_ROLES.INITIAL_ASSESSOR],
        serverSelection(cycle.value)[CANDIDATE_ROLES.INITIAL_ASSESSOR],
      ),
  )
  const dirty = computed(() => membersChanged.value || assessorsChanged.value)

  function reset() {
    selection.value = serverSelection(cycle.value)
    errors.value = {}
  }

  function select(role, ids) {
    selection.value = { ...selection.value, [role]: ids }
    errors.value = { ...errors.value, [role]: '' }
  }

  function requests(extra) {
    const id = cycle.value.id
    const members = Object.fromEntries(
      MEMBER_FIELD_ROLES.map((role) => [ROLE_FIELDS[role], selection.value[role]]),
    )
    const assessorIds = selection.value[CANDIDATE_ROLES.INITIAL_ASSESSOR]
    return [
      membersChanged.value && (() => awardCycleService.syncMembers(id, { ...members, ...extra })),
      assessorsChanged.value &&
        (() => awardCycleService.syncInitialAssessors(id, { assessorIds, ...extra })),
    ].filter(Boolean)
  }

  async function send(extra) {
    for (const request of requests(extra)) {
      const { data } = await request()
      editor.apply(data)
    }
  }

  async function saveAll() {
    if (!dirty.value) return true
    if (saving.value) return false
    saving.value = true
    try {
      const extra = await reasonExtra()
      if (extra === null) return false
      await send(extra)
      reset()
      toast.success(t('cycles.staff.saved'))
      return true
    } catch (error) {
      errors.value = errorsByRole(error?.normalized?.fieldErrors)
      toast.error(error?.normalized?.message || t('errors.unexpected'))
      return false
    } finally {
      saving.value = false
    }
  }

  watch(
    cycle,
    () => {
      if (!saving.value) reset()
    },
    { immediate: true },
  )

  return { selection, errors, saving, dirty, select, reset, saveAll, ...useStaffCandidates(cycle) }
}
