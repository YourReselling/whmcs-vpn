<?php

declare(strict_types=1);

namespace YourReselling\Whmcs\Vpn\Integration;

use YourReselling\Whmcs\Vpn\ApiException;

/** Resumable provisioning. Ambiguous network failures block automatic duplicate purchases. */
final class Provisioner
{
    public function __construct(private readonly ServiceGateway $gateway, private readonly StateStore $store, private readonly ?\Closure $protectCredentials = null) {}

    /** @param array<string,mixed> $body @param array<string,string|int> $path */
    public function create(array $body, array $path): string
    {
        return $this->store->locked(function () use ($body, $path): string {
            $state = $this->store->read();
            if (! empty($state['terminated'])) {
                throw new PanelException('Dieser Dienst wurde beendet. Für eine neue Bestellung einen neuen Dienst anlegen.');
            }
            if (empty($state['id'])) {
                if (! empty($state['creating'])) {
                    throw new PanelException('Bestellstatus unklar. Im Anbieterpanel abgleichen und eine vorhandene Produkt-ID administrativ zuordnen; keine automatische Neubestellung.');
                }
                $this->gateway->validate($this->gateway->profile['create'], $body, $path);
                $state['creating'] = true;
                $state['path'] = $path;
                $this->store->write($state);
                try {
                    $created = $this->gateway->create($body, $path);
                } catch (ApiException $error) {
                    if (in_array($error->status, [400, 401, 403, 404, 422, 429], true)) {
                        $state['creating'] = false;
                        $this->store->write($state);
                    }
                    throw $error;
                }
                $state['id'] = $created['id'];
                $state['creating'] = false;
                $this->store->write($state);
                if ($this->protectCredentials !== null) {
                    $credentials = Secrets::extract($created['response']);
                    if ($credentials !== []) {
                        $state['credentials'] = ($this->protectCredentials)(json_encode($credentials, JSON_THROW_ON_ERROR));
                        $this->store->write($state);
                    }
                }
            }
            $remote = $this->gateway->show((string) $state['id'], $state['path'] ?? $path);
            $status = strtolower((string) ($remote['status'] ?? ''));
            $active = $this->gateway->profile['slug'] === 'mailbox' ? ($remote['enabled'] ?? false) === true : in_array($status, $this->gateway->profile['activeStatuses'], true);
            if (! $active) {
                throw new PanelException('Produkt-ID '.$state['id'].' gespeichert; Bereitstellung noch nicht bestätigt (Status: '.($status ?: 'unbekannt').'). Erneutes Anlegen prüft diesen Dienst, ohne neu zu bestellen.');
            }

            return (string) $state['id'];
        });
    }

    public function action(string $capability): void
    {
        $this->store->locked(function () use ($capability): void {
            $state = $this->store->read();
            $id = (string) ($state['id'] ?? '');
            if ($id === '') {
                throw new PanelException('Keine Anbieter-Produkt-ID für diesen Dienst gespeichert.');
            }
            if ($capability === 'termination' && ! empty($state['terminated'])) {
                return;
            }
            if ($capability !== 'termination' || empty($state['termination_requested'])) {
                $response = $this->gateway->lifecycle($capability, $id);
                if ($capability === 'termination') {
                    $state['termination_requested'] = true;
                    $state['termination_task'] = Catalog::value($response, 'data.task.id');
                    $this->store->write($state);
                }
            }
            if ($capability === 'termination') {
                if ($this->gateway->profile['slug'] === 'rootserver') {
                    if (empty($state['termination_task'])) {
                        throw new PanelException('Kündigungsauftrag angenommen, aber keine Task-ID erhalten. Anbieterstatus administrativ prüfen.');
                    }
                    $task = $this->gateway->task((string) $state['termination_task']);
                    if (($task['status'] ?? '') !== 'completed') {
                        throw new PanelException('Kündigungsauftrag noch nicht abgeschlossen (Status: '.($task['status'] ?? 'unbekannt').'). Erneutes Ausführen prüft denselben Task.');
                    }
                    $state['scheduled_deletion_at'] = Catalog::value($task, 'result.scheduled_deletion_at');
                }
                // Accepted cancellation is final for billing-panel automation; physical cleanup may be delayed.
                $state['terminated'] = true;
                $this->store->write($state);
            } else {
                $remote = $this->gateway->show($id, $state['path'] ?? []);
                $expected = $capability === 'suspend' ? 'suspended' : 'active';
                if (($remote['status'] ?? '') !== $expected) {
                    throw new PanelException('Die Zustandsänderung wurde vom Anbieter noch nicht bestätigt. Bitte den Status erneut prüfen.');
                }
            }
        });
    }
}
