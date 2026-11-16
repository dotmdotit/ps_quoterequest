<?php
/**
 * Carrello preventivi: prodotti aggiunti dal cliente, quantità, note e invio della richiesta.
 * La pagina è pubblica; chi non ha fatto l'accesso vede l'invito ad accedere o registrarsi.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmquoterequestQuoteModuleFrontController extends ModuleFrontController
{
    /** @var Dotmquoterequest */
    public $module;

    public $ssl = true;

    /** @var DotmQuoteRequestQuote|null */
    private $draft;

    private $logged = false;

    public function init()
    {
        parent::init();

        $this->logged = (bool) $this->context->customer->isLogged();
        if ($this->logged) {
            $this->draft = DotmQuoteRequestQuote::getCurrentDraft((int) $this->context->customer->id);
            if ($this->draft && !Tools::isSubmit('submitQuote')) {
                $this->refreshPrices();
            }
        }
    }

    public function postProcess()
    {
        if (!$this->logged || !$this->draft || !Tools::getIsset('dqr_action')) {
            return;
        }

        if (Tools::getValue('token') !== Tools::getToken(false)) {
            $this->errors[] = $this->module->t('error_token');

            return;
        }

        $action = (string) Tools::getValue('dqr_action');
        $line = null;
        if (in_array($action, ['update', 'remove'], true)) {
            $line = new DotmQuoteRequestDetail((int) Tools::getValue('id_quote_detail'));
            if (!Validate::isLoadedObject($line) || (int) $line->id_quote !== (int) $this->draft->id) {
                $this->redirectWithFlash('');
            }
        }

        switch ($action) {
            case 'update':
                $qty = (int) Tools::getValue('quantity');
                if ($qty <= 0) {
                    $line->delete();
                    $this->redirectWithFlash('removed');
                }
                $line->quantity = min($qty, DotmQuoteRequestDetail::MAX_QTY);
                $line->save();
                $this->saveMessage();
                $this->redirectWithFlash('updated');
                break;

            case 'remove':
                $line->delete();
                $this->saveMessage();
                $this->redirectWithFlash('removed');
                break;

            case 'clear':
                foreach ($this->draft->getLines() as $item) {
                    $item->delete();
                }
                $this->redirectWithFlash('cleared');
                break;

            case 'submit':
                $this->submit();
                break;
        }
    }

    /**
     * Invio della richiesta: il carrello preventivi diventa un preventivo "da gestire".
     */
    private function submit()
    {
        $this->refreshPrices();
        $message = trim((string) Tools::getValue('customer_message'));

        if (!$this->draft->countLines()) {
            $this->errors[] = $this->module->t('error_empty');
        } elseif (Tools::strlen($message) > 2000) {
            $this->errors[] = $this->module->t('error_message_length');
        } elseif ($this->getMissingAmount() > 0) {
            $this->errors[] = $this->getMinimumMessage();
        }

        if ($this->errors) {
            $this->draft->customer_message = $message;

            return;
        }

        $this->draft->customer_message = $message;
        $this->draft->status = 'pending';
        $this->draft->id_lang = (int) $this->context->language->id;
        $this->draft->submitted_at = date('Y-m-d H:i:s');
        if (!$this->draft->save()) {
            $this->errors[] = $this->module->t('error_generic');

            return;
        }
        $this->draft->assignNumber();

        DotmQuoteRequestManager::sendNewRequestEmail($this->draft, $this->context->customer);

        Tools::redirect($this->module->getListUrl(['submitted' => (int) $this->draft->id]));
    }

    /**
     * Le note scritte prima di cambiare una quantità non vanno perse.
     */
    private function saveMessage()
    {
        if (Tools::getIsset('customer_message')) {
            $this->draft->customer_message = Tools::substr(trim((string) Tools::getValue('customer_message')), 0, 2000);
            $this->draft->save();
        }
    }

    private function redirectWithFlash($flash)
    {
        Tools::redirect($this->module->getCartUrl($flash !== '' ? ['dqr_msg' => $flash] : []));
    }

    /**
     * Finché la richiesta non è inviata i prezzi seguono il listino (e i prezzi riservati al cliente).
     */
    private function refreshPrices()
    {
        $idCustomer = (int) $this->context->customer->id;
        foreach ($this->draft->getLines() as $line) {
            $incl = DotmQuoteRequestDetail::customerPrice($line->id_product, $line->id_product_attribute, true, $idCustomer);
            $excl = DotmQuoteRequestDetail::customerPrice($line->id_product, $line->id_product_attribute, false, $idCustomer);
            if (abs($incl - (float) $line->unit_price_tax_incl) > 0.000001 || abs($excl - (float) $line->unit_price_tax_excl) > 0.000001) {
                $line->unit_price_tax_incl = $incl;
                $line->unit_price_tax_excl = $excl;
                $line->save();
            }
        }
    }

    public function initContent()
    {
        parent::initContent();

        $t = $this->module->getStrings();
        $lines = $this->draft ? $this->presentLines() : [];
        $missing = $this->getMissingAmount();
        $minimumMessage = $missing > 0 ? $this->getMinimumMessage() : '';
        $min = $this->draft ? Dotmquoterequest::getMinTotal((int) $this->draft->id_currency) : 0;
        $total = $this->draft ? $this->draft->getTotalTaxIncl() : 0;

        $flashes = [
            'updated' => $t['flash_updated'],
            'removed' => $t['flash_removed'],
            'cleared' => $t['flash_cleared'],
            'added' => $t['flash_added'],
        ];
        $flash = (string) Tools::getValue('dqr_msg');

        $qtyTotal = 0;
        foreach ($lines as $line) {
            $qtyTotal += $line['quantity'];
        }

        $back = urlencode($this->module->getCartUrl());
        $this->context->smarty->assign(['dqr' => [
            't' => $t,
            'logged' => $this->logged,
            'css_vars' => $this->module->getCssVars(),
            'action' => $this->module->getCartUrl(),
            'token' => Tools::getToken(false),
            'lines' => $lines,
            'count_label' => count($lines) === 1 ? $t['count_one'] : str_replace('{n}', (string) count($lines), $t['count_many']),
            'qty_total' => $qtyTotal,
            'total' => $this->draft ? $this->draft->getFormattedTotal() : '',
            'message' => $this->draft ? (string) $this->draft->customer_message : '',
            'below_minimum' => $missing > 0,
            'minimum_message' => $minimumMessage,
            'minimum_progress' => $min > 0 ? (int) min(100, floor($total / $min * 100)) : 100,
            'show_minimum' => $minimumMessage !== '' && !in_array($minimumMessage, $this->errors, true),
            'errors' => $this->errors,
            'flash' => isset($flashes[$flash]) ? $flashes[$flash] : '',
            'shop_url' => $this->context->link->getPageLink('index', true),
            'list_url' => $this->module->getListUrl(),
            'login_url' => $this->context->link->getPageLink('authentication', true, null, 'back=' . $back),
            'register_url' => $this->context->link->getPageLink('authentication', true, null, 'create_account=1&back=' . $back),
            'max_qty' => DotmQuoteRequestDetail::MAX_QTY,
        ]]);

        $this->setTemplate('module:dotmquoterequest/views/templates/front/quote.tpl');
    }

    /**
     * Righe con immagine, link al prodotto e prezzi già formattati.
     */
    private function presentLines()
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $idCurrency = (int) $this->draft->id_currency;
        $link = $this->context->link;
        $imageType = method_exists('ImageType', 'getFormattedName') ? ImageType::getFormattedName('cart') : 'cart_default';

        $lines = [];
        foreach ($this->draft->getLines() as $line) {
            $product = new Product((int) $line->id_product, false, $idLang, $idShop);
            $url = '';
            $image = '';
            $available = Validate::isLoadedObject($product) && $product->active;
            if (Validate::isLoadedObject($product)) {
                $url = $link->getProductLink($product, null, null, null, $idLang, $idShop, (int) $line->id_product_attribute);
                $idImage = 0;
                if ($line->id_product_attribute) {
                    $best = Image::getBestImageAttribute($idShop, $idLang, (int) $line->id_product, (int) $line->id_product_attribute);
                    $idImage = $best ? (int) $best['id_image'] : 0;
                }
                if (!$idImage) {
                    $cover = Product::getCover((int) $line->id_product);
                    $idImage = $cover ? (int) $cover['id_image'] : 0;
                }
                if ($idImage) {
                    $image = $link->getImageLink($product->link_rewrite, $idImage, $imageType);
                }
            }

            $lines[] = [
                'id' => (int) $line->id,
                'product_name' => $line->product_name,
                'product_reference' => $line->product_reference,
                'combination_name' => $line->combination_name,
                'quantity' => (int) $line->quantity,
                'unit_price' => Dotmquoterequest::formatPrice($line->unit_price_tax_incl, $idCurrency),
                'total' => Dotmquoterequest::formatPrice($line->getTotalTaxIncl(), $idCurrency),
                'url' => $url,
                'image' => $image,
                'available' => $available,
            ];
        }

        return $lines;
    }

    /**
     * Quanto manca (IVA inclusa, prezzi di listino) al minimo per inviare la richiesta; 0 se si può inviare.
     */
    private function getMissingAmount()
    {
        if (!$this->draft) {
            return 0;
        }

        $min = Dotmquoterequest::getMinTotal((int) $this->draft->id_currency);

        return $min > 0 ? max(0, round($min - $this->draft->getTotalTaxIncl(), 2)) : 0;
    }

    private function getMinimumMessage()
    {
        $idCurrency = (int) $this->draft->id_currency;

        return $this->module->t('minimum_notice', [
            'min' => Dotmquoterequest::formatPrice(Dotmquoterequest::getMinTotal($idCurrency), $idCurrency),
            'total' => Dotmquoterequest::formatPrice($this->draft->getTotalTaxIncl(), $idCurrency),
            'missing' => Dotmquoterequest::formatPrice($this->getMissingAmount(), $idCurrency),
        ]);
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = [
            'title' => $this->module->t('page_title'),
            'url' => $this->module->getCartUrl(),
        ];

        return $breadcrumb;
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();
        $page['meta']['title'] = $this->module->t('page_title');
        $page['meta']['robots'] = 'noindex';

        return $page;
    }
}
