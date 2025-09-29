<?php

/**
 * @var CView $this
 * @var array $data
 */



$this->addJsFile('multiselect.js');
$this->addJsFile('class.calendar.js');
$this->addJsFile('gtlc.js');
$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('layout.mode.js');



(new CHorList())
    ->addItem(new CLink(_('Monitoring'), (new CUrl('zabbix.php'))->setArgument('action', 'dashboard.view')))
    ->addItem(new CSpan(_('Web Scenario Dashboard')))
    ->show();

$html_page = (new CHtmlPage())
    ->setTitle(_('Web Scenario Dashboard'))
    ->setDocUrl('https://www.zabbix.com/documentation/current/manual/web_monitoring');


global $page;
$web_layout_mode = $page['web_layout_mode'] ?? ZBX_LAYOUT_NORMAL;


$filter_form = (new CFilter((new CUrl('zabbix.php'))->setArgument('action', 'webscenario.dashboard')))
    ->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'webscenario.dashboard'))
    ->setProfile($data['timeline']['profileIdx'], $data['timeline']['profileIdx2'])
    ->setActiveTab(CProfile::get($data['timeline']['profileIdx'].'.active', 1))
    ->addTimeSelector($data['timeline']['from'], $data['timeline']['to'], $web_layout_mode != ZBX_LAYOUT_KIOSKMODE);

$html_page->addItem($filter_form);


$stats_dashboard = (new CDiv([
    (new CDiv([
        
        (new CDiv([
            (new CDiv([
                (new CSpan(_('OK Status')))->addClass('stat-card-title'),
                (new CSpan($data['statistics']['enabled_scenarios']))->addClass('stat-card-value stat-success')
            ]))->addClass('stat-card-content'),
            (new CDiv([
                (new CSpan(_('Scenarios OK')))->addClass('stat-card-label')
            ]))->addClass('stat-card-footer')
        ]))->addClass('stat-card stat-card-success'),
        
        
        (new CDiv([
            (new CDiv([
                (new CSpan(_('Problem Status')))->addClass('stat-card-title'),
                (new CSpan($data['statistics']['disabled_scenarios']))->addClass('stat-card-value stat-danger')
            ]))->addClass('stat-card-content'),
            (new CDiv([
                (new CSpan(_('Scenarios with Problems')))->addClass('stat-card-label')
            ]))->addClass('stat-card-footer')
        ]))->addClass('stat-card stat-card-danger'),
        
        
        (new CDiv([
            (new CDiv([
                (new CSpan(_('Average Response Time')))->addClass('stat-card-title'),
                (new CSpan(isset($data['statistics']['avg_response_time']) ? number_format($data['statistics']['avg_response_time'], 3) . 's' : '-'))
                    ->addClass('stat-card-value stat-info')
            ]))->addClass('stat-card-content'),
            (new CDiv([
                (new CSpan(_('Response Time')))->addClass('stat-card-label')
            ]))->addClass('stat-card-footer')
        ]))->addClass('stat-card stat-card-info'),
        
        
        (new CDiv([
            (new CDiv([
                (new CSpan(_('Total Steps')))->addClass('stat-card-title'),
                (new CSpan($data['statistics']['total_steps']))->addClass('stat-card-value stat-primary')
            ]))->addClass('stat-card-content'),
            (new CDiv([
                (new CSpan(_('Total Steps')))->addClass('stat-card-label')
            ]))->addClass('stat-card-footer')
        ]))->addClass('stat-card stat-card-primary')
    ]))->addClass('stats-cards-container')
]))->addClass('stats-dashboard-section');

$html_page->addItem($stats_dashboard);


