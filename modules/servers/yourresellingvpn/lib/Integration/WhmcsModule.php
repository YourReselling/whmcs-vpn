<?php

declare(strict_types=1);

namespace YourReselling\Whmcs\Vpn\Integration;

use YourReselling\Whmcs\Vpn\Transport;

/** WHMCS entrypoints share transport/state handling while each product exposes its own capabilities. */
final class WhmcsModule
{
    /** @return array<string,array<string,mixed>> */
    public static function configOptions(string $slug): array
    {
        $options = [
            'API-Basis-URL' => ['Type' => 'text', 'Size' => '60', 'Default' => 'https://resellingprovider.de/api/v1', 'Description' => 'API-Key im Passwortfeld des zugeordneten WHMCS-Servers speichern.'],
            'Weitere Vorgaben (JSON)' => ['Type' => 'textarea', 'Rows' => '5', 'Cols' => '60', 'Default' => '{}', 'Description' => 'Zusätzliche Bestellfelder; siehe produktspezifische Einrichtung.'],
        ];
        foreach (Catalog::product($slug)['configFields'] as $field) {
            $option = ['Type' => in_array($field['type'] ?? '', ['array', 'object'], true) ? 'textarea' : 'text', 'Size' => '40', 'Description' => $field['name'].($field['required'] ? ' (Pflichtfeld)' : ' (optional)')];
            if (isset($field['enum'])) {
                $option['Type'] = 'dropdown';
                $option['Options'] = array_merge(['' => '—'], array_combine(array_map('strval', $field['enum']), array_map('strval', $field['enum'])));
            } elseif (($field['type'] ?? '') === 'boolean') {
                $option['Type'] = 'dropdown';
                $option['Options'] = ['' => 'Standard', 'true' => 'Ja', 'false' => 'Nein'];
            }
            $options[$field['label'].' · '.$field['name'].($field['path'] ? ' (Pfad)' : '')] = $option;
        }

        return $options;
    }

    /** @param array<string,mixed> $params */
    public static function transport(array $params): Transport
    {
        return new Transport((string) ($params['serverpassword'] ?? ''), (string) (($params['configoption1'] ?? '') ?: 'https://resellingprovider.de/api/v1'));
    }

    /** @param array<string,mixed> $params @return array{body:array<string,mixed>,path:array<string,mixed>} */
    public static function input(string $slug, array $params): array
    {
        $profile = Catalog::product($slug);
        $defaults = Input::object((string) ($params['configoption2'] ?? '{}'));
        foreach ($profile['configFields'] as $index => $field) {
            $value = $params['configoption'.($index + 3)] ?? '';
            if ($value !== '') {
                $defaults[$field['name']] = $value;
            }
        }
        $customer = [];
        foreach ($profile['customerFields'] as $name) {
            $value = $params['customfields'][$name] ?? null;
            if ($value !== null && $value !== '') {
                $customer[$name] = $value;
            }
        }
        if (! empty($params['domain'])) {
            foreach (['domain', 'hostname', 'common_name'] as $name) {
                if (in_array($name, $profile['customerFields'], true) && empty($customer[$name]) && empty($defaults[$name])) {
                    $customer[$name] = $params['domain'];
                }
            }
        }

        return Input::provision($profile, $defaults, $customer);
    }

    /** @param array<string,mixed> $params */
    private static function store(array $params): WhmcsStateStore
    {
        if (! isset($params['model']) || ! is_object($params['model']) || (int) ($params['serviceid'] ?? 0) < 1 || (int) ($params['addonid'] ?? 0) > 0) {
            throw new PanelException('Das Modul benötigt einen eigenständigen WHMCS-Produktdienst mit Service Properties.');
        }

        return new WhmcsStateStore($params['model'], (int) $params['serviceid'], (int) $params['pid']);
    }

    /** @param array<string,mixed> $params */
    public static function run(string $slug, string $action, array $params): string
    {
        try {
            $gateway = new ServiceGateway(self::transport($params), Catalog::product($slug));
            $store = self::store($params);
            $provisioner = new Provisioner($gateway, $store, static fn (string $value): string => encrypt($value));
            if (in_array($action, ['create', 'refresh'], true)) {
                if ($action === 'refresh' && empty($store->read()['id'])) {
                    throw new PanelException('Es wurde noch keine Anbieter-Produkt-ID gespeichert.');
                }
                $input = self::input($slug, $params);
                $provisioner->create($input['body'], $input['path']);
            } else {
                $provisioner->action($action);
            }

            return 'success';
        } catch (\Throwable $error) {
            return Panel::error($error, true);
        }
    }

