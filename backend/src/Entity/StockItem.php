<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\StockItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A catalogue article of consumable/equipment (global, shared by every place): a name, an optional Amazon ASIN
 * (pre-filled cart link), a reorder quantity and whether it is on subscription (excluded from the cart). Whether a
 * given place tracks it is a separate App\Entity\StockLevel row.
 */
#[ORM\Entity(repositoryClass: StockItemRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/stock-items'),
        new Get(uriTemplate: '/stock-items/{id}'),
        new Post(uriTemplate: '/stock-items', security: "is_granted('PLACE_MANAGE')"),
        new Patch(uriTemplate: '/stock-items/{id}', security: "is_granted('PLACE_MANAGE')"),
        new Delete(uriTemplate: '/stock-items/{id}', security: "is_granted('PLACE_MANAGE')"),
    ],
    normalizationContext: ['groups' => ['stock_item:read', 'tracking']],
    denormalizationContext: ['groups' => ['stock_item:write']],
    security: "is_granted('PLACE_READ')",
    order: ['name' => 'ASC'],
    paginationEnabled: false,
)]
class StockItem
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['stock_item:read'])]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['stock_item:read', 'stock_item:write'])]
    private string $name = '';

    #[ORM\Column(length: 32, nullable: true)]
    #[Groups(['stock_item:read', 'stock_item:write'])]
    private ?string $asin = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Groups(['stock_item:read', 'stock_item:write'])]
    private int $reorderQty = 1;

    #[ORM\Column]
    #[Groups(['stock_item:read', 'stock_item:write'])]
    private bool $subscription = false;

    use TrackedTrait;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getAsin(): ?string { return $this->asin; }
    public function setAsin(?string $asin): static { $this->asin = null === $asin ? null : trim($asin); return $this; }
    public function getReorderQty(): int { return $this->reorderQty; }
    public function setReorderQty(int $qty): static { $this->reorderQty = max(0, $qty); return $this; }
    public function isSubscription(): bool { return $this->subscription; }
    public function setSubscription(bool $subscription): static { $this->subscription = $subscription; return $this; }
}
