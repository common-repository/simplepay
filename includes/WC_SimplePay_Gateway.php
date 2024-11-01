<?php

use GuzzleHttp\Exception\ClientException;
use SimplePay\Exceptions\UnauthorizedException;
use SimplePay\SimplePay;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Class WC_SimplePay_Gateway
 */
class WC_SimplePay_Gateway extends WC_Payment_Gateway
{

    /**
     * @var null|SimplePay
     */
    protected $simplepay_sdk = null;

    /**
     * WC_SimplePay_Gateway constructor.
     */
    public function __construct()
    {

        $this->id = 'simplepay';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = 'SimplePay - Chauchas';
        $this->method_description = 'Paga con Chauchas (CHA).';
        $this->max_amount = 100000;

        $this->init_form_fields();
        $this->init_settings();

        $this->simplepay_sdk = new SimplePay($this->get_option('token'));
        $this->title = $this->get_option( 'title' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_wc_simplepay_gateway', array( $this, 'process_response' ) );
        add_action( 'woocommerce_api_wc_simplepay_gateway_ipn', array( $this, 'check_ipn_request' ) );
        add_action('woocommerce_thankyou', [$this, 'thanks_content'], 1);

        if (!$this->is_valid_for_use()) {
            wc_add_notice(__('Para habilitar SimplePay, el comercio debe usar CLP (pesos chilenos) o CHA (chauchas): ',
                'SimplePayPlugin'), 'error');
            $this->enabled = false;
        }
    }

    public function thanks_content($order_id)
    {
        $order = new WC_Order($order_id);
        $gateway = new WC_SimplePay_Gateway();
        if ($order->get_payment_method() != $gateway->id) {
            return;
        }
        $response = $order->get_meta('simplepay_response');
        if ($order->get_meta('simplepay_transaction_paid') === "" && $response === '') {
            wc_add_notice(__('Compra <strong>anulada</strong>', 'woocommerce') . ' por usuario. Recuerda que puedes pagar o
                    cancelar tu compra cuando lo desees desde <a href="' . wc_get_page_permalink('myaccount') . '">' . __('Tu Cuenta', 'woocommerce') . '</a>', 'error');
            wp_redirect($order->get_checkout_payment_url());
            die;
        }

        $response = json_decode($response, true);
        include(__DIR__ . '/../views/thanks_payment_details.php');

    }

    /**
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = new WC_Order( $order_id );
        $currency = $order->get_currency();
        $amount = $order->get_total();
        if ($currency === 'CLP') {
            $amount = (int) number_format($order->get_total(), 0, ',', '');
        }
        $notify_url = add_query_arg('wc-api', self::class, home_url('/'));
        $ipn_url = add_query_arg('wc-api', self::class . '_ipn', home_url('/'));
        $finalUrl = add_query_arg('key', $order->get_order_key(), $this->get_return_url($order));
        try {
            $response = $this->simplepay_sdk->initTransaction(SimplePay::PAYMENT_METHOD_CHAUCHAS, $amount, $currency, $order->get_id(), $notify_url,
                $finalUrl, '', $ipn_url);
        } catch (UnauthorizedException $e) {
            return $this->error(__('El Token de SimplePay no es válido. Contacta al administrador del sitio para que solucione el problema.',
                'simplepay'));
        } catch (\SimplePay\Exceptions\ClientException $e) {
            return $this->error(__('No se pudo conectar con SimplePay. Por favor, intente más tarde.', 'simplepay'));
        } catch (Exception $e) {
            return $this->error(__('Ocurrió un error inesperado. Por favor, intenta más tarde.' . $e->getMessage(), 'simplepay'));
        }

        return array(
            'result' => 'success',
            'redirect' => $response['redirect_url']
        );

    }

    /**
     * AKA: Response URL.
     *
     * @return mixed
     */
    public function process_response()
    {
        if (!isset($_POST['token'])) {
            die('SimplePay: No se ha podido procesar esta solicitud. ');
        }

        $token = $_POST['token'];

        try {
            $response = $this->simplepay_sdk->getTransactionResult($token);
        } catch (Exception $e) {
            die('Error obteniendo información de la transacción: ' . $e->getMessage());
        }

        //Verify if transaction was approved
        if (is_null($response['transaction']['accepted_at'])) {
            return;
        }


        $order = new WC_Order( $response['transaction']['commerce_order_id'] );
        $previousResponse = $order->get_meta('simplepay_response');
        $previousPaid = $order->get_meta('simplepay_transaction_paid');
        if ($previousResponse || $previousPaid) {
            $msg = 'Simplepay está intentando notificar una compra por segunda vez. Es posible que no se haya realizado el llamado a "acknowledgeTransaction"';
            $this->error($msg);
            die($msg);
        }
        $this->simplepay_sdk->acknowledgeTransaction($token);
        $order->add_meta_data('simplepay_response', json_encode($response));
        $order->add_meta_data('simplepay_transaction_paid', true);

        $order->update_status('on-hold');
        $order->add_order_note( __( 'Pago recibido. Se está esperando la confirmación de la red. ID de transacción: ' . $response['transaction']['uuid'], 'simplepay' ));

        wc_reduce_stock_levels($order->get_id());
        WC()->cart->empty_cart();

        wp_redirect($response['redirect_url']);
    }

    public function check_ipn_request()
    {
        if (!isset($_POST['token'])) {
            die('SimplePay: No se ha podido procesar esta solicitud. ');
        }

        $token = $_POST['token'];

        try {
            $response = $this->simplepay_sdk->getTransactionResult($token);
        } catch (Exception $e) {
            die('Error obteniendo información de la transacción: ' . $e->getMessage());
        }

        if ($response['transaction']['completed_at'] !== null) {
            $order = new WC_Order($response['transaction']['commerce_order_id']);
            $order->add_meta_data('simplepay_completed', true);
            $order->payment_complete($response['transaction']['uuid']);
            $order->add_order_note('Transacción completa y confirmada por la red');
            die($response['transaction']['uuid']);
        }

    }


    /**
     * @param $msg
     * @return array
     */
    protected function error($msg)
    {
        wc_add_notice($msg, 'error');

        return [
            'result' => 'error',
            'detail' => $msg
        ];
    }

    /**
     *
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Habilitar/Deshabilitar', 'simplepay'),
                'type' => 'checkbox',
                'label' => __( 'Habilitar el pago con SimplePay', 'simplepay'),
                'default' => 'yes'
            ),

            /*'chaucha_enabled' => array(
                'title' => __( 'Aceptar Chauchas', 'simplepay'),
                'type' => 'checkbox',
                'label' => __( 'Habilitar el pago con Chauchas (CHA)', 'simplepay'),
                'default' => 'yes'
            ),*/

            'send_customer_email' => array(
                'title' => __( 'Enviar email a cliente', 'simplepay'),
                'type' => 'checkbox',
                'description' => __( 'Si habilitas esta opción, se enviará el correo del cliente a SimplePay para notificarle el estado de su pago. Le llegará un correo cuando su pago se acepte, cuando se confirme completamente y cuando ocurra algun error.', 'simplepay'),
                'label' => __( 'Enviar correo al cliente con estados del pago', 'simplepay'),
                'default' => 'yes'
            ),

            'token' => array(
                'title' => __( 'Token', 'simplepay'),
                'type' => 'text',
                'description' => __( 'Esta es la llave con la que tu sitio se comunica con SimplePay. Puedes obtener tu Token desde tu cuenta de SimplePay. <br />Regístrate en <a target="_blank" href="https://simplepay.cl/register">https://simplepay.cl/register</a>',
                    'simplepay'),
                'default' => '',
            ),

            'title' => array(
                'title' => __( 'Título', 'simplepay'),
                'type' => 'text',
                'description' => __( 'Define el texto que aparecerá en el checkout', 'simplepay'),
                'default' => __( 'Pago con Chauchas (vía SimplePay)', 'simplepay'),
                'desc_tip'      => true,
            ),

            /*'description' => array(
                'title' => __( 'Mensaje para los clientes', 'simplepay'),
                'type' => 'textarea',
                'default' => ''
            )*/
        );
    }

    /**
     * @return bool
     */
    function is_valid_for_use()
    {
        if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_' . $this->id . '_supported_currencies', array('CLP', 'CHA')))) {
            return false;
        }
        return true;
    }

}

