<?php

$html_page = (new CHtmlPage())
    ->setTitle(_('WebScenario Manager'));


if ($data['can_edit']) {
    $html_page->setControls(
        (new CTag('nav', true,
            (new CList())
                ->addItem((new CButton('create', _('Create web scenario')))
                    ->onClick('openWebScenarioCreatePopup()')
                    ->addClass(ZBX_STYLE_BTN_ALT)
                )
        ))->setAttribute('aria-label', _('Content controls'))
    );
}


$filter_form = (new CForm('get'))
    ->setName('zbx_filter')
    ->setAttribute('aria-label', _('Main filter'))
    ->addVar('action', 'webscenario.list');

$filter_column1 = (new CFormList())
    ->addRow(_('Name'),
        (new CTextBox('filter_name', $data['filter']['name']))
            ->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
            ->setAttribute('autofocus', 'autofocus')
    );


$host_select = (new CSelect('filter_hostid'))
    ->setId('filter_hostid')
    ->setValue($data['filter']['hostid'])
    ->setFocusableElementId('filter_hostid');

$host_select->addOption(new CSelectOption(0, _('- any -')));
foreach ($data['hosts'] as $host) {
    $host_select->addOption(new CSelectOption($host['hostid'], $host['name']));
}

$filter_column1->addRow(_('Host'), $host_select);

$filter_column2 = (new CFormList())
    ->addRow(_('Status'),
        (new CRadioButtonList('filter_status', (int) $data['filter']['status']))
            ->addValue(_('Any'), -1)
            ->addValue(_('Enabled'), 0)
            ->addValue(_('Disabled'), 1)
            ->setModern(true)
    );

$filter_form->addItem(
    (new CDiv([
        (new CDiv($filter_column1))->addClass(ZBX_STYLE_CELL),
        (new CDiv($filter_column2))->addClass(ZBX_STYLE_CELL)
    ]))
        ->addClass(ZBX_STYLE_ROW)
);

$filter_form->addItem(
    (new CSubmitButton(_('Apply'), 'filter_set', '1'))
        ->addClass(ZBX_STYLE_BTN_LINK)
);

$filter_form->addItem(
    (new CRedirectButton(_('Reset'),
        (new CUrl('webscenario.list'))->setArgument('filter_rst', 1)->getUrl()
    ))->addClass(ZBX_STYLE_BTN_LINK)
);

$filter = (new CFilter())
    ->addItem($filter_form);





$html_page->addItem($filter);


$stats_items = [];
$stats_items[] = (new CCol(_('Total').': '.$data['statistics']['total']))->addClass(ZBX_STYLE_NOWRAP);
$stats_items[] = (new CCol(_('Enabled').': '.$data['statistics']['enabled']))->addClass(ZBX_STYLE_NOWRAP);
$stats_items[] = (new CCol(_('Disabled').': '.$data['statistics']['disabled']))->addClass(ZBX_STYLE_NOWRAP);

$stats_table = (new CTable())
    ->addRow($stats_items)
    ->addClass('statistics');

$html_page->addItem($stats_table);


$form = (new CForm())->setName('webscenario_form');

$table = (new CTableInfo())
    ->setHeader([
        (new CColHeader(
            (new CCheckBox('all_webscenarios'))
                ->onClick("checkAll('".$form->getName()."', 'all_webscenarios', 'webscenario_ids');")
        ))->addClass(ZBX_STYLE_CELL_WIDTH),
        make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'],
            (new CUrl('webscenario.list'))->getUrl()
        ),
        _('Host'),
        _('Steps'),
        _('Status'),
        _('Last check'),
        _('Delay'),
        _('Actions')
    ]);

if (empty($data['webscenarios'])) {
    $table->addRow([
        new CCol(_('No data found'), null, 8)
    ]);
} else {
    foreach ($data['webscenarios'] as $webscenario) {
        $name = new CLink($webscenario['name'],
            (new CUrl('webscenario.edit'))
                ->setArgument('httptestid', $webscenario['httptestid'])
        );

        $host_name = isset($webscenario['hosts'][0]) ? $webscenario['hosts'][0]['name'] : '-';

        $steps_count = count($webscenario['steps']);

        $status = ($webscenario['status'] == 0)
            ? (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN)
            : (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);

        
        $last_check = '';
        if (isset($webscenario['last_check']) && $webscenario['last_check']['lastclock'] > 0) {
            $last_check = (new CSpan(zbx_date2age($webscenario['last_check']['lastclock'])))
                ->addClass(ZBX_STYLE_CURSOR_POINTER)
                ->setHint(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $webscenario['last_check']['lastclock']), '', true, '', 0);

            
            if ($webscenario['last_check']['status'] === 'PROBLEM') {
                $last_check = [
                    (new CSpan('✗'))->addClass(ZBX_STYLE_RED),
                    ' ',
                    $last_check
                ];
            }
        }

        
        $actions = [];

        if ($data['can_edit']) {
            
            $actions[] = new CLink(_('Steps'),
                (new CUrl('httpconf.php'))
                    ->setArgument('form', 'update')
                    ->setArgument('httptestid', $webscenario['httptestid'])
                    ->setArgument('hostid', $webscenario['hostid'])
                    ->setArgument('context', 'host')
                    ->setArgument('tab', 'step')
            );
        }

        
        $actions[] = new CLink(_('View'),
            (new CUrl('zabbix.php'))
                ->setArgument('action', 'webscenario.view')
                ->setArgument('httptestid', $webscenario['httptestid'])
        );

        
        $actions_formatted = [];
        foreach ($actions as $i => $action) {
            if ($i > 0) {
                $actions_formatted[] = ' | ';
            }
            $actions_formatted[] = $action;
        }

        $table->addRow([
            new CCheckBox('webscenario_ids['.$webscenario['httptestid'].']', $webscenario['httptestid']),
            (new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
            $host_name,
            $steps_count,
            $status,
            $last_check,
            $webscenario['delay'],
            (new CCol($actions_formatted))->addClass(ZBX_STYLE_NOWRAP)
        ]);
    }
}


$paging = CPagerHelper::paginate($data['page'], $data['webscenarios'], ZBX_SORT_UP,
    (new CUrl('webscenario.list')));

$form->addItem([
    $table,
    $paging,
    new CActionButtonList('action', 'webscenario_ids', [
        'webscenario.enable' => ['name' => _('Enable'), 'confirm' => _('Enable selected web scenarios?')],
        'webscenario.disable' => ['name' => _('Disable'), 'confirm' => _('Disable selected web scenarios?')],
        'webscenario.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected web scenarios?')]
    ], 'webscenario')
]);

$html_page->addItem($form);

$html_page->show();
?>

<script type="text/javascript">
function openWebScenarioCreatePopup() {
    PopUp('webscenario.create', {}, {
        dialogueid: 'webscenario-create',
        dialogue_class: 'modal-popup-large'
    });
}
</script>