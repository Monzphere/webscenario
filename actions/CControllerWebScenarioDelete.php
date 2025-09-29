<?php

namespace Modules\WebScenario\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CCsrfTokenHelper;
use CRoleHelper;
use API;
use Exception;
use Modules\WebScenario\Services\WebScenarioManager;

class CControllerWebScenarioDelete extends CController {

    public $module;
    private WebScenarioManager $webScenarioManager;

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'httptestids' => 'required|array'
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
        try {
            
            $this->webScenarioManager = new WebScenarioManager($this->module);

            
            $httptestids = $this->getInput('httptestids');

            
            if (empty($httptestids) || !is_array($httptestids)) {
                throw new Exception('IDs dos web scenarios são obrigatórios');
            }

            
            $existing_scenarios = API::HttpTest()->get([
                'output' => ['httptestid', 'name'],
                'httptestids' => $httptestids,
                'editable' => true
            ]);

            if (count($existing_scenarios) !== count($httptestids)) {
                throw new Exception('Um ou mais web scenarios não foram encontrados ou você não tem permissão para deletá-los');
            }

            
            $result = $this->webScenarioManager->deleteWebScenario($httptestids);

            
            $response_data = [
                'success' => true,
                'message' => 'Web scenario(s) deletado(s) com sucesso',
                'deleted_count' => count($httptestids)
            ];

            $this->setResponse(new CControllerResponseData($response_data));

        } catch (Exception $e) {
            
            $response_data = [
                'success' => false,
                'error' => $e->getMessage()
            ];

            
            $this->setResponse(new CControllerResponseData($response_data));
        }
    }
}