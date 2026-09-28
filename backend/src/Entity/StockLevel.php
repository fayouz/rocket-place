<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\StockLevelRepository;
use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Whether a place tracks a stock item, and its current level (ok/low/empty), set by hand or by Rocket Clean during a cleaning.
 * One row = one tracked article for one place; deleting the row means the place no longer tracks that article.
 */
#[ORM\Entity(repositoryClass: StockLevelRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_stock_level_place_item', columns: ['place_id', 'item_id'])]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/stock-levels'),
        new Get(uriTemplate: '/stock-levels/{id}'),
        new Post(uriTemplate: '/stock-levels', security: "is_granted('PLACE_READ')"),
        new Patch(uriTemplate: '/stock-levels/{id}', security: "is_granted('PLACE_READ')"),
        new Delete(uriTemplate: '/stock-levels/{id}', security: "is_granted('PLACE_READ')"),
    ],
    normalizationContext: ['groups' => ['stock_level:read', 'tracking']],
    denormalizationContext: ['groups' => ['stock_level:write']],
    security: "is_granted('PLACE_READ')",
    paginationEnabled: false,
)]
#[ApiFilter(SearchFilter::class, properties: ['place' => 'exact', 'item' => 'exact'])]
class StockLevel
{
    public const LEVELS = ['ok', 'low', 'empty'];

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['stock_level:read'])]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'place_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['stock_level:read', 'stock_level:write'])]
    #[ApiProperty(readableLink: false)] // always an IRI, never embedded (the tracking group would otherwise embed it)
    private Place $place;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'item_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['stock_level:read', 'stock_level:write'])]
    #[ApiProperty(readableLink: false)] // always an IRI, never embedded (the tracking group would otherwise embed it)
    private StockItem $item;

    #[ORM\Column(length: 8)]
    #[Assert\Choice(choices: self::LEVELS)]
    #[Groups(['stock_level:read', 'stock_level:write'])]
    private string $level = 'ok';

    use TrackedTrait;

    public function __construct(Place $place, StockItem $item, string $level = 'ok')
    {
        $this->id = Uuid::v7();
        $this->place = $place;
        $this->item = $item;
        $this->level = $level;
    }

    public function getId(): Uuid { return $this->id; }
    public function getPlace(): Place { return $this->place; }
    public function getItem(): StockItem { return $this->item; }
    public function getLevel(): string { return $this->level; }
    public function setLevel(string $level): static { $this->level = $level; return $this; }
}
