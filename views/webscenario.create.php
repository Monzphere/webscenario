<?php

/**
 * @var CView $this
 * @var array $data
 */


$js_file_path = __DIR__ . '/js/webscenario.create.js.php';
ob_start();
include $js_file_path;
$js_processed = ob_get_clean();

$js_processed = preg_replace('/<script[^>]*>|<\/script>/i', '', $js_processed);

$form = (new CForm('post'))
    ->addItem((new CVar(CSRF_TOKEN_NAME, $data['csrf_token']))->removeId())
    ->setId('webscenario-create-form')
    ->setName('webscenario_create_form')
    ->addItem(getMessages());


$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));


$step_indicators = (new CDiv([
    (new CDiv([
        (new CSpan('1'))->addClass('step-number active'),
        (new CSpan(_('Basic Configuration')))->addClass('step-label')
    ]))->addClass('step-item active')->setAttribute('data-step', '1'),

    (new CDiv())->addClass('step-separator'),

    (new CDiv([
        (new CSpan('2'))->addClass('step-number'),
        (new CSpan(_('Web Steps')))->addClass('step-label')
    ]))->addClass('step-item')->setAttribute('data-step', '2'),

    (new CDiv())->addClass('step-separator'),

    (new CDiv([
        (new CSpan('3'))->addClass('step-number'),
        (new CSpan(_('Review & Create')))->addClass('step-label')
    ]))->addClass('step-item')->setAttribute('data-step', '3')
]))->addClass('wizard-steps');


$step1_content = (new CFormGrid())
    ->addItem([
        (new CLabel(_('Name'), 'name'))->setAsteriskMark(),
        new CFormField(
            (new CTextBox('name', $data['webscenario_data']['name'], false, 128))
                ->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
                ->setAriaRequired()
                ->setAttribute('autofocus', 'autofocus')
        )
    ])
    ->addItem([
        (new CLabel(_('Host'), 'host_search'))->setAsteriskMark(),
        new CFormField([
            (new CTextBox('host_search', '', false, 255))
                ->setId('host_search')
                ->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
                ->setAttribute('placeholder', _('Type to search for hosts...'))
                ->setAttribute('autocomplete', 'off')
                ->setAriaRequired(),
            new CInput('hidden', 'hostid', $data['hostid']),
            (new CDiv())->setId('host_search_results')->addClass('host-search-results')
        ])
    ])
    ->addItem([
        new CLabel(_('Update interval'), 'delay'),
        new CFormField(
            (new CTextBox('delay', $data['webscenario_data']['delay'], false, 255))
                ->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
        )
    ])
    ->addItem([
        new CLabel(_('Attempts'), 'retries'),
        new CFormField(
            (new CNumericBox('retries', $data['webscenario_data']['retries'], 2))
                ->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
        )
    ]);


$agent_select = (new CSelect('agent'))
    ->setId('agent')
    ->setFocusableElementId('agent-focusable')
    ->setValue($data['webscenario_data']['agent']);


$user_agents_all = [
    _('Microsoft Edge') => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36 Edge/80.0.361.66' => 'Microsoft Edge 80',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.102 Safari/537.36 Edge/18.18362' => 'Microsoft Edge 44'
    ],
    _('Mozilla Firefox') => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:73.0) Gecko/20100101 Firefox/73.0' => 'Firefox 73 (Windows)',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:73.0) Gecko/20100101 Firefox/73.0' => 'Firefox 73 (Linux)',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:73.0) Gecko/20100101 Firefox/73.0' => 'Firefox 73 (macOS)'
    ],
    _('Google Chrome') => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36' => 'Chrome 80 (Windows)',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36' => 'Chrome 80 (Linux)',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36' => 'Chrome 80 (macOS)'
    ],
    _('Others') => [
        'Zabbix' => 'Zabbix',
        '-1' => _('other').' ...'
    ]
];

foreach ($user_agents_all as $user_agent_group => $user_agents) {
    $agent_select->addOptionGroup((new CSelectOptionGroup($user_agent_group))
        ->addOptions(CSelect::createOptionsFromArray($user_agents))
    );
}

$step1_content->addItem([
    new CLabel(_('Agent'), $agent_select->getFocusableElementId()),
    new CFormField($agent_select)
]);


$authentication_select = (new CSelect('authentication'))
    ->setValue($data['webscenario_data']['authentication'])
    ->addOption(new CSelectOption('0', _('None')))
    ->addOption(new CSelectOption('1', _('Basic')))
    ->addOption(new CSelectOption('2', _('Digest')))
    ->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$step1_content->addItem([
    new CLabel(_('Authentication'), 'authentication'),
    new CFormField($authentication_select)
]);


