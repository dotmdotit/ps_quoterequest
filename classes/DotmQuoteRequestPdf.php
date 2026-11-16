<?php
/**
 * PDF di un preventivo (logo, cliente, righe con sconti, totali, condizioni, dati del negozio),
 * generato con PDFGenerator (TCPDF) del core come le fatture. Template in views/templates/pdf/.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmQuoteRequestPdf
{
    /** Larghezza del logo nell'intestazione, in px (l'altezza segue le proporzioni) */
    const LOGO_WIDTH = 180;

    /** @var DotmQuoteRequestQuote */
    private $quote;

    /** @var Context */
    private $context;

    /** @var Dotmquoterequest */
    private $module;

    public function __construct(DotmQuoteRequestQuote $quote)
    {
        $this->quote = $quote;
        $this->context = Context::getContext();
        $this->module = Dotmquoterequest::get();
    }

    public function getFilename()
    {
        $name = preg_replace('/[^A-Za-z0-9-]/', '', $this->module->t('pdf_filename', [], $this->getLangId()));

        return ($name ?: 'Quote') . '-' . preg_replace('/[^A-Za-z0-9-]/', '', $this->quote->getDisplayNumber()) . '.pdf';
    }

    /**
     * @return string il PDF come stringa (allegato email)
     */
    public function getContent()
    {
        return $this->build()->render($this->getFilename(), 'S');
    }

    /**
     * Manda il PDF al browser come download e termina.
     */
    public function download()
    {
        $pdf = $this->build();
        // Come PDF::render() del core: qualsiasi output già nel buffer farebbe rifiutare l'invio a TCPDF
        if (ob_get_level() && ob_get_length() > 0) {
            ob_clean();
        }
        $pdf->render($this->getFilename(), 'D');
        exit;
    }

    private function getLangId()
    {
        return (int) $this->quote->id_lang ?: (int) $this->context->language->id;
    }

    private function build()
    {
        $idLang = $this->getLangId();
        $vars = $this->getTemplateVars($idLang);

        $pdf = new PDFGenerator((bool) Configuration::get('PS_PDF_USE_CACHE'));
        $pdf->setFontForLang(Language::getIsoById($idLang) ?: 'en');
        $pdf->SetTitle($vars['title']);
        $pdf->SetAuthor($vars['shop']['name']);
        $pdf->createHeader($this->fetch('header.tpl', $vars));
        $pdf->createFooter($this->fetch('footer.tpl', $vars));
        $pdf->createContent($this->fetch('quote.tpl', $vars));
        $pdf->writePage();

        return $pdf;
    }

    /**
     * Template del modulo, o l'override del tema in themes/<tema>/modules/dotmquoterequest/views/templates/pdf/.
     */
    private function fetch($template, array $vars)
    {
        $file = _PS_MODULE_DIR_ . 'dotmquoterequest/views/templates/pdf/' . $template;
        $override = _PS_THEME_DIR_ . 'modules/dotmquoterequest/views/templates/pdf/' . $template;
        if (is_file($override)) {
            $file = $override;
        }

        $data = $this->context->smarty->createData($this->context->smarty);
        $data->assign($vars);

        return $this->context->smarty->createTemplate($file, $data)->fetch();
    }

    private function getTemplateVars($idLang)
    {
        $quote = $this->quote;
        $idCurrency = (int) $quote->id_currency;
        $price = function ($amount) use ($idCurrency) {
            return Dotmquoterequest::formatPrice($amount, $idCurrency);
        };

        $lines = [];
        $total = $discount = $netTaxExcl = 0;
        $hasLineDiscounts = false;
        foreach ($quote->getLines() as $line) {
            $listTotal = $line->getTotalTaxIncl();
            $lineDiscount = $line->getDiscountTaxIncl();
            $net = $listTotal - $lineDiscount;
            // Quota senza IVA della riga, dai prezzi salvati con il preventivo
            $ratio = (float) $line->unit_price_tax_incl > 0 ? (float) $line->unit_price_tax_excl / (float) $line->unit_price_tax_incl : 1;

            $total += $listTotal;
            $discount += $lineDiscount;
            $hasLineDiscounts = $hasLineDiscounts || $lineDiscount > 0;
            $netTaxExcl += $net * $ratio;

            $lines[] = [
                'name' => $line->product_name,
                'variant' => (string) $line->combination_name,
                'reference' => (string) $line->product_reference,
                'quantity' => (int) $line->quantity,
                'unit_price' => $price($line->unit_price_tax_incl),
                'list_total' => $price($listTotal),
                'discount_percent' => (float) $line->discount_percent > 0
                    ? rtrim(rtrim(number_format((float) $line->discount_percent, 2, ',', ''), '0'), ',') . '%'
                    : '',
                'discount' => $lineDiscount > 0 ? $price($lineDiscount) : '',
                'net_total' => $price($net),
            ];
        }

        // Sconto unico sul preventivo invece che per riga
        if ($discount <= 0 && $quote->discount_type) {
            $discount = $total - $quote->getDiscountedTotal();
            $netTaxExcl = $total > 0 ? $netTaxExcl * ($total - $discount) / $total : 0;
        }
        $net = $total - $discount;

        $issuedAt = Dotmquoterequest::isDateSet($quote->approved_at) ? $quote->approved_at : date('Y-m-d H:i:s');
        $expiresAt = Dotmquoterequest::isDateSet($quote->expires_at)
            ? $quote->expires_at
            : date('Y-m-d H:i:s', strtotime($issuedAt . ' +' . Dotmquoterequest::getValidityDays() . ' days'));
        $expiresLabel = Tools::displayDate($expiresAt, false);

        $strings = [];
        foreach ($this->module->getStrings($idLang) as $key => $text) {
            if (strpos($key, 'pdf_') === 0) {
                $strings[substr($key, 4)] = strtr($text, ['{date}' => $expiresLabel, '{methods}' => $this->getPaymentMethods($idLang)]);
            }
        }

        return [
            't' => $strings,
            'title' => $this->module->t('pdf_title', [], $idLang) . ' ' . $quote->getDisplayNumber(),
            'number' => $quote->getDisplayNumber(),
            'issued_at' => Tools::displayDate($issuedAt, false),
            'expires_at' => $expiresLabel,
            'logo' => $this->getLogo(),
            'customer' => $this->getCustomer(),
            'lines' => $lines,
            'has_discounts' => $discount > 0,
            'has_line_discounts' => $hasLineDiscounts,
            'total' => $price($total),
            'discount' => $price($discount),
            'net' => $price($net),
            'net_tax_excl' => $price($netTaxExcl),
            'tax' => $price($net - $netTaxExcl),
            'customer_message' => (string) $quote->customer_message,
            'payment_restricted' => (bool) Dotmquoterequest::getAllowedPaymentModules(),
            'bank' => $this->getBankDetails(),
            'shop' => $this->getShop($idLang),
        ];
    }

    /**
     * Nomi dei pagamenti ammessi per i preventivi (vuoto se sono ammessi tutti).
     */
    private function getPaymentMethods($idLang)
    {
        $names = [];
        foreach (Dotmquoterequest::getAllowedPaymentModules() as $name) {
            $module = Module::getInstanceByName($name);
            $names[] = $module ? $module->displayName : $name;
        }

        return implode(', ', $names);
    }

    /**
     * Coordinate del bonifico (modulo ps_wirepayment), se il bonifico è tra i pagamenti ammessi e configurato.
     */
    private function getBankDetails()
    {
        if (!Module::isEnabled('ps_wirepayment') || !Dotmquoterequest::isPaymentModuleAllowed('ps_wirepayment')) {
            return null;
        }
        $idShop = (int) $this->quote->id_shop ?: null;
        $bank = [
            'owner' => (string) Configuration::get('BANK_WIRE_OWNER', null, null, $idShop),
            'details' => (string) Configuration::get('BANK_WIRE_DETAILS', null, null, $idShop),
            'address' => (string) Configuration::get('BANK_WIRE_ADDRESS', null, null, $idShop),
        ];

        return $bank['owner'] || $bank['details'] ? $bank : null;
    }

    /**
     * Logo delle fatture (o, in mancanza, del negozio), dimensionato come nell'intestazione delle fatture.
     */
    private function getLogo()
    {
        $idShop = (int) $this->quote->id_shop ?: null;
        foreach (['PS_LOGO_INVOICE', 'PS_LOGO'] as $key) {
            $file = Configuration::get($key, null, null, $idShop);
            $path = _PS_IMG_DIR_ . $file;
            if ($file && is_file($path)) {
                $size = @getimagesize($path);
                $height = $size && $size[0] > 0 ? (int) round(self::LOGO_WIDTH * $size[1] / $size[0]) : 0;

                return ['path' => $path, 'width' => self::LOGO_WIDTH, 'height' => $height];
            }
        }

        return null;
    }

    private function getCustomer()
    {
        $customer = new Customer((int) $this->quote->id_customer);
        $data = [
            'name' => trim($customer->firstname . ' ' . $customer->lastname),
            'company' => (string) $customer->company,
            'email' => (string) $customer->email,
            'address' => [],
            'vat_number' => '',
        ];

        $idAddress = (int) Address::getFirstCustomerAddressId((int) $customer->id);
        if ($idAddress) {
            $address = new Address($idAddress, (int) $this->quote->id_lang);
            if ($address->company) {
                $data['company'] = $address->company;
            }
            $data['vat_number'] = (string) $address->vat_number;
            $data['address'] = array_values(array_filter([
                $address->address1,
                $address->address2,
                trim($address->postcode . ' ' . $address->city . ($address->id_state ? ' (' . State::getNameById($address->id_state) . ')' : '')),
                $address->country,
                $address->phone ?: $address->phone_mobile,
            ]));
        }

        return $data;
    }

    private function getShop($idLang)
    {
        $idShop = (int) $this->quote->id_shop ?: null;
        $get = function ($key) use ($idShop) {
            return (string) Configuration::get($key, null, null, $idShop);
        };
        $countryId = (int) $get('PS_SHOP_COUNTRY_ID');

        return [
            'name' => $get('PS_SHOP_NAME'),
            'address' => implode(' - ', array_filter([
                $get('PS_SHOP_ADDR1'),
                $get('PS_SHOP_ADDR2'),
                trim($get('PS_SHOP_CODE') . ' ' . $get('PS_SHOP_CITY')),
                $countryId ? Country::getNameById((int) $idLang, $countryId) : '',
            ])),
            'phone' => $get('PS_SHOP_PHONE'),
            'email' => $get('PS_SHOP_EMAIL'),
            'details' => $get('PS_SHOP_DETAILS'),
            'website' => Tools::getShopDomainSsl(false),
        ];
    }
}
