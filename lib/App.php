<?php
declare(strict_types=1);

namespace OCA\Gefahrstoffkataster;

use OCP\AppFramework\App as BaseApp;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * Application class for Gefahrstoffkataster.
 * Handles app initialization, migrations, and registration.
 */
class App extends BaseApp implements IBootstrap {
    public const APP_ID = 'gefahrstoffkataster';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    /**
     * Register services and components.
     *
     * @param IRegistrationContext $context
     */
    public function register(IRegistrationContext $context): void {
        // Register services
        $context->registerService('CatalogService', function ($c) {
            return new \OCA\Gefahrstoffkataster\Service\CatalogService(
                $c->query('ServerContainer')->get(IDBConnection::class),
                $c->query('ServerContainer')->get(IUserSession::class)
            );
        });
        
        $context->registerService('MigrationService', function ($c) {
            return new \OCA\Gefahrstoffkataster\Service\MigrationService(
                $c->query('ServerContainer')->get(IDBConnection::class)
            );
        });
        
        // Register AccessPolicy with CatalogService
        $context->registerService('AccessPolicy', function ($c) {
            return new \OCA\Gefahrstoffkataster\Service\AccessPolicy(
                $c->query('ServerContainer')->get(IConfig::class),
                $c->query('ServerContainer')->get(IUserSession::class),
                $c->query('ServerContainer')->get(IGroupManager::class),
                $c->query('ServerContainer')->get(IUserManager::class),
                $c->query('CatalogService')
            );
        });
    }

    /**
     * Bootstrap the application.
     *
     * @param IBootContext $context
     */
    public function boot(IBootContext $context): void {
        // Ensure catalog table exists and has default catalog
        try {
            $catalogService = $context->getAppContainer()->query(\OCA\Gefahrstoffkataster\Service\CatalogService::class);
            // Catalogs are created explicitly through the management API.
            
            // Unassigned records remain in "Ohne Katalog" until explicitly assigned.
        } catch (\Exception $e) {
            // Log error but don't fail boot
            $logger = $context->getAppContainer()->query(\Psr\Log\LoggerInterface::class);
            $logger->error('Failed to initialize catalog: ' . $e->getMessage());
        }
    }
}

