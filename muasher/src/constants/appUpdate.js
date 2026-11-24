// An open tab checks this often whether a newer build of the platform has been deployed.
export const APP_UPDATE_POLL_MS = 5 * 60 * 1000

// Checks on focus or when the tab becomes visible again run at most this often.
export const APP_UPDATE_MIN_GAP_MS = 60 * 1000

// The deployed page lists the hashed bundle files, so it tells which build is live.
export const APP_UPDATE_INDEX_URL = '/index.html'
