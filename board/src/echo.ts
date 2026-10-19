import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { config } from './config'

export type BoardEcho = Echo<'reverb'>

/**
 * WebSocket connection to Laravel Reverb. The board only listens on its own
 * public channel (board.{token}), so no authentication is needed.
 */
export function createEcho(): BoardEcho {
  const tls = config.reverb.scheme === 'https'

  return new Echo({
    broadcaster: 'reverb',
    key: config.reverb.key,
    wsHost: config.reverb.host,
    wsPort: config.reverb.port,
    wssPort: config.reverb.port,
    forceTLS: tls,
    enabledTransports: tls ? ['wss'] : ['ws'],
    Pusher,
  })
}
