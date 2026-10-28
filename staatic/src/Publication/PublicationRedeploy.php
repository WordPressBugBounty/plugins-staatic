<?php

declare(strict_types=1);

namespace Staatic\WordPress\Publication;

final class PublicationRedeploy
{
    /**
     * Creates and claims a publication that deploys the build of $source again.
     *
     * @param array<string, mixed> $metadata Extra publication metadata.
     * @return Publication|null Null when it could not be claimed; it is then cancelled.
     */
    public static function claim(
        PublicationManagerInterface $publicationManager,
        Publication $source,
        array $metadata = []
    ): ?Publication
    {
        $publication = $publicationManager->createPublication(array_filter([
            'sourcePublicationId' => $source->id(),
            'changeEventsSince' => $source->metadataByKey('changeEventsSince')
        ], static function ($value) {
    return $value !== null;
}) + $metadata, $source->build(), null, $source->isPreview());
        if (!$publicationManager->claimPublication($publication)) {
            $publicationManager->cancelPublication($publication);

            return null;
        }

        return $publication;
    }
}
