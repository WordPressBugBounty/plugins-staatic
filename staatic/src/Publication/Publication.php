<?php

declare(strict_types=1);

namespace Staatic\WordPress\Publication;

use DateTimeImmutable;
use DateTimeInterface;
use Staatic\Framework\Build;
use Staatic\Framework\Deployment;
use WP_User;

final class Publication
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var DateTimeInterface
     */
    private $dateCreated;

    /**
     * @var Build
     */
    private $build;

    /**
     * @var Deployment
     */
    private $deployment;

    /**
     * @var bool
     */
    private $isPreview = \false;

    /**
     * @var int|null
     */
    private $userId;

    /**
     * @var mixed[]
     */
    private $metadata = [];

    /**
     * @var DateTimeInterface|null
     */
    private $dateFinished;

    /**
     * @var string|null
     */
    private $currentTask;

    /** @var int */
    public const TIME_LIMIT_IN_HOURS = 4;

    /** @var int */
    public const MINIMUM_TIME_LIMIT_IN_HOURS = 1;

    /** @var int */
    public const MAXIMUM_TIME_LIMIT_IN_HOURS = 72;

    /**
     * @var PublicationStatus
     */
    private $status;

    public function __construct(
        string $id,
        DateTimeInterface $dateCreated,
        Build $build,
        Deployment $deployment,
        bool $isPreview = \false,
        ?int $userId = null,
        array $metadata = [],
        PublicationStatus $status = null,
        ?DateTimeInterface $dateFinished = null,
        ?string $currentTask = null
    )
    {
        $this->id = $id;
        $this->dateCreated = $dateCreated;
        $this->build = $build;
        $this->deployment = $deployment;
        $this->isPreview = $isPreview;
        $this->userId = $userId;
        $this->metadata = $metadata;
        $this->dateFinished = $dateFinished;
        $this->currentTask = $currentTask;
        $this->status = $status ?? PublicationStatus::create(PublicationStatus::STATUS_PENDING);
    }

    /**
     * The number of hours a publication may take before it is canceled.
     *
     * The saved setting replaces the built-in default, after which the long-standing
     * staatic_publication_timeout filter is applied last so an existing filter keeps
     * overriding whatever the site has configured. The stored value is held to the
     * bounds the setting enforces, because the option can also reach the database
     * without passing through the settings screen; the filter stays unbounded, since
     * it is existing public API and sites rely on it to lift the limit.
     */
    public static function timeLimitInHours(): int
    {
        $limit = (int) get_option('staatic_publication_time_limit');
        if ($limit < self::MINIMUM_TIME_LIMIT_IN_HOURS) {
            $limit = self::TIME_LIMIT_IN_HOURS;
        } elseif ($limit > self::MAXIMUM_TIME_LIMIT_IN_HOURS) {
            $limit = self::MAXIMUM_TIME_LIMIT_IN_HOURS;
        }

        return (int) apply_filters('staatic_publication_timeout', $limit);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dateCreated(): DateTimeInterface
    {
        return $this->dateCreated;
    }

    public function build(): Build
    {
        return $this->build;
    }

    public function deployment(): Deployment
    {
        return $this->deployment;
    }

    public function isPreview(): bool
    {
        return $this->isPreview;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function publisher(): ?WP_User
    {
        if (!$this->userId) {
            return null;
        }

        return get_userdata($this->userId) ?: null;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function metadataByKey(string $key)
    {
        return $this->metadata[$key] ?? null;
    }

    public function status(): PublicationStatus
    {
        return $this->status;
    }

    public function type(): PublicationType
    {
        if (!$this->build()->parentId()) {
            return PublicationType::create(PublicationType::TYPE_FULL);
        }
        if ($this->metadataByKey('subset') !== null) {
            return PublicationType::create(PublicationType::TYPE_SUBSET);
        }

        return PublicationType::create(PublicationType::TYPE_PARTIAL);
    }

    public function dateFinished(): ?DateTimeInterface
    {
        return $this->dateFinished;
    }

    public function currentTask(): ?string
    {
        return $this->currentTask;
    }

    public function setStatus(PublicationStatus $status): void
    {
        $this->status = $status;
    }

    public function setCurrentTask(?string $currentTask): void
    {
        $this->currentTask = $currentTask;
    }

    public function markInProgress(): void
    {
        $this->status = PublicationStatus::create(PublicationStatus::STATUS_IN_PROGRESS);
    }

    public function markCanceled(): void
    {
        $this->currentTask = null;
        $this->status = PublicationStatus::create(PublicationStatus::STATUS_CANCELED);
        $this->dateFinished = new DateTimeImmutable();
    }

    public function markFailed(): void
    {
        $this->currentTask = null;
        $this->status = PublicationStatus::create(PublicationStatus::STATUS_FAILED);
        $this->dateFinished = new DateTimeImmutable();
    }

    public function markFinished(): void
    {
        $this->currentTask = null;
        $this->status = PublicationStatus::create(PublicationStatus::STATUS_FINISHED);
        $this->dateFinished = new DateTimeImmutable();
    }

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }
}
