<?php

namespace App\Enums;

enum AgentCommand: string
{
    case DiscoverServer = 'discover_server';
    case DiscoverWebsites = 'discover_websites';
    case ListFiles = 'list_files';
    case ReadFile = 'read_file';
    case WriteFile = 'write_file';
    case DeleteFile = 'delete_file';
    case CreateDirectory = 'create_directory';
    case RenameFile = 'rename_file';
    case WebsiteStart = 'website_start';
    case WebsiteStop = 'website_stop';
    case WebsiteRestart = 'website_restart';
    case WebsiteEnable = 'website_enable';
    case WebsiteDisable = 'website_disable';
    case ServiceStart = 'service_start';
    case ServiceStop = 'service_stop';
    case ServiceRestart = 'service_restart';
    case ServiceReload = 'service_reload';
    case ServiceStatus = 'service_status';
    case GetLogs = 'get_logs';
    case GetMetrics = 'get_metrics';
    case NginxTest = 'nginx_test';
    case NginxReload = 'nginx_reload';

    public function permission(): string
    {
        return match ($this) {
            self::DiscoverServer, self::DiscoverWebsites, self::GetMetrics, self::ServiceStatus => 'servers.view',
            self::ListFiles, self::ReadFile => 'files.read',
            self::WriteFile, self::CreateDirectory, self::RenameFile => 'files.write',
            self::DeleteFile => 'files.delete',
            self::WebsiteStart, self::WebsiteStop, self::WebsiteRestart, self::WebsiteEnable, self::WebsiteDisable => 'websites.manage',
            self::ServiceStart, self::ServiceStop, self::ServiceRestart, self::ServiceReload, self::NginxTest, self::NginxReload => 'services.manage',
            self::GetLogs => 'logs.view',
        };
    }

    public function timeoutSeconds(): int
    {
        return match ($this) {
            self::DiscoverServer, self::DiscoverWebsites => 120,
            self::GetLogs => 30,
            default => 60,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
