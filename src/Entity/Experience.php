<?php

namespace App\Entity;

use App\Repository\ExperienceRepository;
use App\Triage\Intention;
use App\Triage\TriageResult;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * What a user wrote to a public service, imported from the open dataset, along
 * with what Jev made of it once it has been triaged.
 */
#[ORM\Entity(repositoryClass: ExperienceRepository::class)]
// The inbox lists the most recent experiences first: without it, every page sorts the whole table
#[ORM\Index(name: 'experience_recent_first_idx', fields: ['writtenAt', 'id'])]
// The progress of the triage is counted every second, over the requested experiences only
#[ORM\Index(name: 'experience_triage_requested_idx', fields: ['triageRequestedAt'])]
class Experience
{
    #[ORM\Column(nullable: true, enumType: Intention::class)]
    private ?Intention $intention = null;

    #[ORM\Column(nullable: true)]
    private ?float $intentionConfidence = null;

    #[ORM\Column(nullable: true)]
    private ?float $priority = null;

    #[ORM\Column(nullable: true)]
    private ?float $bugProbability = null;

    #[ORM\Column(nullable: true)]
    private ?int $triageTokens = null;

    #[ORM\Column(nullable: true)]
    private ?int $triageDurationMs = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $triageRequestedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $triagedAt = null;

    public function __construct(
        // The identifier comes from the dataset, which makes the import repeatable
        #[ORM\Id]
        #[ORM\Column]
        private int $id,

        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $writtenAt,

        #[ORM\Column(type: Types::TEXT)]
        private string $text,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getWrittenAt(): \DateTimeImmutable
    {
        return $this->writtenAt;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function requestTriage(\DateTimeImmutable $now): void
    {
        $this->triageRequestedAt = $now;
    }

    /**
     * Whether a triage was asked for and is still to be done.
     */
    public function awaitsTriage(): bool
    {
        return null !== $this->triageRequestedAt && null === $this->triagedAt;
    }

    public function isTriaged(): bool
    {
        return null !== $this->triagedAt;
    }

    public function triage(TriageResult $result, \DateTimeImmutable $now): void
    {
        $this->intention = $result->intention;
        $this->intentionConfidence = $result->intentionConfidence;
        $this->priority = $result->priority;
        $this->bugProbability = $result->bugProbability;
        $this->triageTokens = $result->tokens;
        $this->triageDurationMs = $result->durationMs;
        $this->triagedAt = $now;
    }

    public function getIntention(): ?Intention
    {
        return $this->intention;
    }

    public function getIntentionConfidence(): ?float
    {
        return $this->intentionConfidence;
    }

    public function getPriority(): ?float
    {
        return $this->priority;
    }

    public function getBugProbability(): ?float
    {
        return $this->bugProbability;
    }

    public function getTriageDurationMs(): ?int
    {
        return $this->triageDurationMs;
    }

    public function getTriagedAt(): ?\DateTimeImmutable
    {
        return $this->triagedAt;
    }
}
