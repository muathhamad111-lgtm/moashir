import { buildPostureOf, showsModulesAwaitingBackend } from '@/utils/build/buildPosture'

export const BUILD_POSTURE = buildPostureOf(import.meta.env)

export const SHOWS_MODULES_AWAITING_BACKEND = showsModulesAwaitingBackend(BUILD_POSTURE)
