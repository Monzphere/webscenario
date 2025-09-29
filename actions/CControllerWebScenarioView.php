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

class CControllerWebScenarioView extends CController {

    public $module;
    private WebScenarioManager $webScenarioManager;

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'httptestid' => 'required|id',
            'period' => 'string',
            'from' => 'string',
            'to' => 'string'
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

        $httptestid = $this->getInput('httptestid');

        
        $webscenario = $this->webScenarioManager->getWebScenario($httptestid);

        if (!$webscenario) {
            $this->setResponse(new CControllerResponseFatal(_('WebScenario not found')));
            return;
        }

        
        $period = $this->getInput('period', '1h');
        $from = $this->getInput('from', '');
        $to = $this->getInput('to', '');

        
        $time_to = time();
        $time_from = $time_to - $this->parsePeriod($period);

        if ($from && $to) {
            $time_from = strtotime($from);
            $time_to = strtotime($to);
        }

        
        $items = $this->getWebScenarioItems($httptestid);

        
        $item_values = [];
        

        foreach ($items as $item) {
            $lastvalue = $item['lastvalue'] ?? '';
            $lastclock = $item['lastclock'] ?? 0;

            

            $item_values[$item['itemid']] = [
                'lastvalue' => $lastvalue,
                'lastclock' => $lastclock
            ];
        }

        

        
        $statistics = $this->calculateStatistics($items);

        $monitoring_data = [];

        
        $data = [
            'webscenario' => $webscenario,
            'monitoring_data' => $monitoring_data,
            'statistics' => $statistics,
            'items' => $items,
            'item_values' => $item_values,
            'period' => $period,
            'time_from' => $time_from,
            'time_to' => $time_to,
            'csrf_token' => CCsrfTokenHelper::get('webscenario'),
            'can_edit' => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
        ];

        $response = new CControllerResponseData($data);
        $response->setTitle(_('WebScenario: ') . $webscenario['name']);
        $this->setResponse($response);
    }

    /**
     * Converter período em segundos
     */
    private function parsePeriod(string $period): int {
        $periods = [
            '15m' => 900,
            '30m' => 1800,
            '1h' => 3600,
            '6h' => 21600,
            '12h' => 43200,
            '24h' => 86400,
            '7d' => 604800,
            '30d' => 2592000
        ];

        return $periods[$period] ?? 3600;
    }

    /**
     * Buscar estatísticas do webscenario (versão otimizada)
     */
    private function getWebScenarioStatistics(string $httptestid): array {
        
        return [
            'total_checks' => '-',
            'failed_checks' => '-',
            'success_rate' => '-',
            'average_response_time' => '-'
        ];
    }

    /**
     * Buscar itens do webscenario filtrando por host (otimizado)
     */
    private function getWebScenarioItems(string $httptestid): array {
        try {
            
            $webscenario = API::HttpTest()->get([
                'output' => ['hostid', 'name'],
                'httptestids' => [$httptestid]
            ]);

            if (empty($webscenario)) {
                
                return [];
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

            

            return $filtered_items;
        } catch (Exception $e) {
            
            return [];
        }
    }

    /**
     * Buscar valores atuais dos itens
     */
    private function getItemValues(array $items): array {
        $values = [];

        if (empty($items)) {
            
            return $values;
        }

        try {
            $itemids = array_column($items, 'itemid');
            

            if (!empty($itemids)) {
                $items_with_values = API::Item()->get([
                    'output' => ['itemid', 'lastvalue', 'lastclock', 'prevvalue'],
                    'itemids' => $itemids,
                    'selectValueMap' => 'extend'
                ]);

                

                foreach ($items_with_values as $item) {
                    $lastvalue = $item['lastvalue'] ?? '';
                    $lastclock = $item['lastclock'] ?? 0;

                    

                    $values[$item['itemid']] = [
                        'lastvalue' => $lastvalue !== '' ? $lastvalue : '-',
                        'lastclock' => $lastclock,
                        'prevvalue' => $item['prevvalue'] ?? ''
                    ];
                }
            }

            
        } catch (Exception $e) {
            
        }

        return $values;
    }

    /**
     * Calcular estatísticas baseado nos itens do webscenario
     */
    private function calculateStatistics(array $items): array {
        $stats = [
            'total_checks' => 0,
            'failed_checks' => 0,
            'success_rate' => '0%',
            'average_response_time' => '-'
        ];

        $fail_items = [];
        $time_items = [];

        foreach ($items as $item) {
            
            if (strpos($item['key_'], 'web.test.fail[') !== false) {
                $fail_items[] = $item;
            }
            
            elseif (strpos($item['key_'], 'web.test.time[') !== false) {
                $time_items[] = $item;
            }
        }

        

        
        if (!empty($fail_items)) {
            $total_fails = 0;
            $valid_checks = 0;

            foreach ($fail_items as $item) {
                if ($item['lastclock'] > 0) {
                    $valid_checks++;
                    if ($item['lastvalue'] > 0) {
                        $total_fails++;
                    }
                }
            }

            if ($valid_checks > 0) {
                $stats['total_checks'] = $valid_checks;
                $stats['failed_checks'] = $total_fails;
                $success_rate = (($valid_checks - $total_fails) / $valid_checks) * 100;
                $stats['success_rate'] = number_format($success_rate, 1) . '%';
            }
        }

        
        if (!empty($time_items)) {
            $total_time = 0;
            $valid_time_items = 0;

            foreach ($time_items as $item) {
                if ($item['lastclock'] > 0 && $item['lastvalue'] > 0) {
                    $total_time += $item['lastvalue'];
                    $valid_time_items++;
                }
            }

            if ($valid_time_items > 0) {
                $avg_time = $total_time / $valid_time_items;
                $stats['average_response_time'] = number_format($avg_time, 3) . ' s';
            }
        }

        

        return $stats;
    }
}