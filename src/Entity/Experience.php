<?php

namespace App\Entity;

use App\Repository\ExperienceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * What a user wrote to a public service, imported from the open dataset.
 */
#[ORM\Entity(repositoryClass: ExperienceRepository::class)]
class Experience
{
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
}
