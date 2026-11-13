import { logger } from '@/utils/logger'

export async function serviceRequest(
  action,
  execute,
  context = {},
  { expectedStatuses = [] } = {},
) {
  logger.breadcrumb('service', action, context)
  try {
    const response = await execute()
    return response.data
  } catch (error) {
    if (!expectedStatuses.includes(error?.response?.status))
      logger.error(error, { action, ...context })
    throw error
  }
}
