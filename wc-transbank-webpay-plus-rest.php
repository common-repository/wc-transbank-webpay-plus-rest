<?php
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Controllers\ResponseController;
use Transbank\WooCommerce\WebpayRest\Controllers\ThankYouPageController;
use Transbank\WooCommerce\WebpayRest\Helpers\RedirectorHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\WebpayRest\TransbankSdkWebpayRest;
use Transbank\WooCommerce\WebpayRest\TransbankWebpayOrders;

if (!defined('ABSPATH')) {
    exit();
} // Exit if accessed directly

/**
 * @wordpress-plugin
 * Plugin Name: Migración de Medio de Pago Webpay Plus SOAP a REST de Transbank para WooCommerce
 * Author: AndresReyesDev
 * Author URI: https://andres.reyes.dev
 * Plugin URI: https://andres.reyes.dev
 * Description: Recibe pagos en línea con Tarjetas de Crédito y Redcompra en tu WooCommerce a través de Webpay Plus Rest.
 * Version: 2021.03.22
 * WC requires at least: 3.4.0
 * WC tested up to: 4.8
 */
add_action('plugins_loaded', 'woocommerce_transbank_rest_init', 0);

require_once plugin_dir_path(__FILE__) . "vendor/autoload.php";

register_activation_hook(__FILE__, 'on_webpay_rest_plugin_activation');
add_action( 'admin_init', 'on_transbank_rest_webpay_plugins_loaded' );
add_filter('woocommerce_payment_gateways', 'woocommerce_add_transbank_gateway');
add_action('woocommerce_before_cart', function() {
    SessionMessageHelper::printMessage();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_rest_action_links');

add_action('init',function() {
    if( !headers_sent() && '' == session_id() ) {
        session_start([
            'read_and_close' => true
        ]);
    }
});

function woocommerce_transbank_rest_init()
{
    if (!class_exists("WC_Payment_Gateway")) {
        return;
    }

    /**
     * @property string icon
     * @property string  method_title
     * @property string title
     * @property string description
     * @property string id
     */
    class WC_Gateway_Transbank_Webpay_Plus_REST extends WC_Payment_Gateway
    {
        private static $URL_RETURN;
        private static $URL_FINAL;

        protected $notify_url;
        protected $plugin_url;
        protected $config;


        public function __construct()
        {

            self::$URL_RETURN = home_url('/') . '?wc-api=WC_Gateway_transbank_webpay_plus_rest';
            self::$URL_FINAL = home_url('/') . '?wc-api=TransbankWebpayRestThankYouPage';;

            $this->id = 'transbank_webpay_plus_rest';
            $this->icon = 'https://payment.swo.cl/host/logo';
            $this->method_title = __('Webpay Plus REST de Transbank');
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_' . $this->id, home_url('/'));
            $this->title = 'Tarjetas de Crédito o Redcompra';
            $this->description = 'Paga usando tu Tarjeta de Cr&eacute;dito o Débito (Redcompra) a trav&eacute;s de Webpay Plus de Transbank';
            $this->plugin_url = plugins_url('/', __FILE__);


            $this->config = [
                "MODO" => trim($this->get_option('webpay_rest_environment', 'TEST')),
                "COMMERCE_CODE" => trim($this->get_option('webpay_rest_commerce_code', Options::DEFAULT_COMMERCE_CODE)),
                "API_KEY" => $this->get_option('webpay_rest_api_key', Options::DEFAULT_API_KEY),
                "URL_RETURN" => home_url('/') . '?wc-api=WC_Gateway_' . $this->id,
                "ECOMMERCE" => 'woocommerce',
                "VENTA_DESC" => [
                    "VD" => "Venta Débito",
                    "VN" => "Venta Normal",
                    "VC" => "Venta en cuotas",
                    "SI" => "3 cuotas sin interés",
                    "S2" => "2 cuotas sin interés",
                    "NC" => "N cuotas sin interés"
                ],
                "STATUS_AFTER_PAYMENT" => $this->get_option('webpay_rest_after_payment_order_status', null)
            ];

            /**
             * Carga configuración y variables de inicio
             **/

            $this->init_form_fields();
            $this->init_settings();

            add_action('woocommerce_thankyou', [new ThankYouPageController($this->config), 'show'], 1);
            add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'registerPluginVersion']);
            add_action('woocommerce_api_wc_gateway_' . $this->id, [$this, 'check_ipn_response']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
            add_action( 'woocommerce_sections_checkout', [$this, 'wc_transbank_message'], 1);

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }
        public function enqueueScripts()
        {
            wp_localize_script('ajax-script', 'ajax_object', ['ajax_url' => admin_url('admin-ajax.php')]);
        }

        public function registerPluginVersion()
        {
            if (!$this->get_option('webpay_rest_test_mode', 'INTEGRACION') === 'PRODUCCION') {
                return;
            }

            $commerceCode = $this->get_option('webpay_rest_commerce_code');
            if ($commerceCode == Options::DEFAULT_COMMERCE_CODE) {
                return;
            };

            $pluginVersion = $this->getPluginVersion();

            (new PluginVersion)->registerVersion($commerceCode, $pluginVersion, wc()->version,
                PluginVersion::ECOMMERCE_WOOCOMMERCE);
        }

        function is_valid_for_use()
        {
            if (!in_array(get_woocommerce_currency(),
                apply_filters('woocommerce_' . $this->id . '_supported_currencies', ['CLP']))) {
                return false;
            }

            return true;
        }

        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activar/Desactivar', 'transbank_webpay_plus_rest'),
                    'type' => 'checkbox',
                    'default' => 'yes'
                ),
                'webpay_rest_environment' => array(
                    'title' => __('Ambiente', 'transbank_webpay_plus_rest'),
                    'type' => 'select',
                    'options' => array(
                        'TEST' => __('Integración', 'transbank_webpay_plus_rest'),
                        'LIVE' => __('Producción', 'transbank_webpay_plus_rest')
                    ),
                    'default' => 'TEST'
                ),
                'webpay_rest_commerce_code' => array(
                    'title' => __('Código de Comercio', 'transbank_webpay_plus_rest'),
                    'type' => 'text',
                    'default' => $this->config['COMMERCE_CODE']
                ),
                'webpay_rest_api_key' => array(
                    'title' => __('API Key', 'transbank_webpay_plus_rest'),
                    'type' => 'text',
                    'default' => $this->config['API_KEY']
                )
            );
        }

        function receipt_page($order_id)
        {
            $order = new WC_Order($order_id);
            $amount = (int) number_format($order->get_total(), 0, ',', '');
            $sessionId = uniqid();
            $buyOrder = $order_id;
            $returnUrl = self::$URL_RETURN;
            $finalUrl = str_replace("_URL_",
                add_query_arg('key', $order->get_order_key(), $order->get_checkout_order_received_url()),
                self::$URL_FINAL);

            $transbankSdkWebpay = new TransbankSdkWebpayRest($this->config);
            $result = $transbankSdkWebpay->createTransaction($amount, $sessionId, $buyOrder, $returnUrl);

            if (!isset($result["token_ws"])) {
                wc_add_notice( 'Ocurrió un error al intentar conectar con WebPay Plus. Por favor intenta mas tarde.<br/>',
                    'error');
                return;
            }

            $url = $result["url"];
            $token_ws = $result["token_ws"];

            TransbankWebpayOrders::createTransaction([
                'order_id' => $order_id,
                'buy_order' => $buyOrder,
                'amount' => $amount,
                'token' => $token_ws,
                'session_id' => $sessionId,
                'status' => TransbankWebpayOrders::STATUS_INITIALIZED
            ]);

            RedirectorHelper::redirect($url, ["token_ws" => $token_ws]);
        }

        function check_ipn_response()
        {
            @ob_clean();
            if (isset($_POST)) {
                header('HTTP/1.1 200 OK');
                return (new ResponseController($this->config))->response($_POST);
            } else {
                echo "Ocurrio un error al procesar su compra";
            }
        }

        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return [
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            ];
        }

        public function admin_options()
        {
            include 'libwebpay/admin-options.php';
        }

        function wc_transbank_message() {
            $modo = $this->get_option( 'webpay_rest_environment' );
            if ($modo == 'TEST') {
                $current_section = ( isset( $_GET['section'] ) && ! empty( $_GET['section'] ) ) ? $_GET['section'] : '';
                if ( $current_section == 'transbank_webpay_plus_rest' ) {
                    $bg     = plugin_dir_url( __FILE__ ) . 'assets/img/back.png';
                    $domain = get_home_url();
    
                    $message = <<<EOL
    <div class="transbank-postbox" style="background-image: url('$bg'); border-radius: 5px">
    <div class="inside" style="text-align: center; color: #ffffff; font-weight: 300; padding: 10px 50px;">
    <p style="font-size: 25px; font-weight: 300;">¡Vende ya con Tarjetas de Crédito y Débito!</p>
    <p style="font-size: 15px; font-weight: 300;">Vamos al grano: Para vender con tarjetas no sólo basta con instalar el plugin, sino hay que realizar un procedimiento comercial y técnico que puede llevar un par de semanas. Yo realizo ese proceso en máximo <strong>5 días hábiles</strong>.</p>
    <p style="font-size: 15px; font-weight: 300;">Soy <a href="https://andres.reyes.dev" style="color: white"><strong>Andrés Reyes Galgani</strong></a>, desarrollador del plugin, principal integrador de Transbank y estoy aquí para ayudarte en dicho proceso.</p>
    <p style="text-align: center;"><a class="button" style="padding: 5px 10px; margin-top: 10px; background-color: #11a94a; color: white; font-size: 15px; font-weight: 500;" href="https://link.reyes.dev/wc-transbank-webpay-plus-rest?text=Hola, necesito integrar Webpay Plus en el dominio $domain" target="_blank">¿Dudas? ¡Háblame Ahora Directo a WhatsApp!</a></p>
    </div>
    </div>
    EOL;
                    echo $message;
                }
            }
        }

    }

    function woocommerce_add_transbank_gateway($methods)
    {
        $methods[] = 'WC_Gateway_transbank_webpay_plus_rest';

        return $methods;
    }

    function pay_transbank_webpay_content($orderId)
    {

    }

}

function add_rest_action_links($links)
{
    $newLinks = [
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest') . '">💳 Configuración de Medio de Pago</a>',
    ];

    return array_merge($links, $newLinks);
}

function on_webpay_rest_plugin_activation()
{
    woocommerce_transbank_rest_init();
    if (!class_exists(WC_Gateway_Transbank_Webpay_Plus_REST::class)) {
        die('Se necesita tener WooCommerce instalado y activo para poder activar este plugin');
        return;
    }
}

function on_transbank_rest_webpay_plugins_loaded() {
    TransbankWebpayOrders::createTableIfNeeded();
}

function transbank_rest_remove_database() {
    TransbankWebpayOrders::deleteTable();
}

register_uninstall_hook( __FILE__, 'transbank_rest_remove_database' );
