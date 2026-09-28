<?php

namespace App\Domotique;

use App\Homey\HomeyClient;
use App\Lock\HomeyProvider;
use App\Lock\LockCapablePluginInterface;
use App\Lock\LockProviderInterface;

/**
 * Homey Pro hub: devices of the place and their capability values. Read-only (v0.2) for the "Domotique" tab, and
 * a read-only source of smart locks ('locks.state'): Homey exposes lock/unlock and a "locked" state for its lock
 * devices, but no generic keypad-code service, so 'locks.codes' is not declared (see App\Lock\HomeyProvider).
 */
final class HomeyPlugin implements PluginInterface, LockCapablePluginInterface
{
    public function __construct(private readonly HomeyClient $homey)
    {
    }

    public function id(): string { return 'homey'; }
    public function name(): string { return 'Homey'; }
    public function description(): string { return 'Hub domotique Homey (Pro) : appareils du logement et leurs valeurs (températures, prises, capteurs…). Lecture seule.'; }
    public function icon(): string { return 'i-lucide-house-wifi'; }
    public function category(): string { return 'domotique'; }
    public function capabilities(): array { return ['locks.state']; }

    public function lockProvider(array $config): LockProviderInterface
    {
        return new HomeyProvider($this->homey, $config);
    }

    public function fields(): array
    {
        return [
            ['key' => 'homeyUrl', 'label' => 'Adresse locale du Homey', 'type' => 'url', 'placeholder' => 'http://192.168.1.20', 'help' => 'Réseau local uniquement (LAN, VPN) : jamais une adresse publique.'],
            ['key' => 'secret', 'label' => 'Clé d’API locale Homey (coffre des secrets)', 'type' => 'secret', 'secret' => true, 'defaultName' => 'homey.salon.api_key'],
        ];
    }

    public function validate(array $config, string $placeId, ?string $connectorId): array
    {
        $url = trim($config['homeyUrl'] ?? '');
        if ('' !== $url) {
            $host = parse_url($url, \PHP_URL_HOST);
            if (!\is_string($host) || !preg_match('#^https?://#', $url)) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(400, 'Adresse locale du Homey invalide.');
            }
            if (!ConnectorSecrets::isPrivateHost($host)) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(400, 'L’adresse doit être sur le réseau local (192.168.x.x, 10.x.x.x, .local…).');
            }
        }
        if ('' !== ConnectorSecrets::nameIn($config) && !ConnectorSecrets::isValidName(ConnectorSecrets::nameIn($config))) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(400, 'Choisissez un secret du coffre (Administration → Secrets) ; les secrets propres à l’application sont interdits.');
        }

        return $config;
    }

    public function test(array $config): string
    {
        $devices = $this->homey->devices($config);

        return \sprintf('Homey joignable (%d appareil(s)).', \count($devices));
    }

    public function info(array $config): array
    {
        $devices = $this->homey->devices($config);

        return array_map(static function (array $d) {
            $items = [];
            foreach ($d['capabilities'] as $c) {
                if (null === $c['value']) {
                    continue;
                }
                $value = \is_bool($c['value']) ? ($c['value'] ? 'oui' : 'non') : (string) $c['value'];
                $items[] = ['label' => $c['title'], 'value' => $c['units'] ? $value.' '.$c['units'] : $value];
            }

            return ['title' => $d['name'], 'icon' => $d['available'] ? 'i-lucide-cpu' : 'i-lucide-unplug', 'items' => \array_slice($items, 0, 8)];
        }, \array_slice($devices, 0, 60));
    }
}
