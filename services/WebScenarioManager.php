<?php

namespace Modules\WebScenario\Services;

use API;
use CApiWrapper;
use Exception;
use Zabbix\Core\CModule;

class WebScenarioManager {

    private CModule $module;

    public function __construct(CModule $module) {
        $this->module = $module;
    }

    /**
     * Buscar todos os webscenarios com informações detalhadas
     */
    public function getWebScenarios(array $options = []): array {
        $default_options = [
            'output' => ['httptestid', 'name', 'hostid', 'status', 'delay', 'retries', 'agent', 'authentication', 'nextcheck'],
            'selectHosts' => ['hostid', 'name', 'status'],
            'selectSteps' => ['httpstepid', 'name', 'no', 'url', 'status_codes', 'timeout', 'required', 'follow_redirects'],
            'sortfield' => 'name',
            'preservekeys' => true
        ];

        $merged_options = array_merge($default_options, $options);

        try {
            $webscenarios = API::HttpTest()->get($merged_options);

            
            if (count($webscenarios) <= 10) {
                foreach ($webscenarios as &$webscenario) {
                    $webscenario['last_check'] = $this->getLastCheckData($webscenario['httptestid']);
                }
            } else {
                
                foreach ($webscenarios as &$webscenario) {
                    $webscenario['last_check'] = ['lastclock' => 0, 'status' => 'UNKNOWN', 'error' => ''];
                }
            }

            return $webscenarios;
        } catch (Exception $e) {
            
            return [];
        }
    }

    /**
     * Buscar um webscenario específico por ID
     */
    public function getWebScenario(string $httptestid): ?array {
        try {
            $result = API::HttpTest()->get([
                'output' => ['httptestid', 'name', 'hostid', 'status', 'delay', 'retries', 'agent', 'authentication'],
                'selectHosts' => ['hostid', 'name', 'status'],
                'selectSteps' => ['httpstepid', 'name', 'no', 'url', 'status_codes', 'timeout', 'required'],
                'httptestids' => [$httptestid]
            ]);

            return $result ? reset($result) : null;
        } catch (Exception $e) {
            
            return null;
        }
    }

    /**
     * Criar um novo webscenario
     */
    public function createWebScenario(array $data): array {
        try {
            
            $this->validateWebScenarioData($data);

            
            

            
            $result = API::HttpTest()->create([$data]);

            
            

            
            if ($result === false) {
                
                $error = "Failed to create web scenario";

                
                if (isset($_SESSION['messages']) && !empty($_SESSION['messages'])) {
                    $messages = $_SESSION['messages'];
                    if (is_array($messages)) {
                        foreach ($messages as $msg) {
                            if (isset($msg['message'])) {
                                $error = $msg['message'];
                                break;
                            }
                        }
                    }
                }

                
                throw new Exception($error);
            }

            
            return $result;
        } catch (Exception $e) {
            
            throw $e;
        }
    }

    /**
     * Atualizar um webscenario existente
     */
    public function updateWebScenario(array $data): array {
        try {
            if (!isset($data['httptestid'])) {
                throw new Exception("ID do webscenario é obrigatório para atualização");
            }

            
            $result = API::HttpTest()->update([$data]);

            
            return $result !== false ? $result : [];
        } catch (Exception $e) {
            throw new Exception("Erro ao atualizar webscenario: " . $e->getMessage());
        }
    }

    /**
     * Deletar webscenario(s)
     */
    public function deleteWebScenario(array $httptestids): array {
        try {
            return API::HttpTest()->delete($httptestids);
        } catch (Exception $e) {
            throw new Exception("Erro ao deletar webscenario: " . $e->getMessage());
        }
    }

    /**
     * Buscar hosts disponíveis para associar webscenarios
     */
    public function getAvailableHosts(): array {
        try {
            return API::Host()->get([
                'output' => ['hostid', 'name', 'status'],
                'monitored_hosts' => true,
                'sortfield' => 'name'
            ]);
        } catch (Exception $e) {
            
            return [];
        }
    }

