<?php

declare(strict_types=1);

namespace Staatic\WordPress\Cli;

use Staatic\WordPress\Publication\Publication;
use Staatic\WordPress\Publication\PublicationRepository;
use WP_CLI;

class DeleteCommand
{
    /**
     * @var PublicationRepository
     */
    protected $publicationRepository;

    public function __construct(PublicationRepository $publicationRepository)
    {
        $this->publicationRepository = $publicationRepository;
    }

    /**
     * Deletes a publication by its ID.
     *
     * ## OPTIONS
     *
     * <id>
     * : Id of the publication.
     *
     * ## EXAMPLES
     *
     *     wp staatic delete abc-def-123
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args): void
    {
        [$publicationId] = $args;
        if (!$publication = $this->publicationRepository->find($publicationId)) {
            WP_CLI::error(sprintf(
                /* translators: 1: Publication identifier. */
                __('Unable to find publication "%1$s"', 'staatic'),
                $publicationId
            ));
        }
        if (!$this->canDeletePublication($publication)) {
            WP_CLI::error(sprintf(
                /* translators: 1: Publication identifier. */
                __('Publication "%1$s" is still current', 'staatic'),
                $publicationId
            ));
        }
        $this->publicationRepository->delete($publication);
        WP_CLI::success("Publication ({$publicationId}) successfully deleted.");
    }

    private function canDeletePublication(Publication $publication): bool
    {
        return !in_array(
            $publication->id(),
            [
                get_option('staatic_current_publication_id', null),
                get_option('staatic_latest_publication_id', null),
                get_option('staatic_active_publication_id', null),
                get_option('staatic_active_preview_publication_id', null)
            ],
            \true
        );
    }
}
