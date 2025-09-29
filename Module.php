<?php

namespace Modules\WebScenario;

use APP;
use CMenu;
use CMenuItem;
use CController as Action;
use Modules\WebScenario\Services\WebScenarioManager;
use Zabbix\Core\CModule;

/**
 * @property WebScenarioManager $webScenarioManager
 */
class Module extends CModule {

    public WebScenarioManager $webScenarioManager;

    public function getAssets(): array {
        $assets = parent::getAssets();
        $action = APP::Component()->router->getAction();

        
        if (in_array($action, ['webscenario.list', 'webscenario.create', 'webscenario.edit'])) {
            $assets['js'][] = 'webscenario.js';
            $assets['css'][] = 'webscenario.css';
        }
        
        if (in_array($action, ['webscenario.create', 'webscenario.edit'])) {
            $assets['js'][] = 'webscenario.form.js';
        }

        return $assets;
    }

    public function init(): void {
        $this->webScenarioManager = new WebScenarioManager($this);
        $this->registerMenuEntry();
    }

    public function onBeforeAction(Action $action): void {
        if (strpos($action::class, __NAMESPACE__) === 0) {
            $action->module = $this;
        }
    }

    public function onTerminate(Action $action): void {
        
    }

    protected function registerMenuEntry(): void {
        /** @var CMenuItem $menu */
        $menu = APP::Component()->get('menu.main')->find(_('Monitoring'));

        if ($menu instanceof CMenuItem) {
            $menu->getSubMenu()
                ->insertAfter(_('Web scenarios'),
                    (new CMenuItem(_('WebScenario Dashboard')))
                        ->setAction('webscenario.dashboard')
                        ->setAliases(['webscenario.list', 'webscenario.create', 'webscenario.edit'])
                );
        }

        
        $configMenu = APP::Component()->get('menu.main')->find(_('Data collection'));
        if ($configMenu instanceof CMenuItem) {
            $configMenu->getSubMenu()
                ->add((new CMenuItem(_('WebScenario Manager')))
                    ->setAction('webscenario.list')
                );
        }
    }
}