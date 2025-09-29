<?php

namespace Modules\WebScenario\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CCsrfTokenHelper;
use CRoleHelper;
use API;
use CApiService;
use Exception;
use Modules\WebScenario\Services\WebScenarioManager;

class CControllerWebScenarioCreate extends CController {

    public $module;
    private WebScenarioManager $webScenarioManager;

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'hostid' => 'string',
            'step' => 'string',
            
            'name' => 'string',
            'delay' => 'string',
            'retries' => 'string',
            'agent' => 'string',
            'authentication' => 'string',
            'http_user' => 'string',
            'http_password' => 'string',
            'status' => 'string',
            
            'steps_data' => 'string'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseFatal());
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
    }

    protected function doAction(): void {
        
        $hostid = $this->getInput('hostid', '');

        
        $host_ms = [];
        if ($hostid) {
            $hosts = API::Host()->get([
                'output' => ['hostid', 'name'],
                'hostids' => [$hostid]
            ]);
            if ($hosts) {
                $host = reset($hosts);
                $host_ms = [
                    $host['hostid'] => $host['name']
                ];
            }
        }

        
        $default_data = [
            'name' => '',
            'delay' => '1m',
            'retries' => '1',
            'agent' => 'Zabbix',
            'authentication' => '0',
            'http_user' => '',
            'http_password' => '',
            'status' => '0'
        ];

        
        $data = [
            'host_ms' => $host_ms,
            'hosts' => [], 
            'webscenario_data' => $default_data,
            'steps_data' => [],
            'current_step' => $this->getInput('step', 'scenario'),
            'hostid' => $hostid,
            'csrf_token' => CCsrfTokenHelper::get('webscenario'),
            'user' => [
                'debug_mode' => CApiService::$userData['debug_mode'] ?? GROUP_DEBUG_MODE_DISABLED
            ]
        ];

        $response = new CControllerResponseData($data);
        $response->setTitle(_('Create Web Scenario'));
        $this->setResponse($response);
    }
}