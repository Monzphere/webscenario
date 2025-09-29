<?php

$html_page = (new CHtmlPage())
    ->setTitle(_('WebScenario: ') . $data['webscenario']['name']);


if ($data['can_edit']) {
    $html_page->setControls(
        (new CTag('nav', true,
            (new CList())
                ->addItem(new CRedirectButton(_('Edit'),
                    (new CUrl('httpconf.php'))
                        ->setArgument('form', 'update')
                        ->setArgument('httptestid', $data['webscenario']['httptestid'])
                        ->setArgument('hostid', $data['webscenario']['hostid'])
                        ->setArgument('context', 'host')
                ))
                ->addItem(new CRedirectButton(_('Back to list'),
                    (new CUrl('zabbix.php'))->setArgument('action', 'webscenario.list')
                ))
        ))->setAttribute('aria-label', _('Content controls'))
    );
}


$host_name = isset($data['webscenario']['hosts'][0]) ? $data['webscenario']['hosts'][0]['name'] : '-';
$status_label = ($data['webscenario']['status'] == 0) ? _('Enabled') : _('Disabled');
$status_class = ($data['webscenario']['status'] == 0) ? 'stat-success' : 'stat-danger';

$info_dashboard = (new CDiv([
    new CTag('h3', true, _('Information')),
    (new CDiv([
        
        (new CDiv([
            (new CDiv(_('WebScenario Name')))->addClass('webscenario-stat-label'),
            (new CDiv($data['webscenario']['name']))->addClass('webscenario-stat-value stat-primary'),
            (new CDiv(_('Host: ') . $host_name))->addClass('webscenario-stat-description')
        ]))->addClass('webscenario-stat-card'),

        
        (new CDiv([
            (new CDiv(_('Status')))->addClass('webscenario-stat-label'),
            (new CDiv($status_label))->addClass("webscenario-stat-value {$status_class}"),
            (new CDiv(_('Update interval: ') . $data['webscenario']['delay']))->addClass('webscenario-stat-description')
        ]))->addClass('webscenario-stat-card'),

        
        (new CDiv([
            (new CDiv(_('Configuration')))->addClass('webscenario-stat-label'),
            (new CDiv(count($data['webscenario']['steps'])))->addClass('webscenario-stat-value stat-info'),
            (new CDiv(_('Steps configured')))->addClass('webscenario-stat-description'),
            (new CDiv(_('Agent: ') . $data['webscenario']['agent']))->addClass('webscenario-stat-description')
        ]))->addClass('webscenario-stat-card')
    ]))->addClass('webscenario-stats-grid')
]))->addClass('webscenario-dashboard-card');

$html_page->addItem($info_dashboard);


$stats = $data['statistics'];


$success_rate_value = (float) str_replace('%', '', $stats['success_rate']);
$success_rate_color = $success_rate_value >= 95 ? 'stat-success' :
                     ($success_rate_value >= 80 ? 'stat-warning' : 'stat-danger');

$progress_color = $success_rate_value >= 95 ? 'progress-success' :
                 ($success_rate_value >= 80 ? 'progress-warning' : 'progress-danger');

$stats_dashboard = (new CDiv([
    new CTag('h3', true, _('Statistics')),
    (new CDiv([
        
        (new CDiv([
            (new CDiv(_('Total Checks')))->addClass('webscenario-stat-label'),
            (new CDiv($stats['total_checks']))->addClass('webscenario-stat-value stat-primary'),
            (new CDiv(_('Monitoring checks performed')))->addClass('webscenario-stat-description')
        ]))->addClass('webscenario-stat-card'),

        
        (new CDiv([
            (new CDiv(_('Failed Checks')))->addClass('webscenario-stat-label'),
            (new CDiv($stats['failed_checks']))->addClass('webscenario-stat-value stat-danger'),
            (new CDiv(_('Failed monitoring attempts')))->addClass('webscenario-stat-description')
        ]))->addClass('webscenario-stat-card'),

        
        (new CDiv([
            (new CDiv(_('Success Rate')))->addClass('webscenario-stat-label'),
            (new CDiv($stats['success_rate']))->addClass("webscenario-stat-value {$success_rate_color}"),
            (new CDiv([
                (new CDiv(
                    (new CDiv(''))->addClass("webscenario-progress-fill {$progress_color}")
                        ->setAttribute('style', "width: {$success_rate_value}%"),
                ))->addClass('webscenario-progress-bar')
            ])),
            (new CDiv(_('Monitoring success ratio')))->addClass('webscenario-stat-description')
        ]))->addClass('webscenario-stat-card'),

        
        (new CDiv([
            (new CDiv(_('Avg Response Time')))->addClass('webscenario-stat-label'),
            (new CDiv($stats['average_response_time']))->addClass('webscenario-stat-value stat-info'),
            (new CDiv(_('Average page load time')))->addClass('webscenario-stat-description')
        ]))->addClass('webscenario-stat-card')
    ]))->addClass('webscenario-stats-grid')
]))->addClass('webscenario-dashboard-card');

