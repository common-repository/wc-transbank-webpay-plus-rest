<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use DateTime;
use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\WebpayRest\TransbankWebpayOrders;
use Transbank\WooCommerce\WebpayRest\TransbankSdkWebpayRest;
use WC_Order;

class ResponseController
{
    /**
     * @var array
     */
    protected $pluginConfig;
    /**
     * ResponseController constructor.
     *
     * @param array $pluginConfig
     */
    public function __construct(array $pluginConfig)
    {
        $this->pluginConfig = $pluginConfig;
    }

    public function response($postData)
    {
        $token_ws = $this->getTokenWs($postData);
        $webpayTransaction = TransbankWebpayOrders::getByToken($token_ws);

        $wooCommerceOrder = $this->getWooCommerceOrderById($webpayTransaction->order_id);

        if ($this->transactionWasCanceledByUser()) {
            SessionMessageHelper::set('La transacción ha sido cancelada por el usuario', 'error');
            if ($webpayTransaction->status  !== TransbankWebpayOrders::STATUS_INITIALIZED || $wooCommerceOrder->is_paid()) {
                $wooCommerceOrder->add_order_note('El usuario canceló la transacción en el formulario de pago, pero esta orden ya estaba pagada o en un estado diferente a INICIALIZADO');
                return wp_safe_redirect($wooCommerceOrder->get_cancel_order_url());
            }
            $this->setOrderAsCancelledByUser($wooCommerceOrder, $webpayTransaction);
            return wp_safe_redirect($wooCommerceOrder->get_cancel_order_url());
        }

        if ($wooCommerceOrder->is_paid()) {
            // TODO: Revisar porqué no se muestra el mensaje de abajo. H4x
            //SessionMessageHelper::set('Orden <strong>ya ha sido pagada</strong>.', 'notice');
            $wooCommerceOrder->add_order_note('El usuario intentó pagar esta orden cuando ya ' .
                'estaba pagada, no se ejecutó captura del pago y este se debería reversar en ' .
                'los próximos días.');
            return wp_safe_redirect($wooCommerceOrder->get_checkout_order_received_url());
        }

        if (!$wooCommerceOrder->needs_payment()) {
            // TODO: Revisar porqué no se muestra el mensaje de abajo.
            //SessionMessageHelper::set('El estado de la orden no permite que sea pagada. Comuníquese con la tienda.', 'error');
            $wooCommerceOrder->add_order_note('El usuario intentó pagar la orden cuando estaba en estado: ' .
                $wooCommerceOrder->get_status() . ".\n" .
                'No se ejecutó captura del pago y este se debería reversar en los próximos días.'
            );
            return wp_safe_redirect($wooCommerceOrder->get_checkout_order_received_url());
        }

        $transbankSdkWebpay = new TransbankSdkWebpayRest($this->pluginConfig);
        $result = $transbankSdkWebpay->commitTransaction($token_ws);
        if ($this->transactionIsApproved($result) && $this->validateTransactionDetails($result, $webpayTransaction)) {
            $this->completeWooCommerceOrder($wooCommerceOrder, $result, $webpayTransaction);
            return wp_redirect($wooCommerceOrder->get_checkout_order_received_url());
        }

        $this->setWooCommerceOrderAsFailed($wooCommerceOrder, $webpayTransaction, $result);
        return wp_redirect($wooCommerceOrder->get_checkout_order_received_url());
    }

    /**
     * @param $data
     * @return |null
     */
    protected function getTokenWs($data)
    {
        $token_ws = isset($data["token_ws"]) ? $data["token_ws"] : (isset($data['TBK_TOKEN']) ? $data['TBK_TOKEN'] : null);
        if (!isset($token_ws)) {
            $this->throwError('No se encontró el token');
        }

        return $token_ws;
    }
    /**
     * @param $orderId
     * @return WC_Order
     */
    protected function getWooCommerceOrderById($orderId)
    {
        $wooCommerceOrder = new WC_Order($orderId);

        return $wooCommerceOrder;
    }

    protected function throwError($msg)
    {
        $error_message = "Estimado cliente, le informamos que su orden termin&oacute; de forma inesperada: <br />" . $msg;
        wc_add_notice(__('ERROR: ', 'transbank_webpay') . $error_message, 'error');
        die();
    }

