<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module\Admin\Page;

use Staatic\WordPress\Module\Admin\Page\Publications\PublicationsPage;
use Staatic\WordPress\Module\ModuleInterface;
use Staatic\WordPress\Publication\Publication;
use Staatic\WordPress\Publication\PublicationManager;
use Staatic\WordPress\Service\AdminNavigation;
use Staatic\WordPress\Service\PartialRenderer;
use Staatic\WordPress\Util\WordpressEnv;

final class PublishSubsetPage implements ModuleInterface
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
     * @var PublicationManager
     */
    private $publicationManager;

    /** @var string */
    public const PAGE_SLUG = 'staatic-publish-subset';

    use TriggersPublications;

    public function __construct(
        AdminNavigation $navigation,
        PartialRenderer $renderer,
        PublicationManager $publicationManager
    )
    {
        $this->navigation = $navigation;
        $this->renderer = $renderer;
        $this->publicationManager = $publicationManager;
    }

    public function hooks(): void
    {
        if (!is_admin()) {
            return;
        }
        add_action('init', [$this, 'addPage']);
    }

    public function addPage(): void
    {
        $this->navigation->addPage(
            __('Publish Selection', 'staatic'),
            self::PAGE_SLUG,
            [$this, 'render'],
            'staatic_publish_subset',
            PublicationsPage::PAGE_SLUG
        );
    }

    public function render(): void
    {
        $errors = null;
        $reviewDestination = \false;
        $isPreview = !empty($_POST['preview'] ?? $_GET['preview'] ?? null);
        $supportsPreview = (bool) apply_filters('staatic_deployment_strategy_supports_preview', \false);
        if ($isPreview && !$supportsPreview) {
            wp_die(__('The configured deployment method does not support preview publications.', 'staatic'));
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('staatic-publish-subset');
            $urls = (string) ($_POST['urls'] ?? '');
            $paths = (string) ($_POST['paths'] ?? '');
            // Without JS, a changed destination must be reviewed before publication.
            $reviewDestination = isset($_POST['update_destination']) || !isset($_POST['rendered_preview']) || (string) $_POST['rendered_preview'] !== ($isPreview ? '1' : '0');
            $errors = $reviewDestination ? null : $this->publicationManager->validateSubsetRequest($urls, $paths);
            if (!$reviewDestination && !$errors->has_errors()) {
                $deploy = !empty($_POST['deploy']);
                $this->triggerPublication(__('Publish Selection', 'staatic'), function () use (
                    $urls,
                    $paths,
                    $deploy,
                    $isPreview
                ) {
                    return $this->createPublication($urls, $paths, $deploy, $isPreview);
                });

                return;
            }
        }
        $urls = $_POST['urls'] ?? '';
        $paths = $_POST['paths'] ?? '';
        $deploy = isset($_POST['deploy']) ? (bool) $_POST['deploy'] : \true;
        $rootPath = WordpressEnv::getWordpressPath();
        $rootUrlPath = '/' . trim(WordpressEnv::getWordpressUrlPath(), '/');
        $this->renderer->render(
            'admin/publish-subset.php',
            compact('urls', 'paths', 'deploy', 'errors', 'rootPath', 'rootUrlPath', 'isPreview', 'supportsPreview', 'reviewDestination')
        );
    }

    private function createPublication(string $urls, string $paths, bool $deploy, bool $isPreview): Publication
    {
        $metadata = [
            'subset' => [
                'urls' => $urls,
                'paths' => $paths
            ]
        ];
        if (!$deploy) {
            $metadata['skipDeploy'] = \true;
        }

        return $this->publicationManager->createPublication($metadata, null, null, $isPreview);
    }
}