if (!empty($data['webscenarios'])) {

    
    $all_response_items = [];
    foreach ($data['webscenarios'] as $scenario) {
        if (isset($scenario['steps']) && is_array($scenario['steps'])) {
            foreach ($scenario['steps'] as $step) {
                
                $items = API::Item()->get([
                    'output' => ['itemid', 'name', 'key_'],
                    'hostids' => [$scenario['hostid']],
                    'webitems' => true,
                    'filter' => [
                        'key_' => [
                            'web.test.time[' . $scenario['name'] . ',' . $step['name'] . ',resp]',
                            'web.test.time[' . $scenario['name'] . ',' . $step['name'] . ']'
                        ]
                    ]
                ]);

                foreach ($items as $item) {
                    $all_response_items[] = $item['itemid'];
                }
            }
        }
    }

    if (!empty($all_response_items)) {
        
        $items_for_chart = array_slice($all_response_items, 0, 20);

        
        $graph_dims = getGraphDims();
        $graph_dims['width'] = -50;
        $graph_dims['graphHeight'] = 200;

        
        $graph_url = (new CUrl('chart.php'))
            ->setArgument('itemids', $items_for_chart)
            ->setArgument('type', GRAPH_TYPE_NORMAL) 
            ->setArgument('width', $graph_dims['width'])
            ->setArgument('height', $graph_dims['graphHeight'])
            ->setArgument('from', $data['timeline']['from'])
            ->setArgument('to', $data['timeline']['to'])
            ->setArgument('profileIdx', $data['timeline']['profileIdx'])
            ->setArgument('profileIdx2', $data['timeline']['profileIdx2'])
            ->getUrl();

        
        $graph_screen = new CScreenBase([
            'resourcetype' => SCREEN_RESOURCE_GRAPH,
            'mode' => SCREEN_MODE_PREVIEW,
            'dataId' => 'webscenario_response_graph'
        ] + $data['timeline']);

        
        $graph_section = (new CDiv([
            (new CDiv(_('Response Time Trends')))->addClass('dashboard-section-header'),
            (new CDiv((new CDiv())
                ->setId('webscenario_response_graph_container')
                ->addClass(ZBX_STYLE_CENTER)
            ))
                ->addClass('flickerfreescreen')
                ->setId('flickerfreescreen_webscenario_response_graph')
                ->setAttribute('data-timestamp', time())
        ]))->addClass('dashboard-graph-section')->addStyle('margin-top:16px;margin-bottom:48px;');

        $html_page->addItem($graph_section);

        
        $time_control_data = [
            'id' => 'webscenario_response_graph',
            'containerid' => 'webscenario_response_graph_container',
            'src' => $graph_url,
            'objDims' => $graph_dims,
            'loadSBox' => 1,
            'loadImage' => 1
        ];

        
        zbx_add_post_js('timeControl.addObject("webscenario_response_graph", '.zbx_jsvalue($graph_screen->timeline).', '.
            zbx_jsvalue($time_control_data).');'
        );
        $graph_screen->insertFlickerfreeJs();
    }
}


if (!empty($data['problematic_scenarios'])) {
    $problems_section = (new CDiv([
        (new CDiv(_('Recent Problems')))->addClass('dashboard-section-header'),
        (new CDiv(
            [
                (new CTableInfo())
                    ->setHeader([
                        _('Web Scenario'),
                        _('Host'),
                        _('Actions')
                    ])
                    ->setHeadingColumn(0)
                    ->addRows(array_map(function($scenario) {
                        $host_name = isset($scenario['hosts'][0]) ? $scenario['hosts'][0]['name'] : '-';
                        return [
                            (new CSpan($scenario['name']))->addClass(ZBX_STYLE_RED),
                            $host_name,
                            new CLink(_('View'),
                                (new CUrl('zabbix.php'))
                                    ->setArgument('action', 'webscenario.view')
                                    ->setArgument('httptestid', $scenario['httptestid'])
                            )
                        ];
                    }, $data['problematic_scenarios']))
            ]
        ))->addClass('dashboard-problems-widget')
    ]))->addClass('dashboard-problems-section');

    $html_page->addItem($problems_section);
}


CScreenBuilder::insertScreenStandardJs($data['timeline']);


$table = (new CTableInfo())
    ->setHeader([
        _('Name'),
        _('URL'),
        _('Status'),
        _('Status Code'),
        _('Last check'),
        _('Response Time'),
        _('Steps'),
        _('Host')
    ]);

