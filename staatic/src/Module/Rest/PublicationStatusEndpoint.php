<?php

declare(strict_types=1);

namespace Staatic\WordPress\Module\Rest;

use DateTime;
use Staatic\WordPress\Module\ModuleInterface;
use Staatic\WordPress\Publication\Publication;
use Staatic\WordPress\Publication\PublicationRepository;
use Staatic\WordPress\Publication\PublicationTaskProvider;
use Staatic\WordPress\Service\Formatter;
use WP_Error;
use WP_REST_Request;

final class PublicationStatusEndpoint implements ModuleInterface
{
    /**
     * @var PublicationRepository
     */
    private $publicationRepository;

    /**
     * @var PublicationTaskProvider
     */
    private $publicationTaskProvider;

    /**
     * @var Formatter
     */
    private $formatter;

    public const NAMESPACE = 'staatic/v1';

    public const ENDPOINT = '/publication-status';

    public function __construct(
        PublicationRepository $publicationRepository,
        PublicationTaskProvider $publicationTaskProvider,
        Formatter $formatter
    )
    {
        $this->publicationRepository = $publicationRepository;
        $this->publicationTaskProvider = $publicationTaskProvider;
        $this->formatter = $formatter;
    }

    public function hooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, self::ENDPOINT, [[
            'methods' => 'POST',
            'callback' => [$this, 'render'],
            'permission_callback' => [$this, 'permissionCallback'],
            'args' => []
        ]]);
    }

    /**
     * @param WP_REST_Request $request
     */
    public function render($request)
    {
        $params = json_decode($request->get_body(), \true);
        $publicationId = $params['id'] ?? null;
        if (!$publicationId) {
            return new WP_Error('staatic', __('Invalid request', 'staatic'), [
                'status' => 400
            ]);
        }
        $publication = $this->publicationRepository->find($publicationId);
        if (!$publication) {
            wp_send_json_error();
        }
        $currentTask = $publication->currentTask() ? $this->publicationTaskProvider->getTask(
            $publication->currentTask()
        ) : null;

        return rest_ensure_response([
            'publication' => [
                'id' => $publication->id(),
                'isPreview' => $publication->isPreview(),
                'isMaybeStuck' => $this->determineIfMaybeStuck($publication),
                'status' => $publication->status()->status(),
                'currentTask' => $currentTask ? [
                    'name' => $currentTask::name(),
                    'description' => $currentTask->description()
                ] : null,
                'publisher' => $publication->publisher() ? $publication->publisher()->data->display_name : null
            ],
            'progress' => $this->progress($publication)
        ]);
    }

    /** @return array<string, mixed>
     * @param Publication $publication */
    public function progress($publication): array
    {
        $build = $publication->build();
        $deployment = $publication->deployment();

        return [
            'numUrlsCrawlable' => $this->formatter->number($build->numUrlsCrawlable()),
            'numUrlsCrawled' => $this->formatter->number($build->numUrlsCrawled()),
            'crawlPercent' => $this->percentage($build->numUrlsCrawled(), $build->numUrlsCrawlable()),
            'numFilesRegistered' => $this->numFilesRegistered($publication),
            'numFilesRegisteredCount' => $this->numFilesRegisteredCount($publication),
            'numResultsDeployable' => $this->formatter->number($deployment->numResultsDeployable()),
            'numResultsDeployed' => $this->formatter->number($deployment->numResultsDeployed()),
            'deployPercent' => $this->percentage(
                $deployment->numResultsDeployed(),
                $deployment->numResultsDeployable()
            ),
            'dateDeploymentFinished' => $this->formatter->shortDate($deployment->dateFinished()),
            'timeTaken' => $publication->status()->isFinished() ? $this->formatter->difference(
                $build->dateCrawlStarted(),
                $deployment->dateFinished()
            ) : null
        ];
    }

    /**
     * The crawl and deploy totals are sampled while the work they describe is still running, so
     * a counter can briefly read past its total. Clamping here keeps the progress bar inside its
     * track rather than letting it render wider than 100%.
     */
    private function percentage(int $done, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(min($done / $total, 1) * 100, 2);
    }

    /**
     * The number of media files Uploads Sync registered straight from the uploads directory.
     * They are published without ever being crawled, so they are reported next to the crawl
     * progress rather than counted in it. Null whenever the option is off, which is what keeps
     * the status area unchanged for every publication that does not use it.
     */
    private function numFilesRegistered(Publication $publication): ?string
    {
        $count = $this->numFilesRegisteredCount($publication);

        return $count === null ? null : $this->formatter->number($count);
    }

    /**
     * The same figure unformatted. The status bar needs a number rather than a display string:
     * it decides whether to render the line at all, and picks singular or plural, and "0" is a
     * truthy string while a thousands separator makes the value unparseable in the browser's
     * locale-independent way.
     */
    private function numFilesRegisteredCount(Publication $publication): ?int
    {
        $state = $publication->metadataByKey('uploadsIndexState');
        if (!is_array($state) || !isset($state['filesRegistered'])) {
            return null;
        }

        return (int) $state['filesRegistered'];
    }

    private function determineIfMaybeStuck(Publication $publication): bool
    {
        if (!$publication->status()->isInProgress()) {
            return \false;
        }
        if ($publication->currentTask()) {
            return \false;
        }
        $diffInSeconds = (new DateTime())->getTimestamp() - $publication->dateCreated()->getTimestamp();

        return $diffInSeconds > 30;
    }

    /**
     * @param WP_REST_Request $request
     */
    public function permissionCallback($request)
    {
        return current_user_can('staatic_publish');
    }
}