    /**
     * Buscar estatísticas dos webscenarios
     */
    public function getWebScenarioStatistics(): array {
        try {
            $webscenarios = $this->getWebScenarios();

            $stats = [
                'total' => count($webscenarios),
                'enabled' => 0,
                'disabled' => 0,
                'by_host' => []
            ];

            foreach ($webscenarios as $scenario) {
                if ($scenario['status'] == 0) {
                    $stats['enabled']++;
                } else {
                    $stats['disabled']++;
                }

                if (isset($scenario['hosts'][0])) {
                    $hostName = $scenario['hosts'][0]['name'];
                    $stats['by_host'][$hostName] = ($stats['by_host'][$hostName] ?? 0) + 1;
                }
            }

            return $stats;
        } catch (Exception $e) {
            
            return ['total' => 0, 'enabled' => 0, 'disabled' => 0, 'by_host' => []];
        }
    }

    /**
     * Validar dados do webscenario
     */
    private function validateWebScenarioData(array $data): void {
        if (empty($data['name'])) {
            throw new Exception("Nome do webscenario é obrigatório");
        }

        if (empty($data['hostid'])) {
            throw new Exception("Host é obrigatório");
        }

        if (empty($data['steps']) || !is_array($data['steps'])) {
            throw new Exception("Pelo menos um step é obrigatório");
        }

        foreach ($data['steps'] as $step) {
            if (empty($step['name'])) {
                throw new Exception("Nome do step é obrigatório");
            }
            if (empty($step['url'])) {
                throw new Exception("URL do step é obrigatória");
            }
        }
    }

    /**
     * Buscar dados de last check do webscenario
     */
    private function getLastCheckData(string $httptestid): array {
        try {
            
            $webscenario = API::HttpTest()->get([
                'output' => ['hostid', 'name'],
                'httptestids' => [$httptestid]
            ]);

            if (empty($webscenario)) {
                return ['lastclock' => 0, 'status' => 'UNKNOWN', 'error' => ''];
            }

            $hostid = reset($webscenario)['hostid'];
            $scenario_name = reset($webscenario)['name'];

            
            $all_web_items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock'],
                'hostids' => [$hostid],
                'webitems' => true
            ]);

            
            $filtered_items = [];
            foreach ($all_web_items as $item) {
                if (strpos($item['key_'], "[{$scenario_name}]") !== false ||
                    strpos($item['key_'], "[{$scenario_name},") !== false) {
                    $filtered_items[] = $item;
                }
            }

            if (!empty($filtered_items)) {
                
                $latest_item = null;
                $latest_clock = 0;

                foreach ($filtered_items as $item) {
                    if ($item['lastclock'] > $latest_clock) {
                        $latest_clock = $item['lastclock'];
                        $latest_item = $item;
                    }
                }

                if ($latest_item) {
                    
                    $is_error_item = strpos($latest_item['key_'], 'web.test.error') !== false;
                    $is_fail_item = strpos($latest_item['key_'], 'web.test.fail') !== false;
                    $has_error = !empty($latest_item['lastvalue']) && $latest_item['lastvalue'] !== '0';

                    
                    $status = 'OK';
                    if (($is_error_item || $is_fail_item) && $has_error) {
                        $status = 'PROBLEM';
                    }

                    return [
                        'lastclock' => $latest_item['lastclock'],
                        'status' => $status,
                        'error' => $has_error ? $latest_item['lastvalue'] : ''
                    ];
                }
            }

            return ['lastclock' => 0, 'status' => 'UNKNOWN', 'error' => ''];
        } catch (Exception $e) {
            
            return ['lastclock' => 0, 'status' => 'UNKNOWN', 'error' => ''];
        }
    }

    /**
     * Obter dados de monitoramento dos webscenarios
     */
    public function getWebScenarioMonitoringData(string $httptestid, int $time_from = null): array {
        try {
            if ($time_from === null) {
                $time_from = time() - 3600; 
            }

            
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'lastvalue', 'units'],
                'httptestids' => [$httptestid],
                'webitems' => true
            ]);

            $monitoring_data = [];
            foreach ($items as $item) {
                
                $history = API::History()->get([
                    'output' => 'extend',
                    'itemids' => [$item['itemid']],
                    'time_from' => $time_from,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 100
                ]);

                $monitoring_data[] = [
                    'item' => $item,
                    'history' => $history
                ];
            }

            return $monitoring_data;
        } catch (Exception $e) {
            
            return [];
        }
    }
}