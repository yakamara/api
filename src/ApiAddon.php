<?php

namespace Yakamara\Api;

use Override;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Addon\LoadOrder;
use Redaxo\Core\Addon\OwnedSchema;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Backend\MainPage;
use Redaxo\Core\Backend\Page;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\Index;
use Redaxo\Core\Database\Table;
use Redaxo\Core\Environment;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\Security\Permission;
use Redaxo\Core\View\Asset;
use Yakamara\Api\RoutePackage\Backend\Clangs as BackendClangs;
use Yakamara\Api\RoutePackage\Backend\Discovery as BackendDiscovery;
use Yakamara\Api\RoutePackage\Backend\Media as BackendMedia;
use Yakamara\Api\RoutePackage\Backend\Metainfo as BackendMetainfo;
use Yakamara\Api\RoutePackage\Backend\Modules as BackendModules;
use Yakamara\Api\RoutePackage\Backend\Structure as BackendStructure;
use Yakamara\Api\RoutePackage\Backend\Templates as BackendTemplates;
use Yakamara\Api\RoutePackage\Backend\Users as BackendUsers;
use Yakamara\Api\RoutePackage\Clangs;
use Yakamara\Api\RoutePackage\Discovery;
use Yakamara\Api\RoutePackage\Media;
use Yakamara\Api\RoutePackage\MediaUpload;
use Yakamara\Api\RoutePackage\Metainfo;
use Yakamara\Api\RoutePackage\Modules;
use Yakamara\Api\RoutePackage\Structure;
use Yakamara\Api\RoutePackage\Templates;
use Yakamara\Api\RoutePackage\Users;

class ApiAddon extends Addon
{
    /**
     * The api addon must be able to intercept a frontend request before any URL addon turns it into a
     * 404, so it boots early.
     */
    public protected(set) LoadOrder $load = LoadOrder::Early;

    #[Override]
    public function boot(): void
    {
        Permission::register('api[]', $this->i18n('perm_api'));

        RouteCollection::registerRoutePackage(new Clangs());
        RouteCollection::registerRoutePackage(new Modules());
        RouteCollection::registerRoutePackage(new Structure());
        RouteCollection::registerRoutePackage(new Templates());
        RouteCollection::registerRoutePackage(new Media());
        RouteCollection::registerRoutePackage(new MediaUpload());
        RouteCollection::registerRoutePackage(new Users());
        RouteCollection::registerRoutePackage(new Metainfo());
        RouteCollection::registerRoutePackage(new Discovery());
        RouteCollection::registerRoutePackage(new BackendClangs());
        RouteCollection::registerRoutePackage(new BackendDiscovery());
        RouteCollection::registerRoutePackage(new BackendMedia());
        RouteCollection::registerRoutePackage(new BackendMetainfo());
        RouteCollection::registerRoutePackage(new BackendModules());
        RouteCollection::registerRoutePackage(new BackendStructure());
        RouteCollection::registerRoutePackage(new BackendTemplates());
        RouteCollection::registerRoutePackage(new BackendUsers());

        if (Environment::Console === Core::getEnvironment()) {
            return;
        }

        // Two entry points on purpose, whichever comes first wins (RouteCollection::handle() exits):
        //
        // YREWRITE_PREPARE is the regular one when yrewrite is installed. yrewrite registers its URL rewriting
        // in its own (early) PACKAGES_INCLUDED listener and only then resolves the path, so a handler running
        // here generates the same article URLs as the website. Handling earlier, before that listener, left
        // every URL a handler built in the form `./index.php?article_id=…` -- noticed with MCP tools that
        // return links.
        //
        // PACKAGES_INCLUDED (late) covers installations without yrewrite, and paths yrewrite does not hand
        // on. All route packages are known by then.
        Extension::register('PACKAGES_INCLUDED', static function (): void {
            RouteCollection::handle();
        }, ExtensionLevel::Late);

        Extension::register('YREWRITE_PREPARE', static function (): void {
            RouteCollection::handle();
        }, ExtensionLevel::Early);

        if (Core::isBackend() && 'api/openapi' === Controller::getCurrentPage()) {
            Asset::addCssFile($this->getAssetsUrl('vendor/swagger-ui/css/swagger-ui.css'));
            Asset::addCssFile($this->getAssetsUrl('css/swagger-ui-redaxo-theme.css'));
            Asset::addJsFile($this->getAssetsUrl('vendor/swagger-ui/js/swagger-ui-bundle.js'));
        }
    }

    #[Override]
    public function install(): void
    {
        Table::get(Token::TABLE)
            ->ensurePrimaryIdColumn()
            ->ensureColumn(new Column('name', 'varchar(191)', false, ''))
            ->ensureColumn(new Column('token', 'varchar(191)', false, ''))
            ->ensureColumn(new Column('status', 'tinyint(1)', false, '1'))
            ->ensureColumn(new Column('scopes', 'text', true))
            ->ensureColumn(new Column('expires_at', 'datetime', true))
            ->ensureIndex(new Index('token', ['token'], Index::UNIQUE))
            ->ensure();
    }

    #[Override]
    public function getOwnedSchema(): OwnedSchema
    {
        return new OwnedSchema(tables: [Token::TABLE]);
    }

    /** @return iterable<Page> */
    #[Override]
    public function getPages(): iterable
    {
        $main = new MainPage('addons', 'api', $this->i18n('title'));
        $main->setRequiredPermissions('api[]');
        $main->setIcon('rex-icon fa-exchange');
        $main->setPath($this->getPath('pages/index.php'));

        $main->addSubpage(
            new Page('token', $this->i18n('token'))
                ->setRequiredPermissions('admin')
                ->setSubPath($this->getPath('pages/token.php')),
        );
        $main->addSubpage(
            new Page('openapi', $this->i18n('openapi'))
                ->setSubPath($this->getPath('pages/openapi.php')),
        );
        $main->addSubpage(
            new Page('readme', $this->i18n('readme'))
                ->setItemAttr('data-align', 'end')
                ->setSubPath($this->getPath('README.md')),
        );

        yield $main;
    }
}