$html_page->addItem($stats_dashboard);


if (!empty($data['webscenario']['steps'])) {
    $steps_table = (new CTableInfo())
        ->setHeader([
            _('Step'),
            _('Name'),
            _('URL'),
            _('Status codes'),
            _('Timeout'),
            _('Required')
        ]);

    foreach ($data['webscenario']['steps'] as $step) {
        $steps_table->addRow([
            $step['no'],
            $step['name'],
            (new CDiv($step['url']))->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
            $step['status_codes'],
            $step['timeout'],
            $step['required'] ?: '-'
        ]);
    }

    $html_page->addItem(
        (new CFormFieldset(_('Steps')))
            ->addItem($steps_table)
    );
}


if (!empty($data['items'])) {
    $items_table = (new CTableInfo())
        ->setHeader([
            _('Name'),
            _('Key'),
            _('Last value'),
            _('Last check'),
            _('Actions')
        ]);

    foreach ($data['items'] as $item) {
        
        $last_value = '-';
        $last_check = '';

        
        

        if (isset($data['item_values'][$item['itemid']])) {
            $item_data = $data['item_values'][$item['itemid']];
            $raw_value = $item_data['lastvalue'];

            

            
            if ($raw_value !== '' && $raw_value !== null && $raw_value !== '-') {
                $last_value = $raw_value;

                
                if (strpos($item['key_'], 'web.test.time') !== false) {
                    $last_value = number_format($last_value, 3) . ' s';
                } elseif (strpos($item['key_'], 'web.test.fail') !== false) {
                    $last_value = $last_value == '0' ?
                        (new CSpan(_('OK')))->addClass(ZBX_STYLE_GREEN) :
                        (new CSpan(_('FAIL')))->addClass(ZBX_STYLE_RED);
                } elseif (strpos($item['key_'], 'web.test.in') !== false) {
                    $last_value = convertUnits(['value' => $last_value, 'units' => 'B']);
                } elseif (strpos($item['key_'], 'web.test.speed') !== false) {
                    $last_value = convertUnits(['value' => $last_value, 'units' => 'Bps']);
                } elseif (strpos($item['key_'], 'web.test.rspcode') !== false) {
                    $last_value = $last_value;  
                }
            } else {
                
                $last_value = '-';
            }

            if ($item_data['lastclock'] > 0) {
                $last_check = (new CSpan(zbx_date2age($item_data['lastclock'])))
                    ->addClass(ZBX_STYLE_CURSOR_POINTER)
                    ->setHint(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $item_data['lastclock']), '', true, '', 0);
            }
        } else {
            
        }

        $actions = [
            new CLink(_('Graph'),
                (new CUrl('history.php'))
                    ->setArgument('itemids[]', $item['itemid'])
            )
        ];

        $actions_formatted = [];
        foreach ($actions as $i => $action) {
            if ($i > 0) {
                $actions_formatted[] = ' | ';
            }
            $actions_formatted[] = $action;
        }

        $items_table->addRow([
            $item['name'],
            (new CSpan($item['key_']))->addClass('monospace-font'),
            $last_value,
            $last_check,
            (new CCol($actions_formatted))->addClass(ZBX_STYLE_NOWRAP)
        ]);
    }

    $html_page->addItem(
        (new CFormFieldset(_('Items/Metrics') . ' (' . count($data['items']) . ')'))
            ->addItem($items_table)
    );
}


if (empty($data['items'])) {
    $html_page->addItem(
        (new CFormFieldset(_('Information')))
            ->addItem(_('No items found for this web scenario. Items are automatically created when the web scenario runs.'))
    );
}

$html_page->show();