if (empty($data['webscenarios'])) {
    $table->setNoDataMessage(_('No web scenarios found'));
} else {
    foreach ($data['webscenarios'] as $scenario) {
        $name = new CLink($scenario['name'],
            (new CUrl('zabbix.php'))
                ->setArgument('action', 'webscenario.view')
                ->setArgument('httptestid', $scenario['httptestid'])
        );

        
        $url_display = '-';
        if (isset($scenario['steps']) && is_array($scenario['steps']) && !empty($scenario['steps'])) {
            $first_url = isset($scenario['steps'][0]['url']) ? $scenario['steps'][0]['url'] : '';
            $total_steps = count($scenario['steps']);

            if (!empty($first_url)) {
                if ($total_steps == 1) {
                    $url_display = $first_url;
                } else {
                    $additional_count = $total_steps - 1;
                    $url_display = $first_url . ' +' . $additional_count;
                }
            }
        }

        $host_name = isset($scenario['hosts'][0]) ? $scenario['hosts'][0]['name'] : '-';

        
        
        $failed_step_info = '';
        $has_failed_step = false;
        if (isset($scenario['steps']) && is_array($scenario['steps'])) {
            foreach ($scenario['steps'] as $index => $step) {
                if (isset($step['failed']) && $step['failed'] === true) {
                    $failed_step_info = ' - Step #' . ($index + 1) . ' (' . $step['name'] . ') Failed';
                    $has_failed_step = true;
                    break;
                }
            }
        }

        
        $is_problem = ($scenario['status'] != '0') || $has_failed_step;

        $status_icon = (!$is_problem)
            ? (new CTag('span', true))->addClass('icon-ok')->addStyle('color:#28a745;font-size:1.1em;vertical-align:middle;')
            : (new CTag('span', true))->addClass('icon-warning')->addStyle('color:#dc3545;font-size:1.1em;vertical-align:middle;');

        $status_text = (!$is_problem) ? _('OK') : _('Problem');

        $status = (!$is_problem)
            ? (new CSpan($status_text . $failed_step_info))->addClass('webscenario-status-enabled')->addItem($status_icon)
            : (new CSpan($status_text . $failed_step_info))->addClass('webscenario-status-disabled')->addItem($status_icon);

        
        $codes = new CList();
        if (isset($scenario['steps']) && is_array($scenario['steps'])) {
            foreach ($scenario['steps'] as $step) {
                if (isset($step['status_code']) && $step['status_code'] !== null && $step['status_code'] !== '') {
                    $code = $step['status_code'];

                    
                    $code_class = 'stat-danger'; 
                    if ($code == '200' || $code == 200) {
                        $code_class = 'stat-success'; 
                    }

                    
                    if (isset($step['failed']) && $step['failed'] === true) {
                        $code_class = 'stat-danger';
                    }

                    
                    

                    $codes->addItem(
                        (new CSpan($code))
                            ->addClass($code_class)
                    );
                }
            }
        }
        $status_code = !empty($codes->items) ? $codes : '-';

        
        $last_check = '-';
        if ($scenario['last_check']['lastclock'] > 0) {
            $last_check = date('Y-m-d H:i:s', $scenario['last_check']['lastclock']);
        }

        
        $response_times = new CList();
        if (isset($scenario['steps']) && is_array($scenario['steps'])) {
            foreach ($scenario['steps'] as $step) {
                if (isset($step['response_time'])) {
                    $response_times->addItem(
                        (new CSpan())
                            ->addItem((new CTag('span', true))->addClass('icon-time'))
                            ->addItem(' ' . number_format($step['response_time'], 3) . 's')
                    );
                }
            }
        }
        $response_time = !empty($response_times->items) ? $response_times : '-';

        
        $steps_count = (isset($scenario['steps']) && is_array($scenario['steps'])) ? count($scenario['steps']) : 0;

        
        $table->addRow([
            $name,
            (new CSpan($url_display))->addClass('url-display'),
            $status,
            $status_code,
            $last_check,
            $response_time,
            $steps_count,
            $host_name
        ])->addClass('webscenario-expandable');

        
        if (!empty($scenario['steps'])) {
            $detailsTable = (new CTable())
                ->addClass('list-table')
                ->setHeader([
                    _('Step'),
                    _('URL'),
                    _('Status Code'),
                    _('Response Time'),
                    _('Download Speed')
                ]);

            foreach ($scenario['steps'] as $step) {
                
                $status_code = isset($step['status_code']) ? $step['status_code'] : '-';

                
                $status_class = 'stat-danger'; 

                if ($status_code == '200' || $status_code == 200) {
                    $status_class = 'stat-success'; 
                }

                
                if (isset($step['failed']) && $step['failed'] === true) {
                    $status_class = 'stat-danger';
                }

                
                

                $status_display = (new CSpan($status_code))->addClass($status_class);

                
                $download_speed_display = '-';
                if (isset($step['download_speed']) && $step['download_speed'] !== null && $step['download_speed'] !== '') {
                    $download_speed_display = formatBytes((float)$step['download_speed']) . '/s';
                }

                
                $response_time_display = '-';
                if (isset($step['response_time']) && $step['response_time'] !== null && $step['response_time'] !== '') {
                    $response_time_display = number_format((float)$step['response_time'], 3) . 's';
                }

                $detailsTable->addRow([
                    $step['name'],
                    $step['url'],
                    $status_display,
                    $response_time_display,
                    $download_speed_display
                ]);
            }

            $table->addRow(
                (new CCol($detailsTable))
                    ->setColSpan(8)
                    ->addClass('webscenario-details')
            );
        }
    }
}


