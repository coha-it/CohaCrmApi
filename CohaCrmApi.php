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
            'Shopware_Controllers_Frontend_Forms_commitForm_Mail' => 'commitForm',
        ];
    }

    /*
        Overwrite the default commitForm function
        from Shopware's Contact-Formulars
    */
    public function commitForm($args) {
        $subject = $args->getSubject();
        $request = $subject->Request();
        $params = $request->getParams();

        $this->shopwareApiCall($params);
    }

    public function shopwareApiCall($params) {        
        // Get Date
        $now = new \DateTime();
        $timestamp = $now->getTimestamp();

        // Start Log to File
        $this->log('shopware-params', json_encode($params), $timestamp);


        // Get Cred
        $url = $this->getConfig('apiDomain') . '/api/people.json';
        $key = $this->getConfig('apiKey');

        // Fire API
        $dir = __DIR__;
        $sep = DIRECTORY_SEPARATOR;
        $phpFile = $dir . $sep . 'includes' . $sep . 'centralstation-php-api' . $sep . 'crm-api.php';

        // Try Include Existing PHP-File
        if(file_exists($phpFile)) {
            try {
                require_once($phpFile);
                $myCohaCrmApi = new \MyCohaCrmApi();
                $myCohaCrmApi->fireApiCall($url, $key, $params, $timestamp);
            } catch (\Throwable $th) {
                $this->log('shopware-error', $th);
            }
        } else {
            $this->log('shopware-error', "PHP-File $phpFile doesnt exist");
        }

    }

    public function getConfig($key) {
        return Shopware()->Config()->getByNamespace('CohaCrmApi', $key);
    }

    public function log($filename = 'default-error-log', $content = '', $timestamp = '') {
        // Build File-Content
        $filepath = __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . $filename . '.log';
        $filecontent = "[{$timestamp}]\r\n{$content}\r\n\r\n";

        // Put into File
        file_put_contents($filepath, $filecontent, FILE_APPEND);
    }

}
