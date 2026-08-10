<?php

namespace App\Support;

final class Permissions
{
    public const SERVERS_VIEW = 'servers.view';

    public const SERVERS_CREATE = 'servers.create';

    public const SERVERS_UPDATE = 'servers.update';

    public const SERVERS_DELETE = 'servers.delete';

    public const WEBSITES_VIEW = 'websites.view';

    public const WEBSITES_MANAGE = 'websites.manage';

    public const FILES_VIEW = 'files.view';

    public const FILES_READ = 'files.read';

    public const FILES_WRITE = 'files.write';

    public const FILES_DELETE = 'files.delete';

    public const SERVICES_VIEW = 'services.view';

    public const SERVICES_MANAGE = 'services.manage';

    public const LOGS_VIEW = 'logs.view';

    public const DEPLOYMENTS_VIEW = 'deployments.view';

    public const DEPLOYMENTS_MANAGE = 'deployments.manage';

    public const SECURITY_MANAGE = 'security.manage';

    public const ORGANIZATION_MANAGE = 'organization.manage';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::SERVERS_VIEW,
            self::SERVERS_CREATE,
            self::SERVERS_UPDATE,
            self::SERVERS_DELETE,
            self::WEBSITES_VIEW,
            self::WEBSITES_MANAGE,
            self::FILES_VIEW,
            self::FILES_READ,
            self::FILES_WRITE,
            self::FILES_DELETE,
            self::SERVICES_VIEW,
            self::SERVICES_MANAGE,
            self::LOGS_VIEW,
            self::DEPLOYMENTS_VIEW,
            self::DEPLOYMENTS_MANAGE,
            self::SECURITY_MANAGE,
            self::ORGANIZATION_MANAGE,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function roleMap(): array
    {
        return [
            'Super Admin' => self::all(),
            'Admin' => self::all(),
            'Server Manager' => [
                self::SERVERS_VIEW,
                self::SERVERS_CREATE,
                self::SERVERS_UPDATE,
                self::SERVERS_DELETE,
                self::WEBSITES_VIEW,
                self::WEBSITES_MANAGE,
                self::FILES_VIEW,
                self::FILES_READ,
                self::FILES_WRITE,
                self::FILES_DELETE,
                self::SERVICES_VIEW,
                self::SERVICES_MANAGE,
                self::LOGS_VIEW,
            ],
            'Developer' => [
                self::SERVERS_VIEW,
                self::WEBSITES_VIEW,
                self::WEBSITES_MANAGE,
                self::FILES_VIEW,
                self::FILES_READ,
                self::FILES_WRITE,
                self::LOGS_VIEW,
                self::DEPLOYMENTS_VIEW,
                self::DEPLOYMENTS_MANAGE,
            ],
            'Viewer' => [
                self::SERVERS_VIEW,
                self::WEBSITES_VIEW,
                self::FILES_VIEW,
                self::FILES_READ,
                self::SERVICES_VIEW,
                self::LOGS_VIEW,
                self::DEPLOYMENTS_VIEW,
            ],
        ];
    }
}