$table_header = (new CDiv([
    (new CSpan(_('Web Scenarios')))->addClass('dashboard-section-title'),
    (new CDiv([
        (new CTextBox('url_search', ''))
            ->setId('url_search')
            ->setWidth(250)
            ->setAttribute('placeholder', _('Search by URL (min 3 chars)...'))
            ->addClass('url-search-field')
    ]))->addClass('dashboard-search-container')
]))->addClass('dashboard-section-header dashboard-header-with-search');

$main_widget = (new CDiv([
    $table_header,
    $table
]))->addClass('dashboard-main-section')->addStyle('margin-top:48px;clear:both;');

$html_page->addItem($main_widget);


if ($data['paging']['total'] > $data['paging']['limit']) {
    $html_page->addItem(
        (new CDiv())
            ->addClass(ZBX_STYLE_TABLE_PAGING)
            ->addItem(getPagingLine($data['webscenarios'], $data['paging']['page'],
                (new CUrl('zabbix.php'))
                    ->setArgument('action', 'webscenario.dashboard')
                    ->setArgument('filter_url', $data['filter']['url'])
            ))
    );
}


CScreenBuilder::insertScreenStandardJs($data['timeline']);

$html_page->show();

if (isset($data['user']) && is_array($data['user']) && isset($data['user']['debug_mode']) && $data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
    CProfiler::getInstance()->stop();
    echo CProfiler::getInstance()->make()->toString();
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}
?>

<style>
.dashboard-header-with-search {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    margin-bottom: 16px !important;
}

.dashboard-section-title {
    font-size: 18px !important;
    font-weight: bold !important;
}

.dashboard-search-container {
    display: flex !important;
    align-items: center !important;
}

.url-search-field {
    border: 1px solid #d4d4d4 !important;
    border-radius: 4px !important;
    padding: 8px 12px !important;
    font-size: 13px !important;
}

.url-search-field:focus {
    border-color: #0066cc !important;
    outline: none !important;
    box-shadow: 0 0 0 2px rgba(0, 102, 204, 0.1) !important;
}

.webscenario-row-hidden {
    display: none !important;
}

.url-display {
    font-family: monospace;
    color: #0066cc;
    font-size: 12px;
    word-break: break-all;
}

</style>

<script>
function filterTableByUrl(searchText) {
    const searchTerm = searchText.trim();

    
    if (searchTerm === '') {
        const url = new URL(window.location.href);
        url.searchParams.delete('filter_url');
        url.searchParams.set('page', '1'); 
        window.location.href = url.toString();
        return;
    }

    
    const url = new URL(window.location.href);
    url.searchParams.set('filter_url', searchTerm);
    url.searchParams.set('page', '1'); 

    
    showLoadingIndicator();

    
    window.location.href = url.toString();
}

function showLoadingIndicator() {
    const table = document.querySelector('.list-table tbody');
    if (table) {
        table.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">Searching...</td></tr>';
    }
}


function searchWebScenarios(searchText) {
    const searchTerm = searchText.trim();

    if (searchTerm.length < 3 && searchTerm.length > 0) {
        return; 
    }

    
    if (window.searchTimeout) {
        clearTimeout(window.searchTimeout);
    }

    
    window.searchTimeout = setTimeout(() => {
        filterTableByUrl(searchTerm);
    }, 500);
}


document.addEventListener('DOMContentLoaded', function() {
    const searchField = document.getElementById('url_search');
    if (searchField) {
        
        const urlParams = new URLSearchParams(window.location.search);
        const currentFilter = urlParams.get('filter_url');
        if (currentFilter) {
            searchField.value = currentFilter;
        }

        
        searchField.addEventListener('input', function() {
            searchWebScenarios(this.value);
        });

        
        searchField.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                filterTableByUrl(this.value);
            }
        });
    }
});
</script>