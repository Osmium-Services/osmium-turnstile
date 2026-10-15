<?php

declare(strict_types=1);

namespace Osmium\Services\Turnstile\Models;

/**
 * The markup a form gets for its Turnstile widget. Pure: no config, no request.
 *
 * With the invisible widget the token only exists a moment after the page loads, and a form posted before then is
 * refused for having no token. So the form's submit buttons wait until Turnstile has the token, and are released
 * after a few seconds regardless (an ad blocker or an outage must not trap the visitor: the form's own checks
 * still apply).
 */
class TurnstileWidget
{
    public const RELEASE_AFTER_MS = 10000;

    /**
     * @param string $callbackId Letters and digits only, unique on the page (several forms may each have a widget)
     */
    public static function html(string $siteKey, string $callbackId, string $nonce): string
    {
        $key = \htmlspecialchars($siteKey, ENT_QUOTES);
        $id = \preg_replace('/[^A-Za-z0-9]/', '', $callbackId);
        $nonce = \htmlspecialchars($nonce, ENT_QUOTES);
        $release = self::RELEASE_AFTER_MS;

        return <<<HTML
<div class="cf-turnstile" data-sitekey="{$key}" data-callback="turnstileDone{$id}" data-error-callback="turnstileDone{$id}" data-expired-callback="turnstileWait{$id}"></div>
<script nonce="{$nonce}">
(function () {
    var widget = document.currentScript.previousElementSibling;
    var form = widget ? widget.closest('form') : null;
    if (!form) return;
    var answered = false;
    function set(waiting) { // The buttons are looked up each time: this runs before the form's own buttons are parsed
        var buttons = form.querySelectorAll('[type="submit"]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].disabled = waiting;
            buttons[i].style.opacity = waiting ? '0.6' : '';
            buttons[i].style.cursor = waiting ? 'wait' : '';
        }
    }
    window.turnstileDone{$id} = function () { answered = true; set(false); }; // A token (or an error: the form's own checks then decide)
    window.turnstileWait{$id} = function () { set(true); }; // The token expired and is being renewed
    function hold() { if (!answered) set(true); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', hold); else hold();
    setTimeout(function () { answered = true; set(false); }, {$release});
})();
</script>
HTML;
    }
}
