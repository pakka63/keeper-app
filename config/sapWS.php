<?php
return [
  'Oauth2' => [
      'tokenEndpoint' => env('SAP_TOKEN_ENDPOINT'),
      'clientId'      => env('SAP_CLIENT_ID'),
      'clientPwd'     => env('SAP_CLIENT_PWD'),
  ],
  'EndPoint'          => env('SAP_ENDPOINT')
];