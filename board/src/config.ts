/**
 * Runtime settings. In a classroom the board PC opens the page from the
 * teacher's server on the local network, so by default the API and Reverb
 * are expected on the same host the page was loaded from. Empty values in
 * .env mean the same as leaving them out.
 */
const env = import.meta.env

const host = typeof window === 'undefined' ? 'localhost' : window.location.hostname

/** Dev server: API on :8000. Built board (served by the API's nginx): same origin. */
const defaultApiUrl = env.DEV || typeof window === 'undefined' ? `http://${host}:8000/api` : `${window.location.origin}/api`

/**
 * Dev: Reverb on :8080. Built board: the web server passes /app/ on to
 * Reverb, so the WebSocket uses the page's own port and only one port has
 * to be reachable in the classroom network.
 */
const pagePort = typeof window === 'undefined' ? '' : window.location.port || (window.location.protocol === 'https:' ? '443' : '80')
const defaultReverbPort = env.DEV || pagePort === '' ? '8080' : pagePort

export const config = {
  apiUrl: (env.VITE_API_URL as string | undefined) || defaultApiUrl,
  reverb: {
    key: (env.VITE_REVERB_APP_KEY as string | undefined) || '',
    host: (env.VITE_REVERB_HOST as string | undefined) || host,
    port: Number(env.VITE_REVERB_PORT || defaultReverbPort),
    scheme: (env.VITE_REVERB_SCHEME as string | undefined) || 'http',
  },
  /** Name shown to the teacher when pairing, e.g. "214-xona". */
  boardName: (env.VITE_BOARD_NAME as string | undefined) || null,
  /** Safety net in case a websocket message is missed. */
  pollIntervalMs: 30_000,
  /** Without the WebSocket the board follows the phone by asking this often. */
  fallbackPollMs: 3_000,
  /** How long the random "drum" spins before the name is shown. */
  drawAnimationMs: 1800,
}
