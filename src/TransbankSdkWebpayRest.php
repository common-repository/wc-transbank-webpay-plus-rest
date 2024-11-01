<?php
namespace Transbank\WooCommerce\WebpayRest;
use Exception;
use Transbank\Webpay\Options;
use Transbank\Webpay\WebpayPlus;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCreateException;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCommitException;

/**
 * Class TransbankSdkWebpayRest
 * @package Transbank\WooCommerce\WebpayRest
 */
class TransbankSdkWebpayRest {

    /**
     * @var Options
     */
    var $options;

    /**
     * TransbankSdkWebpayRest constructor.
     * @param $config
     */
    function __construct($config) {
        if (isset($config)) {
            $environment = isset($config["MODO"]) ? $config["MODO"] : 'TEST';
            $this->options = ($environment != 'TEST') ? new Options($config["API_KEY"], $config["COMMERCE_CODE"]) : Options::defaultConfig();
            $this->options->setIntegrationType($environment);
        }
    }

    /**
     * @param $amount
     * @param $sessionId
     * @param $buyOrder
     * @param $returnUrl
     * @return array
     * @throws Exception
     */
    public function createTransaction($amount, $sessionId, $buyOrder, $returnUrl) {
        $result = array();
        try{

            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');

            $initResult = WebpayPlus\Transaction::create($buyOrder, $sessionId, $amount, $returnUrl, $this->options);

            if (isset($initResult) && isset($initResult->url) && isset($initResult->token)) {
                $result = array(
                    "url" => $initResult->url,
                    "token_ws" => $initResult->token
                );
            } else {
                throw new Exception('No se ha creado la transacción para, amount: ' . $amount . ', sessionId: ' . $sessionId . ', buyOrder: ' . $buyOrder);
            }
        } catch(Exception $e) {

            $result = array(
                "error" => 'Error al crear la transacción',
                "detail" => $e->getMessage()
            );
        }
        return $result;
    }

    /**
     * @param $tokenWs
     * @return array|WebpayPlus\TransactionCommitResponse
     * @throws Exception
     */
    public function commitTransaction($tokenWs) {
        try{
            if ($tokenWs == null) {
                throw new Exception("El token webpay es requerido");
            }
            return WebpayPlus\Transaction::commit($tokenWs,$this->options);
        } catch(TransactionCommitException $e) {
            $result = array(
                "error" => 'Error al confirmar la transacción',
                "detail" => $e->getMessage()
            );
        }
        return $result;
    }
}
