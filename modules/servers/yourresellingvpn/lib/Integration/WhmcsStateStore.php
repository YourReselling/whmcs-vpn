<?php

declare(strict_types=1);

namespace YourReselling\Whmcs\Vpn\Integration;

use WHMCS\Database\Capsule;

/** WHMCS service properties plus a MySQL advisory lock, shared across application nodes. */
final class WhmcsStateStore implements StateStore
{
    public function __construct(private object $model, private readonly int $serviceId, private readonly int $productId) {}

    public function read(): array
    {
        if (method_exists($this->model, 'fresh')) {
            $this->model = $this->model->fresh() ?? $this->model;
        }
        $raw = $this->model->serviceProperties->get('YourReselling State');
        $state = is_string($raw) && $raw !== '' ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : [];
        $id = $this->model->serviceProperties->get('YourReselling ID');
        if (is_scalar($id) && (string) $id !== '') {
            $state['id'] = (string) $id;
        }

        return $state;
    }

    public function write(array $state): void
    {
        $values = ['YourReselling State' => json_encode($state, JSON_THROW_ON_ERROR)];
        if (isset($state['id'])) {
            $values['YourReselling ID'] = $state['id'];
        }
        Capsule::connection()->transaction(function () use ($values): void {
            $this->model->serviceProperties->save($values);
            Capsule::table('tblcustomfields')->whereIn('fieldname', ['YourReselling State', 'YourReselling ID'])->where('type', 'product')->where('relid', $this->productId)->update(['adminonly' => 'on', 'showorder' => '', 'showinvoice' => '']);
        });
    }

    public function locked(\Closure $operation): mixed
    {
        $name = 'yourreselling:service:'.$this->serviceId;
        $lock = Capsule::connection()->select('SELECT GET_LOCK(?, 0) AS acquired', [$name]);
        if ((int) ($lock[0]->acquired ?? 0) !== 1) {
            throw new PanelException('Für diesen Dienst läuft bereits eine Aktion.');
        }
        try {
            return $operation();
        } finally {
            Capsule::connection()->select('SELECT RELEASE_LOCK(?)', [$name]);
        }
    }
}