    /**
     * @param WC_Order $wooCommerceOrder
     * @param array $result
     * @param $webpayTransaction
     */
    protected function completeWooCommerceOrder(WC_Order $wooCommerceOrder, $result, $webpayTransaction)
    {
        $wooCommerceOrder->add_order_note(__('Pago exitoso con Webpay Plus', 'transbank_webpay'));
        /** CORREO */

        $to = get_bloginfo('admin_email');
        $subject = 'Comprobante de Pago Webpay Plus';

        //Datos de Transbank
        $tbk_invoice_buyOrder = $result->buyOrder;
        $tbk_invoice_authorizationCode = $result->detailOutput->authorizationCode;

        $date_accepted = new DateTime($result->transactionDate);
        $tbk_invoice_transactionDate = $date_accepted->format('d-m-Y H:i:s');

        $tbk_invoice_cardNumber = $result->cardDetail->cardNumber;

        $paymentTypeCode = $result->detailOutput->paymentTypeCode;

        switch ($paymentTypeCode) {
            case "VD":
                $tbk_invoice_paymenCodeResult = "Venta Deb&iacute;to";
                break;
            case "VN":
                $tbk_invoice_paymenCodeResult = "Venta Normal";
                break;
            case "VC":
                $tbk_invoice_paymenCodeResult = "Venta en cuotas";
                break;
            case "SI":
                $tbk_invoice_paymenCodeResult = "3 cuotas sin inter&eacute;s";
                break;
            case "S2":
                $tbk_invoice_paymenCodeResult = "2 cuotas sin inter&eacute;s";
                break;
            case "NC":
                $tbk_invoice_paymenCodeResult = "N cuotas sin inter&eacute;s";
                break;
            default:
                $tbk_invoice_paymenCodeResult = "—";
                break;
        }

        $tbk_invoice_amount = number_format($result->detailOutput->amount, 0, ',', '.');
        $tbk_invoice_sharesNumber = $result->detailOutput->sharesNumber;

        //Datos Cliente
        $tbk_invoice_nombre = $wooCommerceOrder->get_billing_first_name() . ' ' . $wooCommerceOrder->get_billing_last_name();
        $tbk_invoice_correo = $wooCommerceOrder->get_billing_email();

        $formato = '<ul><li><strong>Respuesta de la Transacción</strong>: ACEPTADO</li><li><strong>Orden de Compra:</strong> %s</li><li><strong>Codigo de Autorización:</strong> %s</li><li><strong>Fecha y Hora de la Transacción:</strong> %s</li><li><strong>Tarjeta de Crédito:</strong> ···· ···· ···· %s</li><li><strong>Tipo de Pago:</strong> %s</li><li><strong>Monto Compra: </strong>$%s</li><li><strong>Número de Cuotas:</strong> %s</li></ul>';

        $wooCommerceOrder->add_order_note(sprintf($formato, $tbk_invoice_buyOrder, $tbk_invoice_authorizationCode, $tbk_invoice_transactionDate, $tbk_invoice_cardNumber, $tbk_invoice_paymenCodeResult, $tbk_invoice_amount, $tbk_invoice_sharesNumber));

        $body = <<<EOT
						<!DOCTYPE html>
						<html lang="en">

						<head>
						    <meta charset="UTF-8">
						    <meta name="viewport" content="width=device-width, initial-scale=1.0">
						    <meta http-equiv="X-UA-Compatible" content="ie=edge">
						    <title>Comprobante Webpay Plus</title>
						</head>

						<body style="padding: 30px 15% 0; font-family: Arial, Helvetica, sans-serif; font-size: 0.85rem;">

						    <div style="width: 100%; text-align: center;">
						        <img src="https://payment.swo.cl/host/mail" width="250px" />
						    </div>
						    <div>
						        <h1 style="font-size: 25px; text-transform: uppercase; text-align: center;">Notificación de Pago</h1>
						        <p>Estimado usuario, se ha realizado un pago con los siguientes datos:</p>
						        <hr />
						        <h3>Detalle de Transacción</h3>
						        <table style="width: 100%;" border="1">
						            <tbody>
						                <tr>
						                    <td style="width: 50%"><strong>Respuesta de la Transacción</strong></td>
						                    <td style="width: 50%"><strong>ACEPTADO</strong></td>
						                </tr>
						                <tr>
						                    <td>Orden de Compra</td>
						                    <td>$tbk_invoice_buyOrder</td>
						                </tr>
						                <tr>
						                    <td>Codigo de Autorización</td>
						                    <td>$tbk_invoice_authorizationCode</td>
						                </tr>
						                <tr>
						                    <td>Fecha y Hora de la Transacción</td>
						                    <td>$tbk_invoice_transactionDate</td>
						                </tr>
						                <tr>
						                    <td>Tarjeta de Crédito</td>
						                    <td>···· ···· ···· $tbk_invoice_cardNumber</td>
						                </tr>
						                <tr>
						                    <td>Tipo de Pago</td>
						                    <td>$tbk_invoice_paymenCodeResult</td>
						                </tr>
						                <tr>
						                    <td>Monto Compra</td>
						                    <td>$$tbk_invoice_amount</td>
						                </tr>
						                <tr>
						                    <td>Número de Cuotas</td>
						                    <td>$tbk_invoice_sharesNumber</td>
						                </tr>
						            </tbody>
						        </table>
						        <hr />
						        <h3>Detalle de Orden</h3>
						        <table style="width: 100%;" border="1">
						            <tbody>
						                <tr>
						                    <td style="width: 50%">Nombre de Cliente:</td>
						                    <td style="width: 50%">$tbk_invoice_nombre</td>
						                </tr>
						                <tr>
						                    <td>Correo Electrónico</td>
						                    <td>$tbk_invoice_correo</td>
						                </tr>
						            </tbody>
						        </table>
						        <p>La información contenida en este correo electrónico es informatica y ha sido enviada como respaldo de la transacción
						            cursada con tarjta de crédito o RedCompra. El siguiente pago ha sido consignado directamente en la cuenta del
						            usuario realizando las actualizaciones correspondientes a la orden de compra indicada.
						        </p>
						    </div>
						</body>

						</html>
EOT;

        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail( $to, $subject, $body, $headers );

        /** END CORREO */

        wc_add_notice(__('Pago recibido satisfactoriamente', 'transbank_webpay'));
        TransbankWebpayOrders::update($webpayTransaction->id,
            ['status' => TransbankWebpayOrders::STATUS_APPROVED, 'transbank_response' => json_encode($result)]);

        $wooCommerceOrder->payment_complete();
        $final_status = $this->pluginConfig['STATUS_AFTER_PAYMENT'];
        if ($final_status) {
            $wooCommerceOrder->update_status($final_status);
        }
    }
    /**
     * @param WC_Order $wooCommerceOrder
     * @param array $result
     * @param $webpayTransaction
     */
    protected function setWooCommerceOrderAsFailed(WC_Order $wooCommerceOrder, $webpayTransaction, $result = null)
    {
        $_SESSION['woocommerce_order_failed'] = true;
        $wooCommerceOrder->add_order_note(__('Pago rechazado', 'transbank_webpay'));
        $wooCommerceOrder->update_status('failed');
        if ($result !== null) {
            $wooCommerceOrder->add_order_note(json_encode($result, JSON_PRETTY_PRINT));
        }

        TransbankWebpayOrders::update($webpayTransaction->id,
            ['status' => TransbankWebpayOrders::STATUS_FAILED, 'transbank_response' => json_encode($result)]);
    }

