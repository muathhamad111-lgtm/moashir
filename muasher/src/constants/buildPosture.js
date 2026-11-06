import { buildPostureOf, showsDemoOnlyModules } from '@/utils/build/buildPosture'

export const BUILD_POSTURE = buildPostureOf(import.meta.env)

export const SHOWS_DEMO_ONLY_MODULES = showsDemoOnlyModules(BUILD_POSTURE)
