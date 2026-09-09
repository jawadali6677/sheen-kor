import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverb = window.chatReverb;

if (reverb?.key) {
    const useTls = reverb.scheme === 'https';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverb.key,
        wsHost: reverb.host,
        wsPort: reverb.port,
        wssPort: reverb.port,
        forceTLS: useTls,
        enabledTransports: useTls ? ['wss'] : ['ws'],
        authEndpoint: reverb.authEndpoint,
        auth: {
            headers: {
                'X-CSRF-TOKEN': reverb.csrfToken,
                Accept: 'application/json',
            },
        },
    });
}
