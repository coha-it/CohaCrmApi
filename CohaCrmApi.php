<?php 

namespace CohaCrmApi;

use Doctrine\ORM\Tools\SchemaTool;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Theme\LessDefinition;
use Shopware\Components\Plugin\Context\ActivateContext;

class CohaCrmApi extends Plugin
{

    public static function getSubscribedEvents()
    {
        return [
            // 'Enlight_Controller_Action_PreDispatch_Frontend' => ['onFrontend',-100],
            // 'Enlight_Controller_Action_PreDispatch_Widgets' => ['onFrontend',-100],
            // 'Theme_Compiler_Collect_Plugin_Less' => 'addLessFiles',
            
            'Shopware_Controllers_Frontend_Forms_commitForm_Mail' => 'commitForm',

        ];
    }

    public function commitForm($args) {
        $subject = $args->getSubject();
        $request = $subject->Request();
        $params = $request->getParams();

        $this->buildApiCall($params);
    }

    public function logToFile($filename, $content, $timestamp) {
        // Build File-Content
        $filepath = __DIR__ . DIRECTORY_SEPARATOR . 'Logs' . DIRECTORY_SEPARATOR . $filename;
        $filecontent = "[{$timestamp}]\r\n{$content}\r\n\r\n";

        // Put into File
        file_put_contents($filepath, $filecontent, FILE_APPEND);
    }

    public function buildApiCall($params) {
        /*
            curl -v -H "Accept: application/json" -H "Content-type: application/json" 
            -X POST -d '{"person":{"first_name":"Marian","name":"Miller"}}'  
            https://accountname.centralstationcrm.net/api/people.json 

            {"person":{"id":1545412,"account_id":21,"user_id":null,"title":null,"gender":2,
            "first_name":"Marian","name":"Miller","background":null,"created_by_user_id":1781,
            "updated_by_user_id":null,
            "created_at":"2015-07-20T13:26:42.190Z","updated_at":"2015-07-20T13:26:42.190Z"}}
        */
        
        // Get Date
        $now = new \DateTime();
        $timestamp = $now->getTimestamp();

        // Start Log to File
        $this->logToFile('shopware-params.log', json_encode($params), $timestamp);

        
        // Generate Api Data
        $postfields = $this->generateParameters($params);
        $postfields['apikey'] = $this->getConfig('apiKey');
        $api_url = $this->getConfig('apiDomain') . '/api/people.json';

        // Fire API
        $this->fireApiCall($api_url, $postfields, $timestamp);
    }

    public function fireApiCall($url, $params, $timestamp)
    {
        $cURLConnection = curl_init();

        curl_setopt($cURLConnection, CURLOPT_POST, true);
        curl_setopt($cURLConnection, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($cURLConnection, CURLOPT_URL, $url);
        curl_setopt($cURLConnection, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cURLConnection, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($cURLConnection, CURLOPT_SSL_VERIFYHOST, 0);
        
        $response = curl_exec($cURLConnection);
        curl_close($cURLConnection);

        // Log to File
        $this->logToFile('crm-response.log', $response, $timestamp);

        // Maybe hier response code. wenn ungleich 200 dann log + email an it@ raus!
        // HTTP-Status-Code prüfen
        if (!curl_errno($cURLConnection)) {
            switch ($http_code = curl_getinfo($cURLConnection, CURLINFO_HTTP_CODE)) {
                case 200:
                    // Alles Ok!
                    break;
                default:
                    // echo 'Unerwarter HTTP-Code: ', $http_code, "\n";
                    break;
            }
        }
    }

    public function generateParameters($params) {
        return [
            'person' => [
                'title' => $params['anrede'] ?? '',
                'first_name' => $params['vorname'] ?? '',
                'name' => $params['nachname']  ?? '',
                'email' => $params['email'] ?? '',
                'company' => $params['unternehmen'] ?? '',
                'background' => ($params['betreff'] ?? '') . ' - ' . ($params['kommentar'] ?? ''),

                'positions_attributes' => [
                    [
                        'id' => '',
                        'company_id' => '',
                        'company_name' => $params['unternehmen'] ?? '',
                    ]
                ],

                'tags_attributes' => [
                    [
                        'id' => '',
                        'name' => 'Online-Shop Kontaktformular'
                    ],
                    [
                        'id' => '',
                        'name' => 'form-id-' . ($params['id'] ?? '')
                    ]
                ]
            ]
        ];
    }

    public function getConfig($key) {
        return Shopware()->Config()->getByNamespace('CohaCrmApi', $key);
    }

}
