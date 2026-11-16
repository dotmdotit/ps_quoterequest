<?php
/**
 * "Aggiungi al preventivo" dalla pagina prodotto (richiesta AJAX, risposta JSON).
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmquoterequestAddtoquoteModuleFrontController extends ModuleFrontController
{
    /** @var Dotmquoterequest */
    public $module;

    public $ssl = true;

    public function postProcess()
    {
        try {
            $this->respond($this->handle());
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('dotmquoterequest addtoquote: ' . $e->getMessage(), 3);
            $this->respond([
                'success' => false,
                'message' => (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_)
                    ? get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
                    : $this->module->t('error_generic'),
            ]);
        }
    }

    private function respond(array $data)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    private function handle()
    {
        $customer = $this->context->customer;
        if (!$customer || !$customer->isLogged()) {
            return [
                'success' => false,
                'message' => $this->module->t('error_login'),
                'login_url' => $this->context->link->getPageLink('authentication', true),
            ];
        }

        if (!Tools::getValue('token') || Tools::getValue('token') !== Tools::getToken(false)) {
            return ['success' => false, 'message' => $this->module->t('error_token')];
        }

        $idProduct = (int) Tools::getValue('id_product');
        $idProductAttribute = (int) Tools::getValue('id_product_attribute');
        $qty = (int) Tools::getValue('qty', 1);
        if ($idProduct <= 0 || $qty <= 0) {
            return ['success' => false, 'message' => $this->module->t('error_product_data')];
        }

        $idLang = (int) $this->context->language->id;
        $product = new Product($idProduct, false, $idLang, (int) $this->context->shop->id);
        if (!Validate::isLoadedObject($product) || !$product->active) {
            return ['success' => false, 'message' => $this->module->t('error_product_unavailable')];
        }

        if ($idProductAttribute) {
            $combination = new Combination($idProductAttribute);
            if (!Validate::isLoadedObject($combination) || (int) $combination->id_product !== $idProduct) {
                return ['success' => false, 'message' => $this->module->t('error_product_data')];
            }
        } elseif ($product->hasAttributes()) {
            $idProductAttribute = (int) Product::getDefaultAttribute($idProduct);
        }

        $quote = DotmQuoteRequestQuote::getOrCreateDraft(
            (int) $customer->id,
            (int) $this->context->shop->id,
            $idLang,
            (int) $this->context->currency->id
        );
        if (!Validate::isLoadedObject($quote)) {
            return ['success' => false, 'message' => $this->module->t('error_generic')];
        }

        $line = DotmQuoteRequestDetail::findLine($quote->id, $idProduct, $idProductAttribute);
        if ($line) {
            $line->quantity = min((int) $line->quantity + $qty, DotmQuoteRequestDetail::MAX_QTY);
            $line->save();
        } else {
            if ($quote->countLines() >= DotmQuoteRequestDetail::MAX_LINES) {
                return ['success' => false, 'message' => $this->module->t('error_max_lines', ['n' => DotmQuoteRequestDetail::MAX_LINES])];
            }
            $line = DotmQuoteRequestDetail::build($quote->id, $product, $idProductAttribute, min($qty, DotmQuoteRequestDetail::MAX_QTY), $idLang, (int) $customer->id);
            if (!$line->add()) {
                return ['success' => false, 'message' => $this->module->t('error_generic')];
            }
        }

        $count = $quote->countLines();

        return [
            'success' => true,
            'message' => $this->module->t('flash_added'),
            'quote_url' => $this->module->getCartUrl(['dqr_msg' => 'added']),
            'cart_url' => $this->module->getCartUrl(),
            'line_count' => $count,
        ];
    }

    public function initContent()
    {
        Tools::redirect($this->module->getCartUrl());
    }
}