$step1_content->addItem([
    new CLabel(_('HTTP user'), 'http_user'),
    new CFormField(
        (new CTextBox('http_user', $data['webscenario_data']['http_user'], false, 255))
            ->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
    )
]);

$step1_content->addItem([
    new CLabel(_('HTTP password'), 'http_password'),
    new CFormField(
        (new CPassBox('http_password', $data['webscenario_data']['http_password'], 255))
            ->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
    )
]);

$step1_content->addItem([
    new CLabel(_('Enabled'), 'status'),
    new CFormField(
        (new CCheckBox('status'))
            ->setChecked($data['webscenario_data']['status'] == '0')
    )
]);


$step1_content->addItem([
    new CLabel(_('HTTP proxy'), 'http_proxy'),
    new CFormField(
        (new CTextBox('http_proxy', '', false, 255))
            ->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
            ->setAttribute('placeholder', _('http://proxy.example.com:8080'))
    )
]);




$step2_content = (new CDiv([
    (new CDiv([
        (new CTag('h4', true, _('Web Scenario Steps'))),
        (new CDiv(_('Configure the HTTP requests that make up your web scenario.')))->addClass('step-description')
    ]))->addClass('step-header'),

    (new CDiv([
        (new CButton('add-step', _('Add Step')))
            ->addClass(ZBX_STYLE_BTN_ALT)
            ->setAttribute('onclick', 'addWebScenarioStep()'),

        (new CDiv())->setId('steps-container')->addClass('steps-list')
    ]))->addClass('steps-config')
]));


$step3_content = (new CDiv([
    (new CDiv([
        (new CTag('h4', true, _('Review Configuration'))),
        (new CDiv(_('Review your web scenario configuration before creating.')))->addClass('step-description')
    ]))->addClass('step-header'),

    (new CDiv())->setId('review-content')->addClass('review-content')
]));


$wizard_content = (new CDiv([
    $step_indicators,
    (new CDiv([
        (new CDiv($step1_content))->addClass('step-content active')->setAttribute('data-step', '1'),
        (new CDiv($step2_content))->addClass('step-content')->setAttribute('data-step', '2'),
        (new CDiv($step3_content))->addClass('step-content')->setAttribute('data-step', '3')
    ]))->addClass('wizard-content')
]))->addClass('webscenario-wizard');

$form->addItem($wizard_content);


$nav_buttons = (new CDiv([
    (new CButton('prev-step', _('Previous')))
        ->addClass(ZBX_STYLE_BTN_ALT)
        ->setAttribute('style', 'display: none;')
        ->setAttribute('onclick', 'previousStep()'),

    (new CButton('next-step', _('Next')))
        ->addClass(ZBX_STYLE_BTN_ALT)
        ->setAttribute('onclick', 'nextStep()'),

    (new CButton('create-webscenario', _('Create Web Scenario')))
        ->addClass(ZBX_STYLE_BTN_ALT)
        ->setAttribute('style', 'display: none;')
        ->setAttribute('onclick', 'createWebScenario()')
]))->addClass('wizard-navigation');

$form->addItem($nav_buttons);


$js_file_path = __DIR__ . '/js/webscenario.create.js.php';
ob_start();
include $js_file_path;
$js_processed = ob_get_clean();

$js_processed = preg_replace('/<script[^>]*>|<\/script>/i', '', $js_processed);


$js_init = $js_processed . '

    
    (function() {
        const style = document.createElement("style");
        style.textContent = `
            .host-search-results {
                position: absolute;
                border: 1px solid #ddd;
                border-top: none;
                max-height: 200px;
                overflow-y: auto;
                display: none;
                z-index: 1000;
                width: 453px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                margin-top: -1px;
            }
            [theme="blue-theme" ] .host-search-results {
                background: #ffffff;
            }
            [theme="dark-theme" ] .host-search-results {
                background: #2b2b2b;
            }
            .host-search-results.show {
                display: block;
            }
            .host-search-item {
                padding: 8px 12px;
                cursor: pointer;
            }
            #host_search.host-selected {
                border-color: #0275d8;
            }
            .host-search-no-results {
                padding: 8px 12px;
                color: #999;
                font-style: italic;
            }
        `;
        document.head.appendChild(style);
    })();

    
    if (typeof webScenarioWizard !== "undefined") {
        webScenarioWizard.init('.json_encode([
            'csrf_token' => $data['csrf_token'],
            'hostid' => $data['hostid'],
            'host_name' => $data['host_name'] ?? '',
            'webscenario_data' => $data['webscenario_data']
        ]).');
    }
';

$output = [
    'header' => _('Create Web Scenario'),
    'body' => $form->toString(),
    'buttons' => [],
    'script_inline' => $js_init  
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
    CProfiler::getInstance()->stop();
    $output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);