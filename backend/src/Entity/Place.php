<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\PlaceRepository;
use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A place (logement, local, terrain...) usable without any PMS: locks, connectors/plugins, documents and stock
 * are all attached to a place. Created directly through the API (POST /api/places), renamable/movable here.
 */
#[ORM\Entity(repositoryClass: PlaceRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(uriTemplate: '/places'),
        new Get(uriTemplate: '/places/{id}'),
        new Post(uriTemplate: '/places', security: "is_granted('PLACE_MANAGE')"),
        new Patch(uriTemplate: '/places/{id}', security: "is_granted('PLACE_MANAGE')"),
    ],
    normalizationContext: ['groups' => ['place:read', 'tracking']],
    denormalizationContext: ['groups' => ['place:write']],
    security: "is_granted('PLACE_READ')",
    order: ['name' => 'ASC'],
    paginationEnabled: false,
)]
class Place
{
    /** Colour marker shown next to the place name everywhere ('' = none). */
    public const COLORS = ['', 'red', 'orange', 'amber', 'green', 'teal', 'blue', 'violet', 'pink'];

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['place:read'])]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['place:read', 'place:write'])]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['place:read', 'place:write'])]
    private ?string $address = null;

    #[ORM\Column(length: 16)]
    #[Assert\Choice(choices: self::COLORS, message: 'place.color')]
    #[Groups(['place:read', 'place:write'])]
    private string $color = '';

    #[ORM\Column(nullable: true)]
    #[Groups(['place:read', 'place:write'])]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['place:read', 'place:write'])]
    private ?float $longitude = null;

    /** Folder of the place in Rocket Cloud (documents). */
    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['place:read', 'place:write'])]
    private ?string $cloudFolderId = null;

    use TrackedTrait;

    /** $id: only for fixed, well-known ids (demo places shared by every brick); a new v7 otherwise. */
    public function __construct(?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = null === $address ? null : trim($address); return $this; }
    public function getColor(): string { return $this->color; }
    public function setColor(string $color): static { $this->color = $color; return $this; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function setCoordinates(?float $latitude, ?float $longitude): static { $this->latitude = $latitude; $this->longitude = $longitude; return $this; }
    public function getCloudFolderId(): ?string { return $this->cloudFolderId; }
    public function setCloudFolderId(?string $id): static { $this->cloudFolderId = $id; return $this; }
}
