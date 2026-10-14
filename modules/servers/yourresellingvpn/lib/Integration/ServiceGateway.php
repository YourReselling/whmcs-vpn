<?php

declare(strict_types=1);

namespace YourReselling\Whmcs\Vpn\Integration;

use YourReselling\Whmcs\Vpn\ApiException;
use YourReselling\Whmcs\Vpn\Transport;

/** Executes only verified profile operations and pins every service action to its stored remote ID. */
final class ServiceGateway
{
    /** @param array<string,mixed> $profile */
    public function __construct(private readonly Transport $transport, public readonly array $profile) {}

    /** @param array<string,mixed> $body @param array<string,string|int> $path @return array<string,mixed> */
    public function create(array $body, array $path): array
    {
        $this->validate($this->profile['create'], $body, $path);
        $json = $this->call($this->profile['create'], $path, $body);
        $id = Catalog::value($json, $this->profile['create']['response_id_path']);
        if (! is_string($id) && ! is_int($id)) {
            throw new PanelException('Die Bestellung wurde beantwortet, aber ohne Produkt-ID. Vor erneutem Bestellen im Anbieterpanel abgleichen.');
        }

        return ['id' => (string) $id, 'response' => $json];
    }

    /** @return array<string,mixed> */
    public function show(string $id, array $context = []): array
    {
        $operation = $this->profile['show'];
        $mailbox = $this->profile['slug'] === 'mailbox';
        $json = $this->call($operation, $mailbox ? $context : $this->resourcePath($operation, $id));
        $data = Catalog::value($json, $this->profile['showDataPath']);
        if ($mailbox && is_array($data)) {
            foreach ($data as $entry) {
                if (is_array($entry) && (string) ($entry['id'] ?? '') === $id) {
                    return $entry;
                }
            }
            throw new ApiException('Postfach nicht gefunden.', 404);
        }
        if (! is_array($data)) {
            throw new PanelException('Ungültige Produktantwort.');
        }

        return $data;
    }

    /** @return array<string,mixed> */
    public function task(string $id): array
    {
        if ($this->profile['slug'] !== 'rootserver') {
            throw new \LogicException('Task-Prüfung ist nur für Rootserver vorgesehen.');
        }
        $json = $this->transport->send('GET', '/products/rootserver/tasks/{task}', ['task' => $id])->json();

        return Catalog::value($json, 'data.task') ?? [];
    }

    /** @return array<string,mixed> */
    public function lifecycle(string $capability, string $id): array
    {
        $operation = $this->profile[$capability] ?? null;
        if (! is_array($operation)) {
            throw new PanelException('Diese Aktion wird von der Produkt-API nicht unterstützt.');
        }

        return $this->call($operation, $this->resourcePath($operation, $id), []);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function control(string $action, string $id, array $input): array
    {
        $operation = $this->profile['controls'][$action] ?? throw new \InvalidArgumentException('Aktion nicht freigegeben.');
        $body = array_intersect_key($input, $operation['body']['properties']);
        $path = $this->resourcePath($operation, $id);
        $this->validate($operation, $body, $path);

        return $this->call($operation, $path, $body);
    }

    /** @return array<string,mixed> */
    public function view(string $name, string $id): array
    {
        $operation = $this->profile['views'][$name] ?? throw new \InvalidArgumentException('Ansicht nicht freigegeben.');

        return $this->call($operation, $this->resourcePath($operation, $id));
    }

    /** @param array<string,mixed> $operation @param array<string,mixed> $body @param array<string,mixed> $path */
    public function validate(array $operation, array $body, array $path): void
    {
        foreach ($operation['pathParameters'] as $parameter) {
            if (! isset($path[$parameter['name']]) || (string) $path[$parameter['name']] === '') {
                throw new \InvalidArgumentException('Konfiguration fehlt: '.$parameter['name']);
            }
        }
        foreach ($operation['body']['required'] ?? [] as $key) {
            if (! array_key_exists($key, $body) || $body[$key] === null || $body[$key] === '') {
                throw new \InvalidArgumentException('Pflichtfeld fehlt: '.$key);
            }
        }
    }

    /** @param array<string,mixed> $operation @return array<string,string> */
    private function resourcePath(array $operation, string $id): array
    {
        if (count($operation['pathParameters']) !== 1) {
            throw new \LogicException('Produktaktion benötigt einen eindeutig zugeordneten Ressourcenpfad.');
        }

        return [$operation['pathParameters'][0]['name'] => $id];
    }

    /** @param array<string,mixed> $operation @param array<string,mixed> $path @param array<string,mixed>|null $body @return array<string,mixed> */
    private function call(array $operation, array $path, ?array $body = null): array
    {
        $response = $this->transport->send($operation['method'], $operation['path'], $path, [], $body);
        $json = $response->json();
        if (! is_array($json)) {
            throw new PanelException('Die API hat kein JSON-Objekt geliefert.');
        }
        if (($json['success'] ?? true) === false) {
            throw new ApiException('Der Anbieter hat die Aktion abgelehnt.', $response->status());
        }

        return $json;
    }
}
