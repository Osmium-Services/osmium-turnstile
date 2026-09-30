<?php

declare(strict_types=1);

namespace Osmium\Services\Turnstile\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\Turnstile\Models\TurnstileConfig;

/**
 * Cloudflare Turnstile settings controller - full-page form POST/redirect,
 * same shape as AnalyticsController.
 *
 * Routes:
 *   - index() → /admin/settings/turnstile/  (GET shows the form, POST saves it)
 */
class TurnstileController extends AdminController
{
    private const CONFIG_FILE_PATH = 'app/config/services/turnstile.json.php';
    private const DEFAULT_CONFIG = <<<'JSON'
        <?php exit(); ?>
        {
            "turnstile": {
                "enabled": false,
                "siteKey": "",
                "secretKey": "",
                "mode": "managed"
            }
        }
        JSON;

    public function index(): void
    {
        $isPost = $this->isPost();
        if ($isPost) $this->handleSubmit();

        $this->data['admin']['config']['turnstile'] = (array) TurnstileConfig::get();
        $this->data['admin']['settingsSaved'] = $_SESSION['turnstile_settings_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['turnstile_settings_error'] ?? false;
        unset($_SESSION['turnstile_settings_saved'], $_SESSION['turnstile_settings_error']);

        $this->setView('turnstile/index.phtml');
    }

    private function handleSubmit(): void
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) {
            $_SESSION['turnstile_settings_error'] = 'Invalid form submission. Please try again.';
            $this->redirect('settings/turnstile/');
        }

        $enabled = isset($_POST['enabled']);
        $siteKey = \trim($_POST['site_key'] ?? '');
        $secretKey = \trim($_POST['secret_key'] ?? '');

        $mode = $_POST['mode'] ?? 'managed';
        $modeValid = \in_array($mode, ['managed', 'non-interactive', 'invisible'], true);
        if (!$modeValid) $mode = 'managed';

        $this->saveConfig($enabled, $siteKey, $secretKey, $mode);

        $this->admin->model->changelog->log(
            description: 'Updated Turnstile settings',
            recordType: 'settings',
        );

        TurnstileConfig::clearCache();

        $_SESSION['turnstile_settings_saved'] = true;
        $this->redirect('settings/turnstile/');
    }

    private function saveConfig(bool $enabled, string $siteKey, string $secretKey, string $mode): void
    {
        $configExists = \file_exists(self::CONFIG_FILE_PATH);
        if (!$configExists) $this->ensureConfigDirectoryExists();

        $newJson = \json_encode(
            value: ['turnstile' => [
                'enabled' => $enabled,
                'siteKey' => $siteKey,
                'secretKey' => $secretKey,
                'mode' => $mode,
            ]],
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        \file_put_contents(self::CONFIG_FILE_PATH, "<?php exit(); ?>\n" . $newJson . "\n");
    }

    private function ensureConfigDirectoryExists(): void
    {
        $dir = \dirname(self::CONFIG_FILE_PATH);
        $alreadyExists = \is_dir($dir);
        if (!$alreadyExists) \mkdir(directory: $dir, permissions: 0755, recursive: true);
    }

    private function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}
