<?php

namespace Modules\WebScenario\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CCsrfTokenHelper;
use CRoleHelper;
use CArrayHelper;
use CProfile;
use CSettingsHelper;
use API;
use Exception;
use Modules\WebScenario\Services\WebScenarioManager;

class CControllerWebScenarioList extends CController {

    public $module;
    private WebScenarioManager $webScenarioManager;

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'sort' => 'string',
            'sortorder' => 'string',
            'page' => 'int32',
            'filter_name' => 'string',
            'filter_hostid' => 'id',
            'filter_status' => 'int32',
            'filter_set' => 'in 1',
            'filter_rst' => 'in 1'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseFatal());
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        
        return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS)
            || $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);
    }

    protected function doAction(): void {
        
        $this->webScenarioManager = new WebScenarioManager($this->module);

        
        $sort_field = $this->getInput('sort', 'name');
        $sort_order = $this->getInput('sortorder', ZBX_SORT_UP);
        $page = $this->getInput('page', 1);

        
        $filter = [
            'name' => $this->getInput('filter_name', ''),
            'hostid' => $this->getInput('filter_hostid', 0),
            'status' => $this->getInput('filter_status', -1)
        ];

        
        if ($this->hasInput('filter_rst')) {
            $filter = ['name' => '', 'hostid' => 0, 'status' => -1];
        }

        
        $options = [
            'sortfield' => $sort_field,
            'sortorder' => $sort_order
        ];

        
        if (!empty($filter['name'])) {
            $options['search']['name'] = $filter['name'];
        }

        if ($filter['hostid'] > 0) {
            $options['hostids'] = [$filter['hostid']];
        }

        if ($filter['status'] >= 0) {
            $options['filter']['status'] = $filter['status'];
        }

        
        $webscenarios = $this->webScenarioManager->getWebScenarios($options);

        
        $limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
        $total_count = count($webscenarios);
        $webscenarios = array_slice($webscenarios, ($page - 1) * $limit, $limit, true);

        
        $hosts = $this->webScenarioManager->getAvailableHosts();

        
        $statistics = $this->webScenarioManager->getWebScenarioStatistics();

        
        $data = [
            'webscenarios' => $webscenarios,
            'hosts' => $hosts,
            'filter' => $filter,
            'sort' => $sort_field,
            'sortorder' => $sort_order,
            'page' => $page,
            'total_count' => $total_count,
            'limit' => $limit,
            'statistics' => $statistics,
            'csrf_token' => CCsrfTokenHelper::get('webscenario'),
            'action' => $this->getAction(),
            'can_edit' => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS),
            'profileIdx' => 'web.webscenario.list',
            'active_tab' => CProfile::get('web.webscenario.list.active_tab', 1)
        ];

        $response = new CControllerResponseData($data);
        $response->setTitle(_('WebScenario Manager'));
        $this->setResponse($response);
    }
}