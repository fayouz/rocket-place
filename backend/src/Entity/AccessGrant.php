<?php

namespace App\Entity;

use App\Repository\AccessGrantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Rocket\Core\Entity\TrackedTrait;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * A time-boxed access to a place's lock: planned in the database (App\Code\AccessGrantService::plan), then sent to
 * the lock only on a user's explicit click (::send). `externalRef` is an optional free-text reference to whatever
 * triggered the grant in a client app (e.g. a PMS booking id) — Rocket Place itself never depends on it.
 */
#[ORM\Entity(repositoryClass: AccessGrantRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_access_grant_lock_code', columns: ['lock_id', 'code'])]
#[ORM\UniqueConstraint(name: 'uniq_access_grant_place_external_ref', columns: ['place_id', 'external_ref'])]
class AccessGrant
{
    public const PLANNED = 'planned';
    public const CREATED = 'created';
    public const ERROR = 'error';
    public const REVOKED = 'revoked';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Place $place;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'lock_id', referencedColumnName: 'nuki_id', nullable: false, onDelete: 'CASCADE')]
    private SmartLock $lock;

    #[ORM\Column(length: 120)]
    private string $label;

    #[ORM\Column(length: 6)]
    private string $code;

    /** With time zone: an opening time is read back as the same instant, whatever the server's zone. */
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $validFrom;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $validUntil;

    #[ORM\Column(length: 16)]
    private string $status = self::PLANNED;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $externalRef = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    use TrackedTrait;

    public function __construct(Place $place, SmartLock $lock, string $label, string $code, \DateTimeImmutable $validFrom, \DateTimeImmutable $validUntil, ?string $externalRef = null)
    {
        $this->id = Uuid::v7();
        $this->place = $place;
        $this->lock = $lock;
        $this->label = $label;
        $this->code = $code;
        $this->validFrom = $validFrom;
        $this->validUntil = $validUntil;
        $this->externalRef = $externalRef;
    }

    public function getId(): Uuid { return $this->id; }
    public function getPlace(): Place { return $this->place; }
    public function getLock(): SmartLock { return $this->lock; }
    public function getLabel(): string { return $this->label; }
    public function getCode(): string { return $this->code; }
    public function getValidFrom(): \DateTimeImmutable { return $this->validFrom; }
    public function getValidUntil(): \DateTimeImmutable { return $this->validUntil; }
    public function setValidity(\DateTimeImmutable $from, \DateTimeImmutable $until): static { $this->validFrom = $from; $this->validUntil = $until; return $this; }
    public function getStatus(): string { return $this->status; }
    public function getExternalRef(): ?string { return $this->externalRef; }
    public function getError(): ?string { return $this->error; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function getRevokedAt(): ?\DateTimeImmutable { return $this->revokedAt; }

    public function markCreated(\DateTimeImmutable $at): static { $this->status = self::CREATED; $this->error = null; $this->sentAt = $at; return $this; }
    public function markError(string $error): static { $this->status = self::ERROR; $this->error = mb_substr($error, 0, 500); return $this; }
    public function markRevoked(\DateTimeImmutable $at): static { $this->status = self::REVOKED; $this->revokedAt = $at; return $this; }

    public function isActive(\DateTimeImmutable $now): bool
    {
        return self::REVOKED !== $this->status && $now >= $this->validFrom && $now <= $this->validUntil;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(), 'placeId' => $this->place->getId()->toRfc4122(), 'lockId' => $this->lock->getNukiId(),
            'label' => $this->label, 'code' => $this->code,
            'validFrom' => $this->validFrom->format(\DATE_ATOM), 'validUntil' => $this->validUntil->format(\DATE_ATOM),
            'status' => $this->status, 'externalRef' => $this->externalRef, 'error' => $this->error,
            'sentAt' => $this->sentAt?->format(\DATE_ATOM), 'revokedAt' => $this->revokedAt?->format(\DATE_ATOM),
        ];
    }
}