    /** @return array<string,string> */
    public static function adminButtons(string $slug): array
    {
        Catalog::product($slug);

        return ['Bereitstellung prüfen' => 'RefreshStatus'];
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    public static function testConnection(array $params): array
    {
        try {
            self::transport($params)->send('GET', '/account')->json();

            return ['success' => true];
        } catch (\Throwable $error) {
            return ['success' => false, 'error' => Panel::error($error, true)];
        }
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    public static function clientArea(string $slug, array $params): array
    {
        $profile = Catalog::product($slug);
        $vars = ['yrTitle' => $profile['displayName'], 'yrRows' => [], 'yrSecrets' => [], 'yrControls' => [], 'yrViews' => [], 'yrError' => '', 'yrNotice' => '', 'yrView' => '', 'yrServiceId' => (int) ($params['serviceid'] ?? 0)];
        try {
            $state = self::store($params)->read();
            $id = (string) ($state['id'] ?? '');
            if ($id === '') {
                throw new PanelException('Der Dienst wird noch bereitgestellt.');
            }
            $gateway = new ServiceGateway(self::transport($params), $profile);
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['yr_action'])) {
                if (! function_exists('check_token')) {
                    throw new PanelException('Formularprüfung ist nicht verfügbar.');
                }
                check_token('WHMCS.default');
                if (($_POST['yr_confirm'] ?? '') !== '1' || ! is_string($_POST['yr_action'])) {
                    throw new \InvalidArgumentException('Bitte die Aktion bestätigen.');
                }
                if (($params['model']->status ?? $params['status'] ?? '') !== 'Active' || ! empty($state['termination_requested'])) {
                    throw new PanelException('Für diesen Dienst sind derzeit keine Aktionen verfügbar.');
                }
                $action = $_POST['yr_action'];
                if ($action === 'credentials') {
                    if (! headers_sent()) {
                        header('Cache-Control: no-store, private');
                    }
                    $vars['yrSecrets'] = isset($state['credentials']) ? json_decode(decrypt($state['credentials']), true, 512, JSON_THROW_ON_ERROR) : [];
                    $vars['yrSecrets'] = array_merge($vars['yrSecrets'], Secrets::extract($gateway->show($id, $state['path'] ?? [])));
                    if ($vars['yrSecrets'] === []) {
                        $vars['yrNotice'] = 'Für dieses Produkt liefert die API keine gespeicherten Zugangsdaten.';
                    }
                } else {
                    $operation = $profile['controls'][$action] ?? throw new \InvalidArgumentException('Aktion nicht verfügbar.');
                    $result = $gateway->control($action, $id, Panel::controlInput($operation, (array) ($_POST['yr_fields'] ?? [])));
                    $vars['yrNotice'] = 'Die Aktion wurde vom Anbieter angenommen.';
                    $vars['yrRows'] = Panel::rows($result);
                }
            }
            $view = is_string($_GET['yr_view'] ?? null) ? $_GET['yr_view'] : '';
            if ($vars['yrRows'] === []) {
                $vars['yrRows'] = Panel::rows($view !== '' ? $gateway->view($view, $id) : $gateway->show($id, $state['path'] ?? []));
            }
            $vars['yrView'] = $view;
            foreach ($profile['views'] as $key => $operation) {
                if ($operation['path'] !== $profile['show']['path']) {
                    $vars['yrViews'][] = ['key' => $key, 'label' => $operation['germanSummary'] ?: $operation['summary']];
                }
            }
            if (($params['model']->status ?? $params['status'] ?? '') === 'Active' && empty($state['termination_requested'])) {
                $vars['yrControls'][] = ['key' => 'credentials', 'label' => 'Zugangsdaten anzeigen', 'fields' => []];
                foreach ($profile['controls'] as $key => $operation) {
                    $fields = [];
                    foreach ($operation['body']['properties'] as $name => $schema) {
                        $fields[] = ['name' => $name, 'label' => str_replace('_', ' ', $name), 'type' => $schema['type'] ?? 'string', 'required' => in_array($name, $operation['body']['required'] ?? [], true), 'options' => $schema['enum'] ?? []];
                    }
                    $vars['yrControls'][] = ['key' => $key, 'label' => $operation['label'], 'fields' => $fields];
                }
            }
        } catch (\Throwable $error) {
            $vars['yrError'] = Panel::error($error);
        }

        return ['tabOverviewReplacementTemplate' => 'clientarea.tpl', 'templatefile' => 'clientarea', 'vars' => $vars];
    }
}
