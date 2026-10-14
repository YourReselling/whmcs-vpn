<?php

declare(strict_types=1);

if (! defined('WHMCS')) {
    exit('Direct access is not permitted.');
}

require_once __DIR__.'/bootstrap.php';

use YourReselling\Whmcs\Vpn\Integration\WhmcsModule;

/** VPN: MetaData. */
function yourresellingvpn_MetaData(): array
{
    return ['DisplayName' => 'Your-Reselling · VPN', 'APIVersion' => '1.1', 'RequiresServer' => true];
}

/** VPN: ConfigOptions. */
function yourresellingvpn_ConfigOptions(): array
{
    return WhmcsModule::configOptions('vpn');
}

/** VPN: CreateAccount. */
function yourresellingvpn_CreateAccount(array $params): string
{
    return WhmcsModule::run('vpn', 'create', $params);
}

/** VPN: TestConnection. */
function yourresellingvpn_TestConnection(array $params): array
{
    return WhmcsModule::testConnection($params);
}

/** VPN: ClientArea. */
function yourresellingvpn_ClientArea(array $params): array
{
    return WhmcsModule::clientArea('vpn', $params);
}

/** VPN: AdminCustomButtonArray. */
function yourresellingvpn_AdminCustomButtonArray(): array
{
    return WhmcsModule::adminButtons('vpn');
}

/** VPN: RefreshStatus. */
function yourresellingvpn_RefreshStatus(array $params): string
{
    return WhmcsModule::run('vpn', 'refresh', $params);
}

/** VPN: TerminateAccount. */
function yourresellingvpn_TerminateAccount(array $params): string
{
    return WhmcsModule::run('vpn', 'termination', $params);
}

/** VPN: SuspendAccount. */
function yourresellingvpn_SuspendAccount(array $params): string
{
    return WhmcsModule::run('vpn', 'suspend', $params);
}

/** VPN: UnsuspendAccount. */
function yourresellingvpn_UnsuspendAccount(array $params): string
{
    return WhmcsModule::run('vpn', 'unsuspend', $params);
}
