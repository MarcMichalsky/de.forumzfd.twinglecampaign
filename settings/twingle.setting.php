<?php

return [
  'twingle_api_key' => [
    'name'           => 'twingle_api_key',
    'type'           => 'String',
    'default'        => '',
    'html_type'      => 'text',
    'title'          => ts('Twingle API Key'),
    'is_domain'      => 1,
    'is_contact'     => 0,
    'description'    => ts('The key that allows you to call the Twingle API'),
    `settings_pages` => ['remote' => ['weight' => 10]],
  ],
  'twingle_request_size' => [
    'name'           => 'twingle_request_size',
    'type'           => 'Integer',
    'default'        => '10',
    'html_type'      => 'text',
    'title'          => ts('Twingle Request Size'),
    'is_domain'      => 1,
    'is_contact'     => 0,
    'description'    => ts('How many items should be requested from the Twingle API at once?'),
    `settings_pages` => ['remote' => ['weight' => 11]],
  ]
];