<?php

namespace App\Entity;

use App\Repository\CleaningChecklistItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/** One line of a place's cleaning checklist template, copied into every new cleaning task of that place. */
#[ORM\Entity(repositoryClass: CleaningChecklistItemRepository::class)]
class CleaningChecklistItem
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Place $place;

    #[ORM\Column(length: 160)]
    private string $label;

    #[ORM\Column]
    private int $position;

    public function __construct(Place $place, string $label, int $position)
    {
        $this->id = Uuid::v7();
        $this->place = $place;
        $this->label = $label;
        $this->position = $position;
    }

    public function getId(): Uuid { return $this->id; }
    public function getPlace(): Place { return $this->place; }
    public function getLabel(): string { return $this->label; }
    public function getPosition(): int { return $this->position; }
}
