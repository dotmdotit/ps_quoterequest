<?php
/**
 * "I miei preventivi" nell'area clienti: elenco, dettaglio e PDF dei preventivi inviati.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmquoterequestQuotelistModuleFrontController extends ModuleFrontController
{
    /** @var Dotmquoterequest */
    public $module;

    public $ssl = true;

    public $auth = true;

    /** Stati in cui il preventivo è stato mandato al cliente e ha un PDF */
    const PDF_STATUSES = ['approved', 'awaiting_payment', 'converted', 'expired'];

    public function init()
    {
        $this->authRedirection = urlencode($this->module->getListUrl());

        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        $idQuote = (int) Tools::getValue('id_quote');
        if ($idQuote) {
            $this->displayDetail($idQuote);

            return;
        }

        $quotes = [];
        foreach (DotmQuoteRequestQuote::getCustomerQuotes((int) $this->context->customer->id) as $row) {
            $quote = new DotmQuoteRequestQuote((int) $row['id_quote']);
            $quotes[] = [
                'id' => (int) $quote->id,
                'number' => $quote->getDisplayNumber(),
                'status' => $quote->status,
                'status_label' => $this->module->getStatusLabel($quote->status),
                'submitted_at' => Dotmquoterequest::isDateSet($quote->submitted_at) ? Tools::displayDate($quote->submitted_at) : '',
                'total' => $quote->getFormattedTotal(),
                'url' => $this->module->getListUrl(['id_quote' => (int) $quote->id]),
            ];
        }

        $submitted = DotmQuoteRequestQuote::getForCustomer((int) Tools::getValue('submitted'), (int) $this->context->customer->id);
        $cancelled = DotmQuoteRequestQuote::getForCustomer((int) Tools::getValue('cancelled'), (int) $this->context->customer->id);

        $this->context->smarty->assign(['dqr' => [
            't' => $this->module->getStrings(),
            'css_vars' => $this->module->getCssVars(),
            'quotes' => $quotes,
            'submitted' => $submitted ? $this->module->t('list_submitted', ['number' => $submitted->getDisplayNumber()]) : '',
            'cancelled' => $cancelled ? $this->module->t('list_cancelled', ['number' => $cancelled->getDisplayNumber()]) : '',
            'cart_url' => $this->module->getCartUrl(),
        ]]);

        $this->setTemplate('module:dotmquoterequest/views/templates/front/quotelist.tpl');
    }

    private function displayDetail($idQuote)
    {
        $quote = DotmQuoteRequestQuote::getForCustomer($idQuote, (int) $this->context->customer->id);
        if (!$quote || $quote->status === 'draft') {
            Tools::redirect($this->module->getListUrl());
        }

        $hasPdf = in_array($quote->status, self::PDF_STATUSES, true);
        if ($hasPdf && Tools::getValue('pdf')) {
            (new DotmQuoteRequestPdf($quote))->download();
        }

        $idCurrency = (int) $quote->id_currency;
        $lines = [];
        $hasLineDiscounts = false;
        foreach ($quote->getLines() as $line) {
            $hasLineDiscounts = $hasLineDiscounts || $line->getDiscountTaxIncl() > 0;
            $lines[] = [
                'product_name' => $line->product_name,
                'product_reference' => $line->product_reference,
                'combination_name' => $line->combination_name,
                'quantity' => (int) $line->quantity,
                'unit_price' => Dotmquoterequest::formatPrice($line->unit_price_tax_incl, $idCurrency),
                'total' => Dotmquoterequest::formatPrice($line->getTotalTaxIncl(), $idCurrency),
                'discount' => $line->getDiscountTaxIncl() > 0 ? Dotmquoterequest::formatPrice($line->getDiscountTaxIncl(), $idCurrency) : '',
                'net_total' => Dotmquoterequest::formatPrice($line->getNetTotalTaxIncl(), $idCurrency),
            ];
        }

        $showDiscount = $quote->discount_type && $quote->status !== 'pending';
        $order = (int) $quote->id_order ? new Order((int) $quote->id_order) : null;

        $this->context->smarty->assign(['dqr' => [
            't' => $this->module->getStrings(),
            'css_vars' => $this->module->getCssVars(),
            'number' => $quote->getDisplayNumber(),
            'status' => $quote->status,
            'status_label' => $this->module->getStatusLabel($quote->status),
            'submitted_at' => Dotmquoterequest::isDateSet($quote->submitted_at) ? Tools::displayDate($quote->submitted_at) : '',
            'expires_at' => $quote->status === 'approved' && Dotmquoterequest::isDateSet($quote->expires_at) ? Tools::displayDate($quote->expires_at) : '',
            'pdf_url' => $hasPdf ? $this->module->getListUrl(['id_quote' => (int) $quote->id, 'pdf' => 1]) : '',
            'admin_note' => in_array($quote->status, ['cancelled', 'approved'], true) ? (string) $quote->admin_note : '',
            'customer_message' => (string) $quote->customer_message,
            'lines' => $lines,
            'has_line_discounts' => $showDiscount && $hasLineDiscounts,
            'total' => $quote->getFormattedTotal(),
            'discount' => $showDiscount ? DotmQuoteRequestManager::formatDiscountSummary($quote) : '',
            'discounted_total' => $showDiscount ? Dotmquoterequest::formatPrice($quote->getDiscountedTotal(), $idCurrency) : '',
            'activate_url' => $quote->status === 'approved' && !$quote->isExpired() ? $this->module->getActivateUrl($quote) : '',
            'order_url' => $order && Validate::isLoadedObject($order)
                ? $this->context->link->getPageLink('order-detail', true, null, ['id_order' => (int) $order->id])
                : '',
            'order_reference' => $order && Validate::isLoadedObject($order) ? $order->reference : '',
            'list_url' => $this->module->getListUrl(),
        ]]);

        $this->setTemplate('module:dotmquoterequest/views/templates/front/quotelist-detail.tpl');
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = $this->addMyAccountToBreadcrumb();
        $breadcrumb['links'][] = [
            'title' => $this->module->t('account_list'),
            'url' => $this->module->getListUrl(),
        ];

        return $breadcrumb;
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();
        $page['meta']['title'] = $this->module->t('account_list');
        $page['meta']['robots'] = 'noindex';

        return $page;
    }
}
