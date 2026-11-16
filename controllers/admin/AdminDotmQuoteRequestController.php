<?php
/**
 * Clienti → Preventivi: elenco, dettaglio, modifica e approvazione dei preventivi, creazione per conto di un cliente.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'dotmquoterequest/dotmquoterequest.php';

class AdminDotmQuoteRequestController extends ModuleAdminController
{
    /** @var array id_quote => expires_at, filled by getList() for the row action */
    private $listExpiries = [];

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'dotmquoterequest_quote';
        $this->identifier = 'id_quote';
        $this->className = 'DotmQuoteRequestQuote';
        $this->lang = false;
        $this->deleted = false;
        $this->list_no_link = true;

        $this->_select = '
            CONCAT(c.firstname, \' \', c.lastname) AS customer_name,
            (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'dotmquoterequest_detail` qd WHERE qd.id_quote = a.id_quote) AS lines_count,
            (SELECT SUM(qd.quantity * qd.unit_price_tax_incl) FROM `' . _DB_PREFIX_ . 'dotmquoterequest_detail` qd WHERE qd.id_quote = a.id_quote) AS total_price
        ';
        $this->_join = 'LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = a.id_customer';
        $this->_orderBy = 'date_add';
        $this->_orderWay = 'DESC';
        $this->_defaultOrderBy = 'date_add';
        $this->_defaultOrderWay = 'DESC';

        // $this->l() needs the translator set up by parent::__construct(), so
        // anything using it (row actions, fields_list, status labels) must
        // come after calling it, not before.
        parent::__construct();

        $this->addRowAction('view');
        $this->addRowAction('extend');
        $this->addRowAction('delete');

        $this->fields_list = [
            'number' => [
                'title' => $this->l('Numero'),
                'align' => 'center',
                'width' => 90,
                'filter_key' => 'a!number',
                'callback' => 'printNumberColumn',
            ],
            'customer_name' => ['title' => $this->l('Cliente'), 'havingFilter' => true],
            'lines_count' => ['title' => $this->l('Articoli'), 'align' => 'center', 'search' => false],
            'total_price' => [
                'title' => $this->l('Prezzo totale'),
                'align' => 'right',
                'search' => false,
                'callback' => 'printPriceColumn',
            ],
            'status' => [
                'title' => $this->l('Stato'),
                'align' => 'center',
                'type' => 'select',
                'list' => array_diff_key($this->statusLabels(), ['draft' => 1]),
                'filter_key' => 'a!status',
                'callback' => 'printStatusColumn',
            ],
            'date_add' => ['title' => $this->l('Creato il'), 'type' => 'datetime'],
            'submitted_at' => ['title' => $this->l('Inviato il'), 'type' => 'datetime'],
            'expires_at' => ['title' => $this->l('Scade il'), 'type' => 'datetime'],
        ];
    }

    private function statusLabels()
    {
        return [
            'draft' => $this->l('Bozza'),
            'pending' => $this->l('Da gestire'),
            'approved' => $this->l('Mandato al cliente'),
            'awaiting_payment' => $this->l('In attesa di pagamento'),
            'converted' => $this->l('Pagamento accettato'),
            'expired' => $this->l('Scaduto'),
            'cancelled' => $this->l('Annullato'),
        ];
    }

    private function statusColors()
    {
        return [
            'draft' => 'default',
            'pending' => 'warning',
            'approved' => 'info',
            'awaiting_payment' => 'primary',
            'converted' => 'success',
            'expired' => 'default',
            'cancelled' => 'danger',
        ];
    }

    public function printStatusColumn($status, $row)
    {
        $labels = $this->statusLabels();
        $colors = $this->statusColors();
        $color = isset($colors[$status]) ? $colors[$status] : 'default';
        $label = isset($labels[$status]) ? $labels[$status] : $status;

        return '<span class="label label-' . $color . '">' . htmlspecialchars($label) . '</span>';
    }

    public function printNumberColumn($value, $row)
    {
        return htmlspecialchars(DotmQuoteRequestQuote::formatNumber($value, $row['id_quote']));
    }

    public function printPriceColumn($value, $row)
    {
        return Dotmquoterequest::formatPrice((float) $value);
    }

    public function renderList()
    {
        // Never show empty drafts the customer never submitted.
        $this->_where = ' AND a.status != \'draft\'';

        return parent::renderList();
    }

    public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false)
    {
        parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);

        // The "extend expiry" action only makes sense for approved or expired quotes.
        $this->listExpiries = [];
        $skip = [];
        foreach ($this->_list ?: [] as $row) {
            $id = (int) $row['id_quote'];
            if (in_array($row['status'], ['approved', 'expired'], true)) {
                $this->listExpiries[$id] = $row['expires_at'];
            } else {
                $skip[] = $id;
            }
        }
        $this->addRowActionSkipList('extend', $skip);
    }

    public function displayExtendLink($token, $id, $name = null)
    {
        $expiresAt = isset($this->listExpiries[$id]) ? $this->listExpiries[$id] : null;

        return '<a href="#" class="js-qr-extend" data-id="' . (int) $id . '"'
            . ' data-expires="' . ($expiresAt ? htmlspecialchars(date('Y-m-d', strtotime($expiresAt))) : '') . '"'
            . ' title="' . htmlspecialchars($this->l('Proroga scadenza')) . '">'
            . '<i class="icon-calendar"></i> ' . htmlspecialchars($this->l('Proroga scadenza'))
            . '</a>';
    }

    public function renderKpis()
    {
        $stats = $this->getDashboardStats();

        $kpis = [];

        $kpi = new HelperKpi();
        $kpi->id = 'box-dqr-received';
        $kpi->icon = 'icon-envelope';
        $kpi->color = 'color1';
        $kpi->title = $this->l('Preventivi ricevuti');
        $kpi->subtitle = $this->l('Totale storico');
        $kpi->value = $stats['received'];
        $kpi->refresh = false;
        $kpis[] = $kpi->generate();

        $kpi = new HelperKpi();
        $kpi->id = 'box-dqr-pending';
        $kpi->icon = 'icon-clock-o';
        $kpi->color = 'color2';
        $kpi->title = $this->l('Da gestire');
        $kpi->subtitle = $this->l('Richiedono una revisione');
        $kpi->value = $stats['pending'];
        $kpi->refresh = false;
        $kpis[] = $kpi->generate();

        $kpi = new HelperKpi();
        $kpi->id = 'box-dqr-approved';
        $kpi->icon = 'icon-check';
        $kpi->color = 'color3';
        $kpi->title = $this->l('Mandati al cliente');
        $kpi->subtitle = $this->l('Valore stimato') . ': ' . $stats['approved_total'];
        $kpi->value = $stats['approved_count'];
        $kpi->refresh = false;
        $kpis[] = $kpi->generate();

        $kpi = new HelperKpi();
        $kpi->id = 'box-dqr-awaiting-payment';
        $kpi->icon = 'icon-money';
        $kpi->color = 'color2';
        $kpi->title = $this->l('In attesa di pagamento');
        $kpi->subtitle = $this->l('Ordini da incassare') . ': ' . $stats['awaiting_total'];
        $kpi->value = $stats['awaiting_count'];
        $kpi->refresh = false;
        $kpis[] = $kpi->generate();

        $kpi = new HelperKpi();
        $kpi->id = 'box-dqr-converted';
        $kpi->icon = 'icon-shopping-cart';
        $kpi->color = 'color4';
        $kpi->title = $this->l('Pagamento accettato');
        $kpi->subtitle = $this->l('Fatturato generato') . ': ' . $stats['converted_total'];
        $kpi->value = $stats['converted_count'];
        $kpi->refresh = false;
        $kpis[] = $kpi->generate();

        $helperKpiRow = new HelperKpiRow();
        $helperKpiRow->kpis = $kpis;

        return $helperKpiRow->generate();
    }

    private function getDashboardStats()
    {
        $table = _DB_PREFIX_ . 'dotmquoterequest_quote';

        $received = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE status != \'draft\''
        );

        $pending = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE status = \'pending\''
        );

        $approvedIds = Db::getInstance()->executeS(
            'SELECT id_quote FROM `' . $table . '` WHERE status = \'approved\''
        );
        $approvedTotal = 0;
        foreach ($approvedIds ?: [] as $row) {
            $approvedTotal += (new DotmQuoteRequestQuote((int) $row['id_quote']))->getDiscountedTotal();
        }

        $awaitingCount = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE status = \'awaiting_payment\''
        );
        $awaitingTotal = (float) Db::getInstance()->getValue(
            'SELECT SUM(o.total_paid) FROM `' . $table . '` qr
             INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order = qr.id_order
             WHERE qr.status = \'awaiting_payment\''
        );

        $convertedCount = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE status = \'converted\''
        );
        $convertedTotal = (float) Db::getInstance()->getValue(
            'SELECT SUM(o.total_paid) FROM `' . $table . '` qr
             INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order = qr.id_order
             WHERE qr.status = \'converted\''
        );

        return [
            'received' => $received,
            'pending' => $pending,
            'approved_count' => count($approvedIds ?: []),
            'approved_total' => Dotmquoterequest::formatPrice($approvedTotal),
            'awaiting_count' => $awaitingCount,
            'awaiting_total' => Dotmquoterequest::formatPrice($awaitingTotal),
            'converted_count' => $convertedCount,
            'converted_total' => Dotmquoterequest::formatPrice($convertedTotal),
        ];
    }

    public function renderView()
    {
        $idQuoteRequest = (int) Tools::getValue('id_quote');
        $quote = new DotmQuoteRequestQuote($idQuoteRequest);

        if (!Validate::isLoadedObject($quote)) {
            $this->errors[] = $this->l('Preventivo non trovato.');

            return '';
        }

        $customer = new Customer((int) $quote->id_customer);
        $idCurrency = (int) $quote->id_currency;

        // Lines with raw numbers too: on a pending quote they feed the editable table (views/js/admin.js)
        $lines = [];
        $hasLineDiscounts = false;
        foreach ($quote->getLines() as $line) {
            $hasLineDiscounts = $hasLineDiscounts || (float) $line->discount_percent > 0;
            $lines[] = [
                'id' => (int) $line->id,
                'id_product' => (int) $line->id_product,
                'id_product_attribute' => (int) $line->id_product_attribute,
                'product_name' => $line->product_name,
                'product_reference' => $line->product_reference,
                'combination_name' => $line->combination_name,
                'quantity' => (int) $line->quantity,
                'unit_price_raw' => (float) $line->unit_price_tax_incl,
                'discount_percent' => (float) $line->discount_percent,
                'discount_percent_label' => rtrim(rtrim(number_format((float) $line->discount_percent, 2, ',', ''), '0'), ','),
                'unit_price' => Dotmquoterequest::formatPrice($line->unit_price_tax_incl, $idCurrency),
                'total' => Dotmquoterequest::formatPrice($line->getTotalTaxIncl(), $idCurrency),
                'discount' => Dotmquoterequest::formatPrice($line->getDiscountTaxIncl(), $idCurrency),
                'net_total' => Dotmquoterequest::formatPrice($line->getNetTotalTaxIncl(), $idCurrency),
            ];
        }

        $order = $quote->id_order ? new Order((int) $quote->id_order) : null;

        $this->tpl_view_vars = [
            'quote' => $quote,
            'quote_number' => $quote->getDisplayNumber(),
            'status_colors' => $this->statusColors(),
            'order_reference' => $order && Validate::isLoadedObject($order) ? $order->reference : '',
            'order_link' => $order && Validate::isLoadedObject($order)
                ? $this->context->link->getAdminLink('AdminOrders', true, [], ['id_order' => (int) $order->id, 'vieworder' => 1])
                : '',
            'can_cancel_sent' => $quote->status === 'approved',
            'pdf_link' => self::$currentIndex . '&id_quote=' . (int) $quote->id . '&viewdotmquoterequest_quote&downloadQuotePdf=1&token=' . $this->token,
            'customer' => $customer,
            'lines' => $lines,
            'has_line_discounts' => $hasLineDiscounts,
            'lines_discount_total' => Dotmquoterequest::formatPrice($quote->getLinesDiscountTotal(), $idCurrency),
            'lines_net_total' => Dotmquoterequest::formatPrice($quote->getTotalTaxIncl() - $quote->getLinesDiscountTotal(), $idCurrency),
            'max_lines' => DotmQuoteRequestDetail::MAX_LINES,
            'quote_total' => $quote->getFormattedTotal(),
            'currency_sign' => (new Currency($idCurrency))->sign,
            'status_labels' => $this->statusLabels(),
            'discount_summary' => $quote->discount_type ? DotmQuoteRequestManager::formatDiscountSummary($quote) : null,
            'can_extend' => in_array($quote->status, ['approved', 'expired'], true),
            'expires_at_value' => $quote->expires_at ? date('Y-m-d', strtotime($quote->expires_at)) : '',
            'min_expires_at' => date('Y-m-d'),
            'activate_link' => ($quote->status === 'approved' && $quote->token)
                ? $this->module->getActivateUrl($quote)
                : null,
            'token' => $this->token,
            'current_index' => self::$currentIndex,
        ];

        $this->context->smarty->assign($this->tpl_view_vars);

        return $this->createTemplate('view.tpl')->fetch();
    }

    /**
     * "Aggiungi nuovo": builds a quote on behalf of a customer. Existing quotes
     * are never edited through a form, so the edit URL falls back to the view.
     */
    public function renderForm()
    {
        $idQuoteRequest = (int) Tools::getValue('id_quote');
        if ($idQuoteRequest) {
            Tools::redirectAdmin(self::$currentIndex . '&id_quote=' . $idQuoteRequest . '&viewdotmquoterequest_quote&token=' . $this->token);
        }

        $this->context->smarty->assign([
            'token' => $this->token,
            'current_index' => self::$currentIndex,
            'max_lines' => DotmQuoteRequestDetail::MAX_LINES,
            'posted_customer_message' => (string) Tools::getValue('customer_message'),
            'prefill_json' => json_encode($this->getCreateFormPrefill()),
        ]);

        return $this->createTemplate('form.tpl')->fetch();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addJS(_MODULE_DIR_ . 'dotmquoterequest/views/js/admin.js');
        Media::addJsDef([
            'dqrExtend' => [
                'action' => self::$currentIndex . '&token=' . $this->token,
                'minDate' => date('Y-m-d'),
                'labels' => [
                    'title' => $this->l('Proroga scadenza preventivo'),
                    'date' => $this->l('Nuova data di scadenza'),
                    'help' => $this->l('Il preventivo resterà valido fino alle 23:59 del giorno scelto. Se è scaduto tornerà "Mandato al cliente" e il link del cliente funzionerà di nuovo.'),
                    'cancel' => $this->l('Annulla'),
                    'save' => $this->l('Salva'),
                ],
            ],
            'dqrEdit' => [
                'ajaxUrl' => self::$currentIndex . '&token=' . $this->token . '&ajax=1',
                'maxLines' => DotmQuoteRequestDetail::MAX_LINES,
                'currencyIso' => $this->getViewedCurrencyIso(),
                'labels' => [
                    'noResults' => $this->l('Nessun risultato'),
                    'searching' => $this->l('Ricerca in corso...'),
                    'remove' => $this->l('Rimuovi'),
                    'inactive' => $this->l('non attivo'),
                    'variants' => $this->l('varianti'),
                    'new' => $this->l('nuovo'),
                    'alreadyAdded' => $this->l('Prodotto già presente: aumentane la quantità.'),
                    'maxLines' => $this->l('Hai raggiunto il numero massimo di righe.'),
                    'noLines' => $this->l('Il preventivo deve contenere almeno un prodotto.'),
                    'noDiscount' => $this->l('Per approvare il preventivo imposta uno sconto maggiore di zero.'),
                    'cancelConfirm' => $this->l('Vuoi annullare questo preventivo? Il cliente riceverà una mail e, se il preventivo gli era già stato mandato, il link smetterà di funzionare.'),
                ],
            ],
            'dqrCreate' => [
                'ajaxUrl' => self::$currentIndex . '&token=' . $this->token . '&ajax=1',
                'maxLines' => DotmQuoteRequestDetail::MAX_LINES,
                'currencyIso' => Currency::getDefaultCurrency()->iso_code,
                'labels' => [
                    'noResults' => $this->l('Nessun risultato'),
                    'searching' => $this->l('Ricerca in corso...'),
                    'change' => $this->l('Cambia'),
                    'remove' => $this->l('Rimuovi'),
                    'inactive' => $this->l('non attivo'),
                    'alreadyAdded' => $this->l('Prodotto già presente: aumentane la quantità.'),
                    'maxLines' => $this->l('Hai raggiunto il numero massimo di righe.'),
                    'noCustomer' => $this->l('Seleziona un cliente.'),
                    'noLines' => $this->l('Aggiungi almeno un prodotto.'),
                ],
            ],
        ]);
    }

    /**
     * Valuta del preventivo aperto (per i prezzi calcolati in pagina), altrimenti quella predefinita.
     */
    private function getViewedCurrencyIso()
    {
        $quote = new DotmQuoteRequestQuote((int) Tools::getValue('id_quote'));
        $currency = Validate::isLoadedObject($quote) ? new Currency((int) $quote->id_currency) : null;

        return $currency && Validate::isLoadedObject($currency) ? $currency->iso_code : Currency::getDefaultCurrency()->iso_code;
    }

    public function ajaxProcessSearchCustomers()
    {
        $query = trim((string) Tools::getValue('q'));
        $results = [];

        if (Tools::strlen($query) >= 2) {
            foreach (Customer::searchByName($query, 20) ?: [] as $row) {
                if (!empty($row['deleted']) || !empty($row['is_guest'])) {
                    continue;
                }
                $results[] = [
                    'id' => (int) $row['id_customer'],
                    'name' => trim($row['firstname'] . ' ' . $row['lastname']),
                    'email' => $row['email'],
                    'company' => isset($row['company']) ? (string) $row['company'] : '',
                ];
            }
        }

        $this->ajaxJson(['results' => $results]);
    }

    public function ajaxProcessSearchProducts()
    {
        $query = trim((string) Tools::getValue('q'));
        $idCustomer = (int) Tools::getValue('id_customer');
        $idLang = (int) $this->context->language->id;
        $results = [];

        if (Tools::strlen($query) >= 2) {
            foreach (Product::searchByName($idLang, $query, null, 20) ?: [] as $row) {
                $product = new Product((int) $row['id_product'], false, $idLang);
                if (Validate::isLoadedObject($product)) {
                    $results[] = $this->presentProduct($product, $idCustomer, $idLang);
                }
            }
        }

        $this->ajaxJson(['results' => $results]);
    }

    /**
     * Product with its variants and customer prices, in the shape the
     * creation form's JS expects (search results and re-filled lines).
     */
    private function presentProduct(Product $product, $idCustomer, $idLang)
    {
        $idProduct = (int) $product->id;

        $variants = [];
        foreach ($product->getAttributesResume($idLang) ?: [] as $combination) {
            $idProductAttribute = (int) $combination['id_product_attribute'];
            $variants[] = [
                'id' => $idProductAttribute,
                'label' => $combination['attribute_designation'],
                'price' => $this->customerPrice($idProduct, $idProductAttribute, $idCustomer),
                'default' => (int) $product->cache_default_attribute === $idProductAttribute,
            ];
        }

        return [
            'id' => $idProduct,
            'name' => $product->name,
            'reference' => (string) $product->reference,
            'active' => (bool) $product->active,
            'price' => $this->customerPrice($idProduct, 0, $idCustomer),
            'variants' => $variants,
        ];
    }

    /**
     * After a failed submit, rebuilds customer and lines from POST so the
     * admin does not have to start over.
     */
    private function getCreateFormPrefill()
    {
        if (!Tools::isSubmit('submitDqrcreate')) {
            return null;
        }

        $idLang = (int) $this->context->language->id;
        $prefill = ['customer' => null, 'lines' => []];

        $customer = new Customer((int) Tools::getValue('id_customer'));
        if (Validate::isLoadedObject($customer)) {
            $prefill['customer'] = [
                'id' => (int) $customer->id,
                'name' => trim($customer->firstname . ' ' . $customer->lastname),
                'email' => $customer->email,
                'company' => (string) $customer->company,
            ];
        }

        $productIds = (array) Tools::getValue('line_product', []);
        $attributeIds = (array) Tools::getValue('line_attribute', []);
        $quantities = (array) Tools::getValue('line_qty', []);

        foreach ($productIds as $i => $idProduct) {
            $product = new Product((int) $idProduct, false, $idLang);
            if (!Validate::isLoadedObject($product)) {
                continue;
            }
            $prefill['lines'][] = [
                'product' => $this->presentProduct($product, (int) $customer->id, $idLang),
                'id_product_attribute' => isset($attributeIds[$i]) ? (int) $attributeIds[$i] : 0,
                'qty' => isset($quantities[$i]) ? max(1, (int) $quantities[$i]) : 1,
            ];
        }

        return $prefill;
    }

    private function ajaxJson(array $data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Tax-included unit price as the given customer would see it (group
     * reductions and customer-specific prices included).
     */
    private function customerPrice($idProduct, $idProductAttribute, $idCustomer)
    {
        return (float) Product::getPriceStatic(
            (int) $idProduct,
            true,
            $idProductAttribute ? (int) $idProductAttribute : null,
            6,
            null,
            false,
            true,
            1,
            false,
            $idCustomer ? (int) $idCustomer : null
        );
    }

    public function postProcess()
    {
        if (Tools::getIsset('downloadQuotePdf')) {
            $quote = new DotmQuoteRequestQuote((int) Tools::getValue('id_quote'));
            if (Validate::isLoadedObject($quote) && $quote->status !== 'draft') {
                (new DotmQuoteRequestPdf($quote))->download();
            }
            $this->errors[] = $this->l('Preventivo non trovato.');

            return;
        }

        if (Tools::isSubmit('submitDqrapprove')) {
            $this->processApprove();
        } elseif (Tools::isSubmit('submitDqrsave')) {
            $this->processSaveLines();
        } elseif (Tools::isSubmit('submitDqrreject')) {
            $this->processReject();
        } elseif (Tools::isSubmit('submitDqrextend')) {
            $this->processExtend();
        } elseif (Tools::isSubmit('submitDqrcreate')) {
            $this->processCreate();
        } else {
            parent::postProcess();
        }
    }

    private function processApprove()
    {
        $idQuoteRequest = (int) Tools::getValue('id_quote');
        $quote = new DotmQuoteRequestQuote($idQuoteRequest);

        if (!Validate::isLoadedObject($quote)) {
            $this->errors[] = $this->l('Preventivo non trovato.');

            return;
        }

        if (!$this->saveLines($quote)) {
            return;
        }

        // The per-line discounts become a single fixed-amount cart rule on the quote's cart
        $discount = $quote->getLinesDiscountTotal();
        if ($discount <= 0) {
            $this->errors[] = $this->l('Per approvare il preventivo imposta uno sconto maggiore di zero. Le modifiche alle righe sono state salvate.');

            return;
        }

        $ok = DotmQuoteRequestManager::approve($quote, 'amount', $discount, (string) Tools::getValue('admin_note'));

        if ($ok) {
            $this->confirmations[] = $this->l('Preventivo approvato e link inviato al cliente.');
            Tools::redirectAdmin(self::$currentIndex . '&id_quote=' . $idQuoteRequest . '&viewdotmquoterequest_quote&token=' . $this->token);
        } else {
            $this->errors[] = $this->l('Impossibile approvare il preventivo. Controlla lo sconto inserito e le righe selezionate.');
        }
    }

    /**
     * "Salva modifiche": stores the edited lines of a pending quote without approving it.
     */
    private function processSaveLines()
    {
        $idQuoteRequest = (int) Tools::getValue('id_quote');
        $quote = new DotmQuoteRequestQuote($idQuoteRequest);

        if (!Validate::isLoadedObject($quote)) {
            $this->errors[] = $this->l('Preventivo non trovato.');

            return;
        }

        if ($this->saveLines($quote)) {
            Tools::redirectAdmin(self::$currentIndex . '&id_quote=' . $idQuoteRequest . '&viewdotmquoterequest_quote&conf=4&token=' . $this->token);
        }
    }

    /**
     * Syncs a pending quote's lines with the edited table posted by the view page: quantities and
     * per-line discounts of the existing lines, new products, removed lines. Everything is
     * validated before anything is written.
     *
     * @return bool
     */
    private function saveLines(DotmQuoteRequestQuote $quote)
    {
        if ($quote->status !== 'pending') {
            $this->errors[] = $this->l('Solo i preventivi in attesa possono essere modificati.');

            return false;
        }

        $lineIds = array_map('intval', (array) Tools::getValue('line_id', []));
        $productIds = array_map('intval', (array) Tools::getValue('line_product', []));
        $attributeIds = array_map('intval', (array) Tools::getValue('line_attribute', []));
        $quantities = array_map('intval', (array) Tools::getValue('line_qty', []));
        $discounts = (array) Tools::getValue('line_discount', []);

        $existing = [];
        foreach ($quote->getLines() as $line) {
            $existing[(int) $line->id] = $line;
        }

        $idCustomer = (int) $quote->id_customer;
        $idLang = (int) $quote->id_lang;
        $updates = [];
        $additions = [];
        $keys = [];

        foreach ($lineIds as $i => $idDetail) {
            $qty = isset($quantities[$i]) ? $quantities[$i] : 0;
            $percent = isset($discounts[$i]) ? (float) str_replace(',', '.', $discounts[$i]) : 0;

            if ($qty <= 0) {
                $this->errors[] = $this->l('Ogni riga deve avere una quantità di almeno 1.');
                continue;
            }
            if ($percent < 0 || $percent > 100) {
                $this->errors[] = $this->l('Lo sconto di riga deve essere compreso tra 0 e 100%.');
                continue;
            }
            $percent = round($percent, 4);

            if ($idDetail) {
                if (!isset($existing[$idDetail])) {
                    $this->errors[] = $this->l('Una delle righe non appartiene a questo preventivo: ricarica la pagina.');
                    continue;
                }
                $line = $existing[$idDetail];
                $keys[(int) $line->id_product . '-' . (int) $line->id_product_attribute] = true;
                $updates[$idDetail] = ['qty' => $qty, 'percent' => $percent];
                continue;
            }

            $idProduct = isset($productIds[$i]) ? $productIds[$i] : 0;
            $idProductAttribute = isset($attributeIds[$i]) ? $attributeIds[$i] : 0;
            $product = new Product($idProduct, false, $idLang);
            if (!Validate::isLoadedObject($product) || !$product->active) {
                $this->errors[] = sprintf($this->l('Il prodotto #%d non esiste o non è attivo.'), $idProduct);
                continue;
            }
            if ($idProductAttribute) {
                $combination = new Combination($idProductAttribute);
                if (!Validate::isLoadedObject($combination) || (int) $combination->id_product !== $idProduct) {
                    $this->errors[] = sprintf($this->l('Variante non valida per il prodotto "%s".'), $product->name);
                    continue;
                }
            } elseif ($product->hasAttributes()) {
                $this->errors[] = sprintf($this->l('Scegli una variante per il prodotto "%s".'), $product->name);
                continue;
            }

            $key = $idProduct . '-' . $idProductAttribute;
            if (isset($keys[$key])) {
                $this->errors[] = sprintf($this->l('Il prodotto "%s" è già presente nel preventivo: aumentane la quantità.'), $product->name);
                continue;
            }
            $keys[$key] = true;
            $additions[] = ['product' => $product, 'id_product_attribute' => $idProductAttribute, 'qty' => $qty, 'percent' => $percent];
        }

        if (!$this->errors && !$updates && !$additions) {
            $this->errors[] = $this->l('Il preventivo deve contenere almeno un prodotto.');
        }
        if (!$this->errors && count($updates) + count($additions) > DotmQuoteRequestDetail::MAX_LINES) {
            $this->errors[] = sprintf($this->l('Un preventivo può contenere al massimo %d righe.'), DotmQuoteRequestDetail::MAX_LINES);
        }
        if ($this->errors) {
            return false;
        }

        foreach ($existing as $idDetail => $line) {
            if (!isset($updates[$idDetail])) {
                $line->delete();
                continue;
            }
            $line->quantity = $updates[$idDetail]['qty'];
            $line->discount_percent = $updates[$idDetail]['percent'];
            $line->save();
        }

        foreach ($additions as $addition) {
            $product = $addition['product'];
            $idProductAttribute = (int) $addition['id_product_attribute'];

            $detail = new DotmQuoteRequestDetail();
            $detail->id_quote = (int) $quote->id;
            $detail->id_product = (int) $product->id;
            $detail->id_product_attribute = $idProductAttribute;
            $detail->combination_name = DotmQuoteRequestDetail::buildCombinationName($product, $idProductAttribute, $idLang);
            $detail->quantity = (int) $addition['qty'];
            $detail->product_name = $product->name;
            $detail->product_reference = $product->reference;
            $detail->unit_price_tax_excl = Product::getPriceStatic((int) $product->id, false, $idProductAttribute ?: null, 6, null, false, true, 1, false, $idCustomer);
            $detail->unit_price_tax_incl = $this->customerPrice((int) $product->id, $idProductAttribute, $idCustomer);
            $detail->discount_percent = (float) $addition['percent'];

            if (!$detail->add()) {
                $this->errors[] = sprintf($this->l('Impossibile salvare la riga "%s".'), $product->name);

                return false;
            }
        }

        return true;
    }

    private function processReject()
    {
        $idQuoteRequest = (int) Tools::getValue('id_quote');
        $quote = new DotmQuoteRequestQuote($idQuoteRequest);

        if (!Validate::isLoadedObject($quote)) {
            $this->errors[] = $this->l('Preventivo non trovato.');

            return;
        }

        $adminNote = (string) Tools::getValue('admin_note');

        if (DotmQuoteRequestManager::cancel($quote, $adminNote)) {
            $this->confirmations[] = $this->l('Preventivo annullato.');
            Tools::redirectAdmin(self::$currentIndex . '&id_quote=' . $idQuoteRequest . '&viewdotmquoterequest_quote&token=' . $this->token);
        } else {
            $this->errors[] = $this->l('Impossibile annullare il preventivo: si possono annullare solo i preventivi "Da gestire" o "Mandato al cliente".');
        }
    }

    private function processCreate()
    {
        $this->display = 'add';

        if (!$this->access('add')) {
            $this->errors[] = $this->l('Non hai i permessi per creare preventivi.');

            return;
        }

        $customer = new Customer((int) Tools::getValue('id_customer'));
        if (!Validate::isLoadedObject($customer) || $customer->deleted || $customer->is_guest) {
            $this->errors[] = $this->l('Seleziona un cliente valido.');

            return;
        }

        $productIds = array_map('intval', (array) Tools::getValue('line_product', []));
        $attributeIds = array_map('intval', (array) Tools::getValue('line_attribute', []));
        $quantities = array_map('intval', (array) Tools::getValue('line_qty', []));

        $idLang = (int) $customer->id_lang ?: (int) $this->context->language->id;

        // Validate the lines and merge duplicates (same product + variant).
        $lines = [];
        foreach ($productIds as $i => $idProduct) {
            $idProductAttribute = isset($attributeIds[$i]) ? $attributeIds[$i] : 0;
            $qty = isset($quantities[$i]) ? $quantities[$i] : 0;

            if ($idProduct <= 0 || $qty <= 0) {
                continue;
            }

            $product = new Product($idProduct, false, $idLang);
            if (!Validate::isLoadedObject($product) || !$product->active) {
                $this->errors[] = sprintf($this->l('Il prodotto #%d non esiste o non è attivo.'), $idProduct);
                continue;
            }

            if ($idProductAttribute) {
                $combination = new Combination($idProductAttribute);
                if (!Validate::isLoadedObject($combination) || (int) $combination->id_product !== $idProduct) {
                    $this->errors[] = sprintf($this->l('Variante non valida per il prodotto "%s".'), $product->name);
                    continue;
                }
            } elseif ($product->hasAttributes()) {
                $this->errors[] = sprintf($this->l('Scegli una variante per il prodotto "%s".'), $product->name);
                continue;
            }

            $key = $idProduct . '-' . $idProductAttribute;
            if (isset($lines[$key])) {
                $lines[$key]['qty'] += $qty;
            } else {
                $lines[$key] = ['product' => $product, 'id_product_attribute' => $idProductAttribute, 'qty' => $qty];
            }
        }

        if (!empty($this->errors)) {
            return;
        }

        if (empty($lines)) {
            $this->errors[] = $this->l('Aggiungi almeno un prodotto.');

            return;
        }

        if (count($lines) > DotmQuoteRequestDetail::MAX_LINES) {
            $this->errors[] = sprintf($this->l('Un preventivo può contenere al massimo %d righe.'), DotmQuoteRequestDetail::MAX_LINES);

            return;
        }

        $quote = new DotmQuoteRequestQuote();
        $quote->id_customer = (int) $customer->id;
        $quote->id_shop = (int) $customer->id_shop ?: (int) $this->context->shop->id;
        $quote->id_lang = $idLang;
        $quote->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $quote->customer_message = (string) Tools::getValue('customer_message');
        $quote->status = 'pending';
        $quote->submitted_at = date('Y-m-d H:i:s');

        if (!$quote->add()) {
            $this->errors[] = $this->l('Impossibile creare il preventivo.');

            return;
        }

        $quote->assignNumber();

        foreach ($lines as $line) {
            $product = $line['product'];
            $idProductAttribute = (int) $line['id_product_attribute'];

            $detail = new DotmQuoteRequestDetail();
            $detail->id_quote = (int) $quote->id;
            $detail->id_product = (int) $product->id;
            $detail->id_product_attribute = $idProductAttribute;
            $detail->combination_name = DotmQuoteRequestDetail::buildCombinationName($product, $idProductAttribute, $idLang);
            $detail->quantity = (int) $line['qty'];
            $detail->product_name = $product->name;
            $detail->product_reference = $product->reference;
            $detail->unit_price_tax_excl = Product::getPriceStatic((int) $product->id, false, $idProductAttribute ?: null, 6, null, false, true, 1, false, (int) $customer->id);
            $detail->unit_price_tax_incl = $this->customerPrice((int) $product->id, $idProductAttribute, (int) $customer->id);

            if (!$detail->add()) {
                // delete() also removes the lines already saved.
                $quote->delete();
                $this->errors[] = sprintf($this->l('Impossibile salvare la riga "%s".'), $product->name);

                return;
            }
        }

        // Land on the detail page, where the quote can be approved with a discount.
        Tools::redirectAdmin(self::$currentIndex . '&id_quote=' . (int) $quote->id . '&viewdotmquoterequest_quote&conf=3&token=' . $this->token);
    }

    private function processExtend()
    {
        $idQuoteRequest = (int) Tools::getValue('id_quote');
        $quote = new DotmQuoteRequestQuote($idQuoteRequest);

        if (!Validate::isLoadedObject($quote)) {
            $this->errors[] = $this->l('Preventivo non trovato.');

            return;
        }

        $date = DateTime::createFromFormat('!Y-m-d', (string) Tools::getValue('expires_at'));
        if (!$date) {
            $this->errors[] = $this->l('Data di scadenza non valida.');

            return;
        }

        // The quote stays valid for the whole chosen day.
        $newExpiresAt = $date->format('Y-m-d') . ' 23:59:59';

        if (strtotime($newExpiresAt) <= time()) {
            $this->errors[] = $this->l('La nuova scadenza deve essere futura.');

            return;
        }

        if (DotmQuoteRequestManager::extendExpiry($quote, $newExpiresAt)) {
            $redirect = Tools::getValue('back') === 'list'
                ? self::$currentIndex . '&conf=4&token=' . $this->token
                : self::$currentIndex . '&id_quote=' . $idQuoteRequest . '&viewdotmquoterequest_quote&conf=4&token=' . $this->token;
            Tools::redirectAdmin($redirect);
        } else {
            $this->errors[] = $this->l('Impossibile prorogare il preventivo (stato non valido o regola sconto mancante).');
        }
    }
}

