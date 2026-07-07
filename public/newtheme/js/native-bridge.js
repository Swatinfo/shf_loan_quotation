// ============================================================
//  SHF Native Bridge — registers the WebView's FCM device token.
//
//  The native (Flutter) shell wraps this web app in a WebView. After it
//  obtains an FCM token it runs:
//
//      window.shfNative = { token: '...', platform: 'android' };
//      window.dispatchEvent(new Event('shf-native-ready'));
//
//  This script catches that event and POSTs { token, platform, sound } to
//  /api/device/register using the WebView's session cookie. `sound` is the
//  user's chosen chime preset (shf-chime-preset) so the server can send FCM on
//  the matching Android sound channel. In a plain browser `window.shfNative` is
//  never set, so this is a no-op.
// ============================================================
(function () {
    var REGISTER_URL = '/api/device/register';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function currentSound() {
        try {
            if (window.SHFPush && typeof window.SHFPush.getChimePreset === 'function') {
                return window.SHFPush.getChimePreset();
            }
            return localStorage.getItem('shf-chime-preset') || 'smooth';
        } catch (e) {
            return 'smooth';
        }
    }

    function register() {
        var native = window.shfNative;
        if (!native || !native.token) { return; }
        fetch(REGISTER_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                token: native.token,
                platform: native.platform || 'android',
                sound: currentSound(),
            }),
        }).catch(function () {
            // Registration is best-effort; never disrupt the page.
        });
    }

    window.addEventListener('shf-native-ready', register);

    // The shell may have fired the event before this script attached
    // (onLoadStop races page parsing) — catch up if the token is already set.
    if (window.shfNative && window.shfNative.token) { register(); }

    // Re-register when the user changes their notification sound so the server
    // starts sending on the matching channel.
    window.addEventListener('shf-chime-preset-changed', function () {
        if (window.shfNative && window.shfNative.token) { register(); }
    });
})();
