<?php
/**
 * Link del preventivo approvato (dall'email o da "I miei preventivi"): porta il cliente al checkout
 * con il carrello del preventivo; con action=cancel il cliente annulla il preventivo.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmquoterequestActivateModuleFrontController extends ModuleFrontController
{
    /** @var Dotmquoterequest */
    public $module;

    public $ssl = true;

    public $auth = true;

    public function init()
    {
        // Dopo l'accesso il cliente torna a questo stesso link
        $this->authRedirection = urlencode($this->context->link->getModuleLink('dotmquoterequest', 'activate', [
            'id_quote' => (int) Tools::getValue('id_quote'),
            'token' => (string) Tools::getValue('token'),
        ], true));

        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        $quote = new DotmQuoteRequestQuote((int) Tools::getValue('id_quote'));
        $error = $this->validate($quote, (string) Tools::getValue('token'));

        if ($error) {
            $this->context->smarty->assign(['dqr' => [
                'error' => $error,
                'title' => $this->module->t('activate_error_title'),
                'list_url' => $this->module->getListUrl(),
                'list_label' => $this->module->t('account_list'),
                'css_vars' => $this->module->getCssVars(),
            ]]);
            $this->setTemplate('module:dotmquoterequest/views/templates/front/activate-error.tpl');

            return;
        }

        if (Tools::getValue('action') === 'cancel') {
            $this->cancel($quote);

            return;
        }

        // Il carrello del preventivo sostituisce (non si unisce a) quello del cliente, che viene
        // ricordato e restituito quando il cliente esce dal checkout (Dotmquoterequest::leaveQuoteCart()).
        $currentCart = (int) $this->context->cookie->id_cart;
        if ($currentCart && $currentCart !== (int) $quote->id_cart) {
            $this->context->cookie->dqr_prev_cart = $currentCart;
        }
        $this->context->cookie->id_cart = (int) $quote->id_cart;
        $this->context->cart = new Cart((int) $quote->id_cart);
        $this->context->cookie->write();

        Tools::redirect($this->context->link->getPageLink('order', true));
    }

    /**
     * "Annulla preventivo" dal checkout: il preventivo è annullato definitivamente (lo sconto viene
     * disattivato) e il cliente ritrova il suo carrello.
     */
    private function cancel(DotmQuoteRequestQuote $quote)
    {
        $quote->status = 'cancelled';
        $quote->cancelled_at = date('Y-m-d H:i:s');
        $quote->save();

        DotmQuoteRequestManager::disableCartRule($quote);

        if ((int) $this->context->cookie->id_cart === (int) $quote->id_cart) {
            $this->module->leaveQuoteCart();
        }

        Tools::redirect($this->module->getListUrl(['cancelled' => (int) $quote->id]));
    }

    private function validate(DotmQuoteRequestQuote $quote, $token)
    {
        if (!Validate::isLoadedObject($quote)) {
            return $this->module->t('activate_not_found');
        }

        if ($token === '' || (string) $quote->token === '' || !hash_equals((string) $quote->token, $token)) {
            return $this->module->t('activate_invalid');
        }

        // Il token identifica il preventivo, non il cliente: il cliente collegato deve esserne il titolare
        if ((int) $quote->id_customer !== (int) $this->context->customer->id) {
            return $this->module->t('activate_wrong_customer');
        }

        if ($quote->isExpired()) {
            if ($quote->status === 'approved') {
                $quote->status = 'expired';
                $quote->save();
            }

            return $this->module->t('activate_expired');
        }

        if ($quote->status !== 'approved') {
            return $this->module->t('activate_unavailable');
        }

        $cart = new Cart((int) $quote->id_cart);
        if (!Validate::isLoadedObject($cart) || $cart->orderExists()) {
            return $this->module->t('activate_no_cart');
        }

        return null;
    }
}
