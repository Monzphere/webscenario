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

class CControllerWebScenarioDashboard extends CController {

    public $module;
    private WebScenarioManager $webScenarioManager;

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'filter_url' => 'string',
            'page' => 'int32',
            'limit' => 'int32',
            'from' => 'range_time',
            'to' => 'range_time',
            'reset' => 'string'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseFatal());
        }

        
        if ($this->hasInput('from') || $this->hasInput('to')) {
            validateTimeSelectorPeriod($this->getInput('from'), $this->getInput('to'));
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        //return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS);
        return true;
    }

    /**
     * Buscar estatísticas do dashboard (otimizado com cache)
     */
    private function getDashboardStatistics(): array {
        try {
            
            $all_scenarios = API::HttpTest()->get([
                'output' => ['httptestid', 'status'],
                'selectSteps' => API_OUTPUT_COUNT,
                'countOutput' => false
            ]);

            $total = count($all_scenarios);
            $enabled = 0;
            $disabled = 0;

            
            $fail_items = API::Item()->get([
                'output' => ['httptestid', 'lastvalue', 'name', 'key_'],
                'webitems' => true,
                'search' => ['key_' => 'web.test.fail']
            ]);

            
            $scenario_status = [];
            foreach ($fail_items as $item) {
                if (!empty($item['httptestid'])) {
                    if (!empty($item['lastvalue']) && $item['lastvalue'] > 0) {
                        
                        $scenario_status[$item['httptestid']] = 'problem';
                    } elseif (!isset($scenario_status[$item['httptestid']])) {
                        
                        $scenario_status[$item['httptestid']] = 'ok';
                    }
                }
            }

            $enabled = 0;
            $disabled = 0;

            
            $scenarios_with_steps = API::HttpTest()->get([
                'output' => ['httptestid', 'name', 'status', 'hostid'],
                'selectSteps' => ['name']
            ]);

            
            $scenario_failed_steps = [];
            foreach ($scenarios_with_steps as $scenario) {
                $scenario_failed_steps[$scenario['httptestid']] = false;

                
                $items = API::Item()->get([
                    'output' => ['key_', 'lastvalue'],
                    'webitems' => true,
                    'filter' => ['hostid' => $scenario['hostid']],
                    'search' => ['key_' => 'web.test.fail']
                ]);

                foreach ($items as $item) {
                    $key = $item['key_'];
                    $escaped_scenario = preg_quote($scenario['name'], '/');

                    
                    if (preg_match("/^web\.test\.fail\[{$escaped_scenario}\]$/", $key) &&
                        !empty($item['lastvalue']) && $item['lastvalue'] > 0) {
                        $scenario_failed_steps[$scenario['httptestid']] = true;
                        
                        break;
                    }
                }
            }

            foreach ($all_scenarios as $scenario) {
                $has_failed_step = $scenario_failed_steps[$scenario['httptestid']] ?? false;

                
                if ($scenario['status'] == '0') {
                    
                    $status = $scenario_status[$scenario['httptestid']] ?? 'ok';
                    if ($status === 'ok' && !$has_failed_step) {
                        $enabled++;
                    } else {
                        $disabled++; 
                    }
                } else {
                    $disabled++;
                }
            }

            
            $hosts_with_scenarios = API::Host()->get([
                'output' => ['hostid'],
                'httptestids' => array_column($all_scenarios, 'httptestid'),
                'countOutput' => true
            ]);

            
            $total_steps = 0;
            foreach ($all_scenarios as $scenario) {
                $total_steps += (int) ($scenario['steps'] ?? 0);
            }

            
            $total_time = 0;
            $time_count = 0;

            
            $scenarios_with_steps = API::HttpTest()->get([
                'output' => ['httptestid', 'name'],
                'selectSteps' => ['name'],
                'selectHosts' => ['hostid'],
                'filter' => ['status' => 0]
            ]);

            foreach ($scenarios_with_steps as $scenario) {
                foreach ($scenario['steps'] as $step) {
                    $items = API::Item()->get([
                        'output' => ['lastvalue'],
                        'hostids' => array_column($scenario['hosts'], 'hostid'),
                        'webitems' => true,
                        'filter' => [
                            'key_' => [
                                'web.test.time[' . $scenario['name'] . ',' . $step['name'] . ',resp]',
                                'web.test.time[' . $scenario['name'] . ',' . $step['name'] . ']'
                            ]
                        ]
                    ]);

                    foreach ($items as $item) {
                        if (!empty($item['lastvalue']) && $item['lastvalue'] > 0) {
                            $total_time += (float)$item['lastvalue'];
                            $time_count++;
                        }
                    }
                }
            }

            $avg_response_time = $time_count > 0 ? $total_time / $time_count : 0;

            return [
                'total_scenarios' => $total,
                'enabled_scenarios' => $enabled,
                'disabled_scenarios' => $disabled,
                'total_hosts' => $hosts_with_scenarios,
                'total_steps' => $total_steps,
                'availability_rate' => $total > 0 ? round(($enabled / $total) * 100, 1) : 0,
                'avg_response_time' => round($avg_response_time, 3)
            ];

        } catch (Exception $e) {
            
            return [
                'total_scenarios' => 0,
                'enabled_scenarios' => 0,
                'disabled_scenarios' => 0,
                'total_hosts' => 0,
                'total_steps' => 0,
                'availability_rate' => 0
            ];
        }
    }

    /**
     * Buscar webscenarios com filtros e paginação
     */
    private function getWebScenarios(string $filter_url, int $page, int $limit): array {
        try {
            $options = [
                'output' => ['httptestid', 'name', 'hostid', 'status', 'delay', 'retries'],
                'selectHosts' => ['name'],
                'selectSteps' => ['httpstepid', 'name', 'url'],
                'sortfield' => 'name',
                'limit' => $limit,
                'offset' => ($page - 1) * $limit
            ];

            
            if (!empty($filter_url)) {
                
                $search_options = $options;
                unset($search_options['limit'], $search_options['offset']);

                $all_scenarios = API::HttpTest()->get($search_options);
                $filtered_scenarios = [];

                foreach ($all_scenarios as $scenario) {
                    $match_found = false;

                    
                    if (stripos($scenario['name'], $filter_url) !== false) {
                        $match_found = true;
                    }

                    
                    if (!$match_found && isset($scenario['steps']) && is_array($scenario['steps'])) {
                        foreach ($scenario['steps'] as $step) {
                            if (isset($step['url']) && stripos($step['url'], $filter_url) !== false) {
                                $match_found = true;
                                break;
                            }
                        }
                    }

                    if ($match_found) {
                        $filtered_scenarios[] = $scenario;
                    }
                }

                $total = count($filtered_scenarios);
                
                $webscenarios = array_slice($filtered_scenarios, ($page - 1) * $limit, $limit, true);
            } else {
                
                
                $count_options = $options;
                unset($count_options['limit'], $count_options['offset'], $count_options['selectHosts'], $count_options['selectSteps'], $count_options['sortfield']);
                $count_options['countOutput'] = true;
                $total = API::HttpTest()->get($count_options);

                
                $webscenarios = API::HttpTest()->get($options);
            }

            
            foreach ($webscenarios as &$scenario) {
                
                $items = API::Item()->get([
                    'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock'],
                    'webitems' => true,
                    'hostids' => [$scenario['hostid']],
                    'search' => [
                        'key_' => 'web.test'
                    ]
                ]);

                
                
                foreach ($items as $item) {
                    
                }

                
                $fail_items = API::Item()->get([
                    'output' => ['lastvalue', 'name', 'key_'],
                    'webitems' => true,
                    'hostids' => [$scenario['hostid']],
                    'search' => ['key_' => 'web.test.fail']
                ]);

                
                
                foreach ($fail_items as $fail_item) {
                    
                }

                $has_any_failure = false;
                foreach ($fail_items as $fail_item) {
                    if (!empty($fail_item['lastvalue']) && $fail_item['lastvalue'] > 0) {
                        $has_any_failure = true;
                        break;
                    }
                }

                
                foreach ($scenario['steps'] as &$step) {
                    
                    $step['status_code'] = null;
                    $step['response_time'] = null;
                    $step['failed'] = false;  
                    $step['fail_value'] = null;
                    $step['download_speed'] = null;

                    foreach ($items as $item) {
                        $key = $item['key_'];

                        
                        if (strpos($key, $scenario['name']) === false) {
                            continue;
                        }

                        
                        $escaped_scenario = preg_quote($scenario['name'], '/');
                        $escaped_step = preg_quote($step['name'], '/');

                        
                        if (preg_match("/web\.test\.rspcode\[{$escaped_scenario},{$escaped_step}\]/", $key)) {
                            $step['status_code'] = $item['lastvalue'];
                            
                        }
                        
                        if (preg_match("/web\.test\.time\[{$escaped_scenario},{$escaped_step}(,resp)?\]/", $key)) {
                            $step['response_time'] = $item['lastvalue'];
                            
                        }
                        
                        if (preg_match("/web\.test\.fail\[{$escaped_scenario},{$escaped_step}\]/", $key) ||
                            preg_match("/web\.test\.fail\[{$escaped_scenario}\]/", $key)) {
                            $step['failed'] = !empty($item['lastvalue']) && $item['lastvalue'] > 0;
                            $step['fail_value'] = (int)$item['lastvalue'];
                            
                        }
                        
                        if (preg_match("/web\.test\.in\[{$escaped_scenario},{$escaped_step}\]/", $key)) {
                            $step['download_speed'] = $item['lastvalue'];
                            
                        }

                        
                        if (($step['download_speed'] === null || $step['download_speed'] === '') &&
                            strpos($key, 'web.test.in') !== false &&
                            strpos($key, $step['name']) !== false) {
                            $step['download_speed'] = $item['lastvalue'];
                            
                        }

                        
                        
                    }

                    
                    if ($step['failed'] === true) {
                        
                    }
                }

                
                $scenario_fail_value = 0;
                foreach ($items as $item) {
                    $key = $item['key_'];
                    $escaped_scenario = preg_quote($scenario['name'], '/');

                    
                    if (preg_match("/^web\.test\.fail\[{$escaped_scenario}\]$/", $key) &&
                        !empty($item['lastvalue']) && $item['lastvalue'] > 0) {
                        $scenario_fail_value = (int)$item['lastvalue'];
                        
                        break;
                    }
                }

                
                if ($scenario_fail_value > 0) {
                    
                    $failed_step_index = $scenario_fail_value - 1; 

                    
                    foreach ($scenario['steps'] as &$reset_step) {
                        $reset_step['failed'] = false;
                        $reset_step['fail_value'] = null;
                    }
                    unset($reset_step);

                    
                    if (isset($scenario['steps'][$failed_step_index])) {
                        $scenario['steps'][$failed_step_index]['failed'] = true;
                        $scenario['steps'][$failed_step_index]['fail_value'] = $scenario_fail_value;
                        $failed_step_name = $scenario['steps'][$failed_step_index]['name'];
                        
                    } else {
                        
                    }
                }

                unset($step);

                
                if ($has_any_failure) {
                    $scenario['last_check'] = [
                        'lastclock' => time(),
                        'status' => 'PROBLEM'
                    ];
                } else {
                    $scenario['last_check'] = [
                        'lastclock' => time(),
                        'status' => 'OK'
                    ];
                }

                
            }
            unset($scenario);

            return [
                'data' => $webscenarios,
                'total' => $total
            ];

        } catch (Exception $e) {
            
            return ['data' => [], 'total' => 0];
        }
    }

    /**
     * Buscar last check simplificado (para performance)
     */
    private function getSimpleLastCheck(string $httptestid): array {
        try {
            
            $items = API::Item()->get([
                'output' => ['lastclock', 'lastvalue'],
                'httptestids' => [$httptestid],
                'webitems' => true,
                'search' => ['key_' => 'web.test.fail']
            ]);

            if (!empty($items)) {
                $lastclock = 0;
                $has_problem = false;

                
                foreach ($items as $item) {
                    if ($item['lastclock'] > $lastclock) {
                        $lastclock = $item['lastclock'];
                    }
                    if (!empty($item['lastvalue']) && $item['lastvalue'] > 0) {
                        $has_problem = true;
                        break; 
                    }
                }

                return [
                    'lastclock' => $lastclock,
                    'status' => $has_problem ? 'PROBLEM' : 'OK'
                ];
            }

            return ['lastclock' => 0, 'status' => 'UNKNOWN'];

        } catch (Exception $e) {
            return ['lastclock' => 0, 'status' => 'UNKNOWN'];
        }
    }

    /**
     * Buscar hosts para filtro
     */
    private function getHostsForFilter(): array {
        try {
            
            $hosts_with_scenarios = API::HttpTest()->get([
                'output' => ['hostid'],
                'selectHosts' => ['hostid', 'name']
            ]);

            $hosts = [];
            $seen_hosts = [];

            foreach ($hosts_with_scenarios as $scenario) {
                if (!empty($scenario['hosts'][0])) {
                    $host = $scenario['hosts'][0];
                    if (!isset($seen_hosts[$host['hostid']])) {
                        $hosts[] = $host;
                        $seen_hosts[$host['hostid']] = true;
                    }
                }
            }

            
            usort($hosts, function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });

            return $hosts;

        } catch (Exception $e) {
            
            return [];
        }
    }

    /**
     * Buscar webscenarios com problemas recentes
     */
    private function getProblematicScenarios(): array {
        try {
            
            $failed_items = API::Item()->get([
                'output' => ['httptestid'],
                'webitems' => true,
                'search' => ['key_' => 'web.test.fail'],
                'filter' => ['lastvalue' => ['1', '2', '3', '4']],
                'limit' => 5
            ]);

            if (empty($failed_items)) {
                return [];
            }

            
            $httptestids = array_unique(array_column($failed_items, 'httptestid'));

            $scenarios = API::HttpTest()->get([
                'output' => ['httptestid', 'name'],
                'selectHosts' => ['name'],
                'httptestids' => $httptestids
            ]);

            return $scenarios;

        } catch (Exception $e) {
            
            return [];
        }
    }

    /**
     * Buscar todas as métricas dos webscenarios para o gráfico
     */
    private function getAllWebScenarioMetrics(): array {
        $metrics = [];
        try {
            
            $scenarios = API::HttpTest()->get([
                'output' => ['httptestid', 'name'],
                'selectSteps' => ['name'],
                'selectHosts' => ['name']
            ]);
            foreach ($scenarios as $scenario) {
                $scenario_name = $scenario['name'];
                $host_name = isset($scenario['hosts'][0]['name']) ? $scenario['hosts'][0]['name'] : '';
                
                $items = API::Item()->get([
                    'output' => ['name', 'key_', 'lastvalue', 'units'],
                    'httptestids' => [$scenario['httptestid']],
                    'webitems' => true,
                    'search' => [
                        'key_' => [
                            'web.test.in',
                            'web.test.fail',
                            'web.test.error',
                            'web.test.rspcode',
                            'web.test.time'
                        ]
                    ],
                    'searchByAny' => true
                ]);
                foreach ($items as $item) {
                    $label = $item['name'];
                    $value = $item['lastvalue'];
                    $unit = $item['units'];
                    $key = $item['key_'];
                    $metrics[] = [
                        'label' => $label . ' (' . $scenario_name . ')',
                        'value' => is_numeric($value) ? (float)$value : $value,
                        'unit' => $unit,
                        'key' => $key,
                        'host' => $host_name
                    ];
                }
            }
        } catch (Exception $e) {
            
        }
        return $metrics;
    }

    /**
     * Buscar tendências de tempo de resposta dos webscenarios para o gráfico
     */
    private function getWebScenarioResponseTrends(): array {
        $trends = [];
        try {
            
            $scenarios = API::HttpTest()->get([
                'output' => ['httptestid', 'name'],
                'selectHosts' => ['name']
            ]);
            foreach ($scenarios as $scenario) {
                $scenario_name = $scenario['name'];
                $host_name = isset($scenario['hosts'][0]['name']) ? $scenario['hosts'][0]['name'] : '';
                
                $items = API::Item()->get([
                    'output' => ['itemid', 'name', 'key_'],
                    'httptestids' => [$scenario['httptestid']],
                    'webitems' => true,
                    'search' => ['key_' => 'web.test.time'],
                    'searchByAny' => true
                ]);
                foreach ($items as $item) {
                    
                    $history = API::History()->get([
                        'output' => 'extend',
                        'itemids' => [$item['itemid']],
                        'sortfield' => 'clock',
                        'sortorder' => 'ASC',
                        'limit' => 24
                    ]);
                    foreach ($history as $point) {
                        $trends[$scenario_name]['host'] = $host_name;
                        $trends[$scenario_name]['data'][] = [
                            'clock' => date('H:i', $point['clock']),
                            'value' => (float)$point['value']
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            
        }
        return $trends;
    }

    private function getResponseTimeTrends(): array {
        $trends = [];
        try {
            
            $scenarios = API::HttpTest()->get([
                'output' => ['httptestid', 'name'],
                'selectSteps' => ['name'],
                'selectHosts' => ['hostid', 'name'],
                'filter' => ['status' => 0]
            ]);

            
            $all_items = [];
            foreach ($scenarios as $scenario) {
                foreach ($scenario['steps'] as $step) {
                    $items = API::Item()->get([
                        'output' => ['itemid', 'name', 'key_'],
                        'hostids' => array_column($scenario['hosts'], 'hostid'),
                        'webitems' => true,
                        'filter' => [
                            'key_' => [
                                'web.test.time[' . $scenario['name'] . ',' . $step['name'] . ',resp]',
                                'web.test.time[' . $scenario['name'] . ',' . $step['name'] . ']'
                            ]
                        ]
                    ]);
                    if (!empty($items)) {
                        foreach ($items as $item) {
                            $all_items[$item['itemid']] = [
                                'scenario' => $scenario['name'],
                                'step' => $step['name']
                            ];
                        }
                    }
                }
            }

            if (!empty($all_items)) {
                
                $history = API::History()->get([
                    'output' => ['itemid', 'clock', 'value'],
                    'itemids' => array_keys($all_items),
                    'time_from' => time() - 24*3600,
                    'history' => 0,
                    'sortfield' => 'clock',
                    'sortorder' => 'ASC'
                ]);

                
                foreach ($history as $point) {
                    $itemid = $point['itemid'];
                    if (isset($all_items[$itemid])) {
                        $scenario_name = $all_items[$itemid]['scenario'] . ' - ' . $all_items[$itemid]['step'];
                        $trends[$scenario_name]['data'][] = [
                            'clock' => date('H:i', $point['clock']),
                            'value' => (float)$point['value']
                        ];
                    }
                }
            }

        } catch (Exception $e) {
            
        }
        return $trends;
    }

    protected function doAction(): void {
        try {
            
            if ($this->module) {
                $this->webScenarioManager = new WebScenarioManager($this->module);
            }

            
            $filter_url = $this->getInput('filter_url', '');
            $page = $this->getInput('page', 1);
            $limit = 20; 

            
            $timeselector_options = [
                'profileIdx' => 'web.httpdetails.filter',
                'profileIdx2' => 0,
                'from' => $this->hasInput('from') ? $this->getInput('from') : null,
                'to' => $this->hasInput('to') ? $this->getInput('to') : null
            ];
            updateTimeSelectorPeriod($timeselector_options);
            $timeline = getTimeSelectorPeriod($timeselector_options);

            
            $statistics = $this->getDashboardStatistics();

            
            $webscenarios = $this->getWebScenarios($filter_url, $page, $limit);

            
            $problematic_scenarios = $this->getProblematicScenarios();

            
            $metrics = $this->getAllWebScenarioMetrics();

            
            $response_trends = $this->getResponseTimeTrends();

            
            $data = [
                'statistics' => $statistics,
                'webscenarios' => $webscenarios['data'],
                'total_count' => $webscenarios['total'],
                'problematic_scenarios' => $problematic_scenarios,
                'metrics' => $metrics,
                'response_trends' => $response_trends ?? [],
                'timeline' => $timeline,
                'filter' => [
                    'url' => $filter_url
                ],
                'paging' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $webscenarios['total']
                ],
                'csrf_token' => CCsrfTokenHelper::get('webscenario'),
                'can_edit' => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
            ];

            $response = new CControllerResponseData($data);
            $response->setTitle(_('Web Scenario Dashboard'));
            $this->setResponse($response);

        } catch (Exception $e) {
            
            $this->setResponse(new CControllerResponseFatal());
        }
    }
}
