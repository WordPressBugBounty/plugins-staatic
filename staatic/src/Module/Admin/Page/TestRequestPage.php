<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module\Admin\Page;

use Staatic\WordPress\Module\ModuleInterface;
use Staatic\WordPress\Request\TestRequest;
use Staatic\WordPress\Service\AdminNavigation;
use Staatic\WordPress\Service\Formatter;
use Staatic\WordPress\Service\PartialRenderer;

final class TestRequestPage implements ModuleInterface
{
    /**
     * @var AdminNavigation
     */
    private $navigation;

    /**
     * @var PartialRenderer
     */
    private $renderer;

    /**
     * @var Formatter
     */
    private $formatter;

    use FlashesMessages;

    private const OUTCOME_DISABLED = 'disabled';

    private const OUTCOME_FAILED = 'failed';

    private const OUTCOME_DISPATCHED = 'dispatched';

    /** @var string */
    public const PAGE_SLUG = 'staatic-test-request';

    /** @var string */
    public const NONCE_ACTION = 'staatic-test-request';

    /**
     * The URL that dispatches the diagnostic.
     *
     * Loading the page is the action: it occupies a PHP worker for up to five minutes and
     * rewrites the recorded status. Every link that offers it therefore carries a nonce, which
     * load() checks before dispatching anything.
     */
    public static function dispatchUrl(): string
    {
        return wp_nonce_url(admin_url(sprintf('admin.php?page=%s', self::PAGE_SLUG)), self::NONCE_ACTION);
    }

    /**
     * @var TestRequest
     */
    private $request;

    /**
     * @var mixed[]|null
     */
    private $outcome;

    public function __construct(AdminNavigation $navigation, PartialRenderer $renderer, Formatter $formatter)
    {
        $this->navigation = $navigation;
        $this->renderer = $renderer;
        $this->formatter = $formatter;
    }

    public function hooks(): void
    {
        $this->request = new TestRequest();
        if (!is_admin()) {
            return;
        }
        add_action('init', [$this, 'addPage']);
    }

    public function addPage(): void
    {
        $this->navigation->addPage(
            __('Publication Test Task', 'staatic'),
            self::PAGE_SLUG,
            [$this, 'render'],
            'staatic_manage_settings',
            SettingsPage::PAGE_SLUG,
            [$this, 'load']
        );
    }

    public function load(): void
    {
        if ($this->outcome !== null) {
            return;
        }
        // Loading this page dispatches the diagnostic, so it is treated as the action it is
        // rather than as a read. check_admin_referer() ends the request itself on a bad nonce.
        check_admin_referer(self::NONCE_ACTION);
        $destination = $this->destinationUrl();
        if (!TestRequest::isEnabled()) {
            $this->outcome = [
                'state' => self::OUTCOME_DISABLED,
                'destination' => $destination
            ];

            return;
        }
        $result = $this->request->dispatch();
        $this->outcome = [
            'state' => $result === \false || is_wp_error($result) ? self::OUTCOME_FAILED : self::OUTCOME_DISPATCHED,
            'runtime' => TestRequest::targetRuntime(),
            'destination' => $destination
        ];
    }

    public function render(): void
    {
        if ($this->outcome === null) {
            return;
        }
        $outcome = $this->outcome;
        if ($outcome['state'] === self::OUTCOME_DISABLED) {
            $this->renderFlashMessage(
                __('Publication Test Task', 'staatic'),
                __('Publication test task is disabled by the staatic_test_request_enabled filter and was not dispatched.', 'staatic'),
                $outcome['destination']
            );

            return;
        }
        if ($outcome['state'] === self::OUTCOME_FAILED) {
            $this->renderFlashMessage(
                __('Publication Test Task', 'staatic'),
                __('Publication test task could not be dispatched. Please try again or check the available Staatic diagnostic information for related issues.', 'staatic'),
                $outcome['destination']
            );

            return;
        }
        $this->renderFlashMessage(__('Publication Test Task', 'staatic'), sprintf(
            /* translators: 1: Diagnostic runtime. */
            __('Publication test task has been dispatched and should complete after about %1$s.', 'staatic'),
            $this->formatSeconds($outcome['runtime'])
        ), $outcome['destination']);
    }

    private function destinationUrl(): string
    {
        global $wp_version;

        return version_compare($wp_version, '5.2', '>=') ? admin_url('site-health.php') : admin_url(
            sprintf('admin.php?page=%s', SettingsPage::PAGE_SLUG)
        );
    }

    private function formatSeconds(int $seconds): string
    {
        return $this->formatter->seconds($seconds);
    }
}