    /**
     * @param array $result
     * @return bool
     */
    protected function transactionIsApproved($result)
    {
        if (!isset($result->responseCode)) {
            return false;
        }

        return (int) $result->responseCode === 0;
    }
    /**
     * @param array $result
     * @param $webpayTransaction
     * @return bool
     */
    protected function validateTransactionDetails($result, $webpayTransaction)
    {
        if (!isset($result->responseCode)) {
            return false;
        }

        return $result->buyOrder == $webpayTransaction->buy_order && $result->sessionId == $webpayTransaction->session_id && $result->amount == $webpayTransaction->amount;
    }
    /**
     * @param array $result
     * @return array
     * @throws \Exception
     */
    public function getTransactionDetails($result)
    {
        $paymentTypeCode = isset($result->paymentTypeCode) ? $result->paymentTypeCode : null;
        $authorizationCode = isset($result->authorizationCode) ? $result->authorizationCode : null;
        $amount = isset($result->amount) ? $result->amount : null;
        $sharesNumber = isset($result->installmentsNumber) ? $result->installmentsNumber : null;
        $sharesAmount = isset($result->installmentsAmount) ? $result->installmentsAmount : null;
        $responseCode = isset($result->responseCode) ? $result->responseCode : null;
        if ($responseCode == 0) {
            $transactionResponse = "Transacción Aprobada";
        } else {
            $transactionResponse = "Transacción Rechazada";
        }
        $paymentCodeResult = "Sin cuotas";
        if ($this->pluginConfig) {
            if (array_key_exists('VENTA_DESC', $this->pluginConfig)) {
                if (array_key_exists($paymentTypeCode, $this->pluginConfig['VENTA_DESC'])) {
                    $paymentCodeResult = $this->pluginConfig['VENTA_DESC'][$paymentTypeCode];
                }
            }
        }

        $paymentType = __("Crédito", 'transbank_webpay');
        if ($paymentTypeCode == "VD") {
            $paymentType = __("Débito", 'transbank_webpay');
        }

        $transactionDate = isset($result->transactionDate) ? $result->transactionDate : null;
        $date_accepted = new DateTime($transactionDate);

        return [$authorizationCode, $amount, $sharesNumber, $transactionResponse, $paymentCodeResult, $date_accepted, $sharesAmount, $paymentType];
    }

    protected function setOrderAsCancelledByUser(WC_Order $order_info, $webpayTransaction)
    {
        // Transaction aborted by user
        $order_info->add_order_note(__('Pago abortado por el usuario en el fomulario de pago', 'transbank_webpay'));
        $order_info->update_status('cancelled');
        TransbankWebpayOrders::update($webpayTransaction->id,
            ['status' => TransbankWebpayOrders::STATUS_ABORTED_BY_USER]);
    }
    /**
     * @return bool
     */
    private function transactionWasCanceledByUser()
    {
        return isset($_POST['TBK_ORDEN_COMPRA']) && isset($_POST['TBK_ID_SESION']) && $_POST['TBK_TOKEN'];
    }
}
