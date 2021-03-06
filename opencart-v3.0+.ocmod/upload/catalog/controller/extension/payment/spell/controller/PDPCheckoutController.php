<?php

class PDPCheckoutController
{

    private $load;
    private $language;
    private $session;
    private $request;
    private $response;
    private $model_extension_payment_spell_payment;

    public function __construct($registry)
    {
        $this->language = $registry->get('language');
        $this->load = $registry->get('load');
        $this->session = $registry->get('session');
        $this->request = $registry->get('request');
        $this->response = $registry->get('response');
        $this->load->model('extension/payment/spell_payment');
        $this->model_extension_payment_spell_payment = $registry->get('model_extension_payment_spell_payment');
    }

    /**
     * Callback functions
     *
     * @Route("/index.php?route=extension/payment/spell_payment/oneClickProcess")
     *
     * @return void;
     */
    public function oneClickProcess()
    {
        $payment = $this->getSpellModel()->createOneClickPayment($this->request);
        if (!isset($payment['checkout_url'])) {
            $this->response->setOutput($this->language->get('error_ps_url_fail'));
        } else {
            if ($this->session->data['spell_payment_id'] !=  $payment['id'] ) {
                $this->session->data['spell_payment_id'] = $payment['id'];
                header("Location:" . $payment['checkout_url']);
            } else {
                $this->response->setOutput($this->language->get('error_ps_url_fail'));
            }
        }
    }

    private function collectUrlParams()
    {
        $order_id = $this->session->data['order_id'];
        $arr = array(
            // 'country' => $this->request->post['country'],
            'order_info' => $this->getCheckoutOrder()->getOrder($order_id),
        );

        if (array_key_exists('payment_method', $this->request->post)) {
            $arr['payment_method'] = $this->request->post['payment_method'];
        }

        return $arr;
    }

    /** @return ModelCheckoutOrder */
    private function getCheckoutOrder()
    {
        $this->load->model('checkout/modelorder');

        return $this->model_checkout_order;
    }

    /** @return ModelExtensionPaymentSpellPayment */
    private function getSpellModel()
    {
        return $this->model_extension_payment_spell_payment;
    }

    public function oneClickCallback()
    {
        $this->db->query("SELECT GET_LOCK('spell_payment', 15);");

        $spell = $this->getSpellModel()->getSpell();
        $orderId = $_GET['id'];
        $order = $this->getCheckoutOrder()->getOrder($orderId);
        $payment_id = $this->session->data['spell_payment_id'];
        if (!$payment_id) {
            $input = json_decode(file_get_contents('php://input'), true);
            $payment_id = array_key_exists('id', $input) ? $input['id'] : '';
        }
        $purchase = $spell->purchases($payment_id);
        $status = !$purchase ? null : $purchase['status'];
        $successStatusId = $this->config->get('payment_spell_payment_success_status_id');
        if ($spell->was_payment_successful($payment_id) && $status === 'paid') {
            $order_history_id = $this->getCheckoutOrder()->addOrderHistory($orderId, $successStatusId, $status);
        } else {
            $errorStatusId = $this->config->get('payment_spell_payment_error_status_id');
            $this->getCheckoutOrder()->addOrderHistory($orderId, $errorStatusId, $status);
        }

        $this->db->query("SELECT RELEASE_LOCK('spell_payment');");
    }
}
