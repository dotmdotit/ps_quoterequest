<?php
/**
 * Azioni sui preventivi (approvazione, proroga, annullamento) ed email.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmQuoteRequestManager
{
    /**
     * Approva un preventivo in attesa: crea il carrello del cliente con le righe, lo sconto (regola
     * carrello riservata al cliente, con la stessa scadenza del preventivo) e il token del link,
     * poi manda al cliente l'email con il link e il PDF.
     *
     * @param string $discountType 'percent'|'amount'
     * @param float $discountValue
     * @param string $adminNote
     *
     * @return bool
     */
    public static function approve(DotmQuoteRequestQuote $quote, $discountType, $discountValue, $adminNote)
    {
        if ($quote->status !== 'pending' || !in_array($discountType, ['percent', 'amount'], true)) {
            return false;
        }

        $discountValue = (float) $discountValue;
        if ($discountValue <= 0 || ($discountType === 'percent' && $discountValue > 100)) {
            return false;
        }

        $lines = $quote->getLines();
        if (!$lines) {
            return false;
        }

        $customer = new Customer((int) $quote->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            return false;
        }

        $dateFrom = date('Y-m-d H:i:s');
        $dateTo = date('Y-m-d 23:59:59', strtotime('+' . Dotmquoterequest::getValidityDays() . ' days'));

        // 1. Carrello del cliente con le righe del preventivo
        $shop = new Shop((int) $quote->id_shop);
        $cart = new Cart();
        $cart->id_customer = (int) $customer->id;
        $cart->id_lang = (int) $quote->id_lang;
        $cart->id_currency = (int) $quote->id_currency;
        $cart->id_shop = (int) $quote->id_shop;
        $cart->id_shop_group = (int) $shop->id_shop_group;
        $cart->secure_key = $customer->secure_key;
        $idAddress = (int) Address::getFirstCustomerAddressId((int) $customer->id);
        if ($idAddress) {
            $cart->id_address_delivery = $idAddress;
            $cart->id_address_invoice = $idAddress;
        }
        if (!$cart->add()) {
            return false;
        }

        $added = false;
        foreach ($lines as $line) {
            $product = new Product((int) $line->id_product, false, (int) $quote->id_lang, (int) $quote->id_shop);
            if (!Validate::isLoadedObject($product) || !$product->active) {
                continue;
            }
            if ($cart->updateQty((int) $line->quantity, (int) $line->id_product, (int) $line->id_product_attribute ?: null, false, 'up', 0, $shop) !== false) {
                $added = true;
            }
        }
        if (!$added) {
            $cart->delete();

            return false;
        }

        // 2. Sconto riservato al cliente, applicato al carrello
        $module = Dotmquoterequest::get();
        $cartRule = new CartRule();
        $cartRule->name = [];
        foreach (Language::getLanguages(false) as $lang) {
            $cartRule->name[$lang['id_lang']] = Tools::substr($module->t('cart_rule_name', ['number' => $quote->getDisplayNumber()], (int) $lang['id_lang']), 0, 254);
        }
        $cartRule->id_customer = (int) $customer->id;
        $cartRule->code = Tools::strtoupper(Tools::passwdGen(16));
        $cartRule->quantity = 1;
        $cartRule->quantity_per_user = 1;
        $cartRule->date_from = $dateFrom;
        $cartRule->date_to = $dateTo;
        $cartRule->active = 1;
        $cartRule->highlight = 0;
        $cartRule->partial_use = 0;
        $cartRule->reduction_tax = 1;
        if ($discountType === 'percent') {
            $cartRule->reduction_percent = $discountValue;
        } else {
            $cartRule->reduction_amount = $discountValue;
            $cartRule->reduction_currency = (int) $quote->id_currency;
        }
        if (!$cartRule->add()) {
            $cart->delete();

            return false;
        }
        $cart->addCartRule((int) $cartRule->id);

        // 3. Preventivo approvato, con il token del link
        $quote->status = 'approved';
        $quote->discount_type = $discountType;
        $quote->discount_value = $discountValue;
        $quote->admin_note = (string) $adminNote;
        $quote->id_cart_rule = (int) $cartRule->id;
        $quote->id_cart = (int) $cart->id;
        $quote->token = bin2hex(random_bytes(32));
        $quote->approved_at = $dateFrom;
        $quote->expires_at = $dateTo;
        if (!$quote->save()) {
            return false;
        }

        self::sendApprovedEmail($quote, $customer);

        return true;
    }

    /**
     * Sposta la scadenza di un preventivo approvato (o scaduto): aggiorna anche lo sconto, e un
     * preventivo scaduto torna approvato, così il link del cliente funziona di nuovo.
     */
    public static function extendExpiry(DotmQuoteRequestQuote $quote, $newExpiresAt)
    {
        if (!in_array($quote->status, ['approved', 'expired'], true) || strtotime($newExpiresAt) <= time()) {
            return false;
        }

        $cartRule = new CartRule((int) $quote->id_cart_rule);
        if (!Validate::isLoadedObject($cartRule)) {
            return false;
        }

        $cartRule->date_to = $newExpiresAt;
        $cartRule->active = 1;
        if (!$cartRule->save()) {
            return false;
        }

        $quote->expires_at = $newExpiresAt;
        $quote->status = 'approved';

        return (bool) $quote->save();
    }

    /**
     * Annullamento dal negozio, per un preventivo da gestire o già mandato al cliente (in tal caso lo
     * sconto viene disattivato e il link smette di funzionare). Il cliente riceve un'email con la nota.
     */
    public static function cancel(DotmQuoteRequestQuote $quote, $adminNote)
    {
        if (!in_array($quote->status, ['pending', 'approved'], true)) {
            return false;
        }

        $quote->status = 'cancelled';
        $quote->admin_note = (string) $adminNote;
        $quote->cancelled_at = date('Y-m-d H:i:s');
        if (!$quote->save()) {
            return false;
        }

        self::disableCartRule($quote);

        $customer = new Customer((int) $quote->id_customer);
        if (Validate::isLoadedObject($customer)) {
            self::sendCancelledEmail($quote, $customer);
        }

        return true;
    }

    public static function disableCartRule(DotmQuoteRequestQuote $quote)
    {
        if (!(int) $quote->id_cart_rule) {
            return;
        }
        $cartRule = new CartRule((int) $quote->id_cart_rule);
        if (Validate::isLoadedObject($cartRule) && $cartRule->active) {
            $cartRule->active = 0;
            $cartRule->save();
        }
    }

    public static function formatDiscountSummary(DotmQuoteRequestQuote $quote)
    {
        if ($quote->discount_type === 'percent') {
            return rtrim(rtrim(number_format((float) $quote->discount_value, 2, ',', '.'), '0'), ',') . '%';
        }

        return Dotmquoterequest::formatPrice((float) $quote->discount_value, (int) $quote->id_currency);
    }

    /* ------------------------------------------------------------------
     * Email
     * ------------------------------------------------------------------ */

    /**
     * Lingua per le email: quella indicata se il modulo ha i template, altrimenti inglese o italiano.
     */
    private static function getMailLang($idLang)
    {
        $dir = _PS_MODULE_DIR_ . 'dotmquoterequest/mails/';
        foreach ([(int) $idLang, (int) Language::getIdByIso('en'), (int) Language::getIdByIso('it')] as $id) {
            $iso = $id ? Language::getIsoById($id) : '';
            if ($iso && is_file($dir . $iso . '/dotmquoterequest_approved.html')) {
                return $id;
            }
        }

        return (int) $idLang;
    }

    private static function send($idLang, $template, $subject, array $vars, $to, $toName, $idShop, $attachment = null, $replyTo = null, $replyToName = null)
    {
        $module = Dotmquoterequest::get();
        $vars['{dqr_color}'] = $module->getColor() ?: Dotmquoterequest::DEFAULT_COLOR;

        return Mail::Send(
            (int) $idLang,
            $template,
            $subject,
            $vars,
            $to,
            $toName,
            null,
            null,
            $attachment,
            null,
            _PS_MODULE_DIR_ . 'dotmquoterequest/mails/',
            false,
            (int) $idShop ?: null,
            null,
            $replyTo,
            $replyToName
        );
    }

    /**
     * Notifica al negozio di una nuova richiesta.
     */
    public static function sendNewRequestEmail(DotmQuoteRequestQuote $quote, Customer $customer)
    {
        $module = Dotmquoterequest::get();
        $idLang = self::getMailLang((int) Configuration::get('PS_LANG_DEFAULT'));
        $message = trim((string) $quote->customer_message);
        $vars = [
            '{customer_name}' => trim($customer->firstname . ' ' . $customer->lastname),
            '{customer_email}' => $customer->email,
            '{quote_number}' => $quote->getDisplayNumber(),
            '{lines_count}' => $quote->countLines(),
            '{products_html}' => self::buildProductsTable($quote, $idLang),
            '{products_txt}' => self::buildProductsList($quote),
            '{quote_total}' => $quote->getFormattedTotal(),
            '{customer_message}' => $message !== '' ? $message : '—',
            '{customer_message_html}' => $message !== '' ? nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) : '—',
        ];
        $adminLink = self::getAdminQuoteLink((int) $quote->id);
        $vars['{admin_link}'] = $adminLink !== '' ? $adminLink : $module->t('mail_admin_where', [], $idLang);
        $vars['{admin_link_html}'] = $adminLink !== ''
            ? '<a href="' . htmlspecialchars($adminLink, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 22px;border-radius:6px;background:' . ($module->getColor() ?: Dotmquoterequest::DEFAULT_COLOR) . ';color:#FFFFFF;font-weight:bold;text-decoration:none;">' . htmlspecialchars($module->t('mail_admin_button', [], $idLang), ENT_QUOTES, 'UTF-8') . '</a>'
            : htmlspecialchars($module->t('mail_admin_where', [], $idLang), ENT_QUOTES, 'UTF-8');

        $ok = true;
        foreach (Dotmquoterequest::getNotifyEmails() as $to) {
            $sent = self::send(
                $idLang,
                'dotmquoterequest_admin',
                $module->t('mail_subject_admin', ['number' => $quote->getDisplayNumber()], $idLang),
                $vars,
                $to,
                null,
                (int) $quote->id_shop,
                null,
                $customer->email,
                trim($customer->firstname . ' ' . $customer->lastname)
            );
            if (!$sent) {
                $ok = false;
                PrestaShopLogger::addLog('dotmquoterequest: notifica non inviata a ' . $to . ' (preventivo ' . $quote->getDisplayNumber() . ')', 3);
            }
        }

        return $ok;
    }

    private static function sendApprovedEmail(DotmQuoteRequestQuote $quote, Customer $customer)
    {
        $module = Dotmquoterequest::get();
        $idLang = self::getMailLang((int) $quote->id_lang);

        // Il preventivo in PDF; l'email parte comunque se il PDF non si genera
        $attachment = null;
        try {
            $pdf = new DotmQuoteRequestPdf($quote);
            $attachment = ['content' => $pdf->getContent(), 'name' => $pdf->getFilename(), 'mime' => 'application/pdf'];
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('dotmquoterequest: PDF del preventivo ' . $quote->getDisplayNumber() . ' non generato: ' . $e->getMessage(), 3);
        }

        $sent = self::send(
            $idLang,
            'dotmquoterequest_approved',
            $module->t('mail_subject_approved', ['number' => $quote->getDisplayNumber()], $idLang),
            [
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{quote_number}' => $quote->getDisplayNumber(),
                '{discount_summary}' => self::formatDiscountSummary($quote),
                '{quote_total}' => Dotmquoterequest::formatPrice($quote->getDiscountedTotal(), (int) $quote->id_currency),
                '{expires_at}' => Tools::displayDate($quote->expires_at),
                '{activation_link}' => Context::getContext()->link->getModuleLink('dotmquoterequest', 'activate', [
                    'id_quote' => (int) $quote->id,
                    'token' => $quote->token,
                ], true, $idLang, (int) $quote->id_shop),
            ],
            $customer->email,
            trim($customer->firstname . ' ' . $customer->lastname),
            (int) $quote->id_shop,
            $attachment
        );
        if (!$sent) {
            PrestaShopLogger::addLog('dotmquoterequest: email di approvazione non inviata (preventivo ' . $quote->getDisplayNumber() . ')', 3);
        }

        return $sent;
    }

    private static function sendCancelledEmail(DotmQuoteRequestQuote $quote, Customer $customer)
    {
        $module = Dotmquoterequest::get();
        $idLang = self::getMailLang((int) $quote->id_lang);
        $note = trim((string) $quote->admin_note);

        return self::send(
            $idLang,
            'dotmquoterequest_cancelled',
            $module->t('mail_subject_cancelled', ['number' => $quote->getDisplayNumber()], $idLang),
            [
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{quote_number}' => $quote->getDisplayNumber(),
                '{admin_note}' => $note !== '' ? $note : '—',
                '{admin_note_html}' => $note !== '' ? nl2br(htmlspecialchars($note, ENT_QUOTES, 'UTF-8')) : '—',
            ],
            $customer->email,
            trim($customer->firstname . ' ' . $customer->lastname),
            (int) $quote->id_shop
        );
    }

    /**
     * Righe del preventivo come tabella HTML (stili in linea: i client di posta ignorano i fogli di stile).
     */
    private static function buildProductsTable(DotmQuoteRequestQuote $quote, $idLang)
    {
        $module = Dotmquoterequest::get();
        $idCurrency = (int) $quote->id_currency;
        $cell = 'padding:8px 10px;border-bottom:1px solid #E5E5E5;vertical-align:top;';
        $right = $cell . 'text-align:right;white-space:nowrap;';
        $head = 'padding:8px 10px;border-bottom:1px solid #D4D4D4;background:#F5F5F5;color:#666666;font-weight:bold;text-align:left;';
        $esc = function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        };

        $rows = '';
        foreach ($quote->getLines() as $line) {
            $name = $esc($line->product_name);
            if ($line->combination_name) {
                $name .= '<br><span style="color:#666666;font-size:13px;">' . $esc($line->combination_name) . '</span>';
            }
            if ($line->product_reference) {
                $name .= '<br><span style="color:#999999;font-size:12px;">' . $esc($module->t('ref', [], $idLang)) . ' ' . $esc($line->product_reference) . '</span>';
            }
            $rows .= '<tr>'
                . '<td style="' . $cell . '">' . $name . '</td>'
                . '<td style="' . $right . '">' . (int) $line->quantity . '</td>'
                . '<td style="' . $right . '">' . $esc(Dotmquoterequest::formatPrice($line->unit_price_tax_incl, $idCurrency)) . '</td>'
                . '<td style="' . $right . '">' . $esc(Dotmquoterequest::formatPrice($line->getTotalTaxIncl(), $idCurrency)) . '</td>'
                . '</tr>';
        }

        return '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-size:14px;">'
            . '<thead><tr>'
            . '<th style="' . $head . '">' . $esc($module->t('col_product', [], $idLang)) . '</th>'
            . '<th style="' . $head . 'text-align:right;">' . $esc($module->t('col_qty', [], $idLang)) . '</th>'
            . '<th style="' . $head . 'text-align:right;">' . $esc($module->t('col_unit_price', [], $idLang)) . '</th>'
            . '<th style="' . $head . 'text-align:right;">' . $esc($module->t('col_total', [], $idLang)) . '</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '<tfoot><tr>'
            . '<td colspan="3" style="padding:10px;text-align:right;font-weight:bold;">' . $esc($module->t('list_total_tax_incl', [], $idLang)) . '</td>'
            . '<td style="padding:10px;text-align:right;font-weight:bold;white-space:nowrap;">' . $esc($quote->getFormattedTotal()) . '</td>'
            . '</tr></tfoot>'
            . '</table>';
    }

    /**
     * Le stesse righe per la versione testuale dell'email.
     */
    private static function buildProductsList(DotmQuoteRequestQuote $quote)
    {
        $idCurrency = (int) $quote->id_currency;
        $lines = [];
        foreach ($quote->getLines() as $line) {
            $lines[] = '- ' . (int) $line->quantity . ' x ' . $line->product_name
                . ($line->combination_name ? ' (' . $line->combination_name . ')' : '')
                . ($line->product_reference ? ' [' . $line->product_reference . ']' : '')
                . ': ' . Dotmquoterequest::formatPrice($line->unit_price_tax_incl, $idCurrency)
                . ' = ' . Dotmquoterequest::formatPrice($line->getTotalTaxIncl(), $idCurrency);
        }

        return implode("\n", $lines);
    }

    /**
     * Link::getAdminLink() non funziona dal front office (da cui parte la notifica), quindi il link
     * al preventivo si costruisce con la cartella admin salvata all'installazione/configurazione.
     * Senza cartella nota il link resta vuoto e nell'email non compare.
     */
    private static function getAdminQuoteLink($idQuote)
    {
        $adminDir = trim((string) Configuration::get(Dotmquoterequest::CFG_ADMIN_DIR), '/');
        if ($adminDir === '') {
            return '';
        }

        return Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . $adminDir
            . '/index.php?controller=' . Dotmquoterequest::ADMIN_TAB
            . '&id_quote=' . (int) $idQuote
            . '&view' . DotmQuoteRequestQuote::$definition['table'];
    }
}
