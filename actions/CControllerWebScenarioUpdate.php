<?php

namespace Modules\WebScenario\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CControllerResponseRedirect;
use CCsrfTokenHelper;
use CRoleHelper;
use API;
use Exception;
use Throwable;
use Modules\WebScenario\Services\WebScenarioManager;

class CControllerWebScenarioUpdate extends CController {

    public $module;
    private WebScenarioManager $webScenarioManager;

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'httptestid' => 'string',
            'hostid' => 'required|string',
            
            'name' => 'required|string',
            'delay' => 'string',
            'retries' => 'string',
            'agent' => 'string',
            'authentication' => 'string',
            'http_user' => 'string',
            'http_password' => 'string',
            'status' => 'string',
            'http_proxy' => 'string',
            
            'steps_data' => 'required|string',
            
            'variables' => 'string',
            'headers' => 'string'
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
            
            if (!$this->module) {
                
                $use_direct_api = true;
            } else {
                
                $this->webScenarioManager = new WebScenarioManager($this->module);
                $use_direct_api = false;
            }

            
            $webscenario_data = [
                'name' => $this->getInput('name'),
                'hostid' => $this->getInput('hostid'),
                'delay' => $this->getInput('delay', '1m'),
                'retries' => intval($this->getInput('retries', '1')),
                'agent' => $this->getInput('agent', 'Zabbix'),
                'authentication' => intval($this->getInput('authentication', '0')),
                'http_user' => $this->getInput('http_user', ''),
                'http_password' => $this->getInput('http_password', ''),
                'status' => $this->getInput('status', '0'), 
                'http_proxy' => $this->getInput('http_proxy', ''),
                'steps' => []
            ];

            
            error_log("WebScenario Create - Input data: " . json_encode([
                'name' => $this->getInput('name'),
                'hostid' => $this->getInput('hostid'),
                'status' => $this->getInput('status')
            ]));

            
            $httptestid = $this->getInput('httptestid');
            if ($httptestid) {
                $webscenario_data['httptestid'] = $httptestid;
            }

            
            $steps_data = $this->getInput('steps_data');
            if ($steps_data) {
                $steps = json_decode($steps_data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Dados dos steps inválidos: ' . json_last_error_msg());
                }
                if (is_array($steps)) {
                    foreach ($steps as $index => $step) {
                        if (!is_array($step)) {
                            throw new Exception("Step {$index} deve ser um array");
                        }

                        $step_data = [
                            'no' => intval($step['no'] ?? 1),
                            'name' => trim($step['name'] ?? ''),
                            'url' => trim($step['url'] ?? ''),
                            'timeout' => $step['timeout'] ?? '15s',
                            'required' => $step['required'] ?? '',
                            'status_codes' => $step['status_codes'] ?? '200',
                            'follow_redirects' => isset($step['follow_redirects']) ? intval($step['follow_redirects']) : 1,
                            'retrieve_mode' => intval($step['retrieve_mode'] ?? 0),
                            'posts' => $step['posts'] ?? '',
                            'query_fields' => [],
                            'headers' => [],
                            'variables' => []
                        ];

                        
                        if (empty($step_data['name'])) {
                            throw new Exception("Nome do step " . ($index + 1) . " é obrigatório");
                        }
                        if (empty($step_data['url'])) {
                            throw new Exception("URL do step " . ($index + 1) . " é obrigatória");
                        }

                        
                        foreach (['query_fields', 'headers', 'variables'] as $field_type) {
                            if (isset($step[$field_type]) && is_array($step[$field_type])) {
                                foreach ($step[$field_type] as $field) {
                                    if (!empty($field['name'])) {
                                        $step_data[$field_type][] = [
                                            'name' => $field['name'],
                                            'value' => $field['value'] ?? ''
                                        ];
                                    }
                                }
                            }
                        }

                        $webscenario_data['steps'][] = $step_data;
                    }
                }
            }

            
            if (empty($webscenario_data['steps'])) {
                throw new Exception('Pelo menos um step é obrigatório');
            }

            
            

            
            if ($use_direct_api) {
                
                
                if ($httptestid) {
                    $result = API::HttpTest()->update([$webscenario_data]);
                    $message = 'Web scenario atualizado com sucesso';
                } else {
                    $result = API::HttpTest()->create([$webscenario_data]);
                    $message = 'Web scenario criado com sucesso';
                }
            } else {
                
                
                if ($httptestid) {
                    $result = $this->webScenarioManager->updateWebScenario($webscenario_data);
                    $message = 'Web scenario atualizado com sucesso';
                } else {
                    $result = $this->webScenarioManager->createWebScenario($webscenario_data);
                    $message = 'Web scenario criado com sucesso';
                }
            }

            

            
            if (empty($result)) {
                throw new Exception('API returned empty result - web scenario may not have been created');
            }

            
            $response_data = [
                'success' => true,
                'message' => $message,
                'httptestid' => $httptestid ?: ($result['httptestids'][0] ?? null)
            ];

            
            header('Content-Type: application/json');
            echo json_encode($response_data);
            exit;

        } catch (Exception $e) {
            
            $response_data = [
                'success' => false,
                'error' => $e->getMessage()
            ];

            

            
            header('Content-Type: application/json');
            echo json_encode($response_data);
            exit;
        } catch (Throwable $e) {
            
            $response_data = [
                'success' => false,
                'error' => 'Erro interno do servidor: ' . $e->getMessage()
            ];

            

            
            header('Content-Type: application/json');
            echo json_encode($response_data);
            exit;
        }
    }
}