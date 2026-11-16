<?php
/**
 * Richiesta preventivo — carrello dei preventivi per PrestaShop.
 *
 * I clienti registrati aggiungono prodotti a un "carrello preventivi", inviano la richiesta e,
 * dopo l'approvazione con sconto da parte del negozio, completano l'acquisto da un link sicuro.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/DotmQuoteRequestQuote.php';
require_once __DIR__ . '/classes/DotmQuoteRequestDetail.php';
require_once __DIR__ . '/classes/DotmQuoteRequestManager.php';
require_once __DIR__ . '/classes/DotmQuoteRequestPdf.php';

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Dotmquoterequest extends Module implements WidgetInterface
{
    const CFG_VALIDITY = 'DOTMQUOTEREQUEST_VALIDITY_DAYS';
    const CFG_MIN_TOTAL = 'DOTMQUOTEREQUEST_MIN_TOTAL';
    const CFG_NOTIFY = 'DOTMQUOTEREQUEST_NOTIFY_EMAIL';
    const CFG_PREFIX = 'DOTMQUOTEREQUEST_PREFIX';
    const CFG_PAYMENTS = 'DOTMQUOTEREQUEST_PAYMENT_MODULES';
    const CFG_AFTER_ADD = 'DOTMQUOTEREQUEST_AFTER_ADD';
    const CFG_SHOW_NAV = 'DOTMQUOTEREQUEST_SHOW_NAV';
    const CFG_ROUTE_CART = 'DOTMQUOTEREQUEST_ROUTE_CART';
    const CFG_ROUTE_LIST = 'DOTMQUOTEREQUEST_ROUTE_LIST';
    const CFG_COLOR = 'DOTMQUOTEREQUEST_COLOR';
    const CFG_ADMIN_DIR = 'DOTMQUOTEREQUEST_ADMIN_DIR';

    const DEFAULT_COLOR = '#2b2b2b';
    const DEFAULT_VALIDITY = 15;
    const DEFAULT_PREFIX = 'P-';
    const DEFAULT_ROUTE_CART = 'preventivo';
    const DEFAULT_ROUTE_LIST = 'i-miei-preventivi';

    /**
     * Nome della voce di menu (= nome del controller admin senza il suffisso "Controller").
     */
    const ADMIN_TAB = 'AdminDotmQuoteRequest';

    const HOOKS = [
        'moduleRoutes',
        'displayHeader',
        'displayNav2',
        'displayProductActions',
        'displayCustomerAccount',
        'displayPaymentTop',
        'actionFrontControllerInitAfter',
        'actionPresentCart',
        'actionPresentPaymentOptions',
        'actionValidateOrder',
        'actionOrderStatusPostUpdate',
    ];

    /** @var array<string, array> */
    protected $strings = [];

    /** @var array<int, DotmQuoteRequestQuote|false> preventivi approvati per id carrello, per richiesta */
    private $quoteByCart = [];

    public function __construct()
    {
        $this->name = 'dotmquoterequest';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'dotm.it';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.7.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = 'Richiesta preventivo';
        $this->description = 'Carrello dei preventivi: i clienti registrati aggiungono prodotti, inviano la richiesta e completano l\'acquisto con lo sconto concordato dal negozio.';
        $this->confirmUninstall = 'Disinstallare il modulo? I preventivi salvati restano nel database.';
    }

    /* ------------------------------------------------------------------
     * Installazione
     * ------------------------------------------------------------------ */

    public function install()
    {
        return parent::install()
            && $this->installDb()
            && $this->installTab()
            && Configuration::updateValue(self::CFG_VALIDITY, self::DEFAULT_VALIDITY)
            && Configuration::updateValue(self::CFG_MIN_TOTAL, 0)
            && Configuration::updateValue(self::CFG_NOTIFY, (string) Configuration::get('PS_SHOP_EMAIL'))
            && Configuration::updateValue(self::CFG_PREFIX, self::DEFAULT_PREFIX)
            && Configuration::updateValue(self::CFG_PAYMENTS, '')
            && Configuration::updateValue(self::CFG_AFTER_ADD, 'redirect')
            && Configuration::updateValue(self::CFG_SHOW_NAV, 1)
            && Configuration::updateValue(self::CFG_ROUTE_CART, self::DEFAULT_ROUTE_CART)
            && Configuration::updateValue(self::CFG_ROUTE_LIST, self::DEFAULT_ROUTE_LIST)
            && Configuration::updateValue(self::CFG_COLOR, '')
            && Configuration::updateValue(self::CFG_ADMIN_DIR, defined('_PS_ADMIN_DIR_') ? basename(_PS_ADMIN_DIR_) : '')
            && $this->registerHook(self::HOOKS);
    }

    /**
     * Le tabelle restano: i preventivi (e i collegamenti a carrelli, sconti e ordini) non vanno persi.
     */
    public function uninstall()
    {
        foreach ([
            self::CFG_VALIDITY, self::CFG_MIN_TOTAL, self::CFG_NOTIFY, self::CFG_PREFIX, self::CFG_PAYMENTS,
            self::CFG_AFTER_ADD, self::CFG_SHOW_NAV, self::CFG_ROUTE_CART, self::CFG_ROUTE_LIST, self::CFG_COLOR,
            self::CFG_ADMIN_DIR,
        ] as $key) {
            Configuration::deleteByName($key);
        }

        return $this->uninstallTab() && parent::uninstall();
    }

    protected function installDb()
    {
        $sql = [];

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dotmquoterequest_quote` (
            `id_quote` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `number` INT UNSIGNED NULL,
            `id_customer` INT UNSIGNED NOT NULL,
            `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
            `id_lang` INT UNSIGNED NOT NULL,
            `id_currency` INT UNSIGNED NOT NULL,
            `status` VARCHAR(32) NOT NULL DEFAULT \'draft\',
            `customer_message` TEXT NULL,
            `admin_note` TEXT NULL,
            `discount_type` VARCHAR(16) NULL,
            `discount_value` DECIMAL(20,6) NULL,
            `token` VARCHAR(64) NOT NULL DEFAULT \'\',
            `id_cart_rule` INT UNSIGNED NOT NULL DEFAULT 0,
            `id_cart` INT UNSIGNED NOT NULL DEFAULT 0,
            `id_order` INT UNSIGNED NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            `submitted_at` DATETIME NULL,
            `approved_at` DATETIME NULL,
            `cancelled_at` DATETIME NULL,
            `expires_at` DATETIME NULL,
            `converted_at` DATETIME NULL,
            PRIMARY KEY (`id_quote`),
            UNIQUE KEY `number` (`number`),
            KEY `id_customer` (`id_customer`),
            KEY `status` (`status`),
            KEY `id_cart` (`id_cart`),
            KEY `id_order` (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dotmquoterequest_detail` (
            `id_quote_detail` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_quote` INT UNSIGNED NOT NULL,
            `id_product` INT UNSIGNED NOT NULL,
            `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
            `combination_name` VARCHAR(255) NULL,
            `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
            `product_name` VARCHAR(255) NOT NULL,
            `product_reference` VARCHAR(64) NULL,
            `unit_price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
            `unit_price_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
            `discount_percent` DECIMAL(9,4) NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_quote_detail`),
            KEY `id_quote` (`id_quote`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    protected function installTab()
    {
        if (Tab::getIdFromClassName(self::ADMIN_TAB)) {
            return true;
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = self::ADMIN_TAB;
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentCustomer');
        $tab->icon = 'request_quote';
        $tab->name = [];
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = $lang['iso_code'] === 'it' ? 'Preventivi' : 'Quotes';
        }

        return (bool) $tab->add();
    }

    protected function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName(self::ADMIN_TAB);
        if (!$idTab) {
            return true;
        }
        $tab = new Tab($idTab);

        return (bool) $tab->delete();
    }

    /* ------------------------------------------------------------------
     * Testi (lang/<iso>.php; per le lingue senza file: inglese, poi italiano)
     * ------------------------------------------------------------------ */

    public function getStrings($idLang = null)
    {
        $iso = $idLang ? Language::getIsoById((int) $idLang) : $this->context->language->iso_code;
        $iso = preg_replace('/[^a-z]/', '', strtolower((string) $iso));
        if (!isset($this->strings[$iso])) {
            $strings = include __DIR__ . '/lang/it.php';
            if ($iso !== 'it') {
                $file = __DIR__ . '/lang/' . $iso . '.php';
                foreach (array_unique([__DIR__ . '/lang/en.php', is_file($file) ? $file : __DIR__ . '/lang/en.php']) as $path) {
                    $local = include $path;
                    $strings = array_merge($strings, is_array($local) ? $local : []);
                }
            }
            $this->strings[$iso] = $strings;
        }

        return $this->strings[$iso];
    }

    public function t($key, array $params = [], $idLang = null)
    {
        $strings = $this->getStrings($idLang);
        $text = isset($strings[$key]) ? $strings[$key] : $key;
        if ($params) {
            $replace = [];
            foreach ($params as $k => $v) {
                $replace['{' . $k . '}'] = $v;
            }
            $text = strtr($text, $replace);
        }

        return $text;
    }

    /**
     * Istanza del modulo per le classi che non la ricevono (manager, PDF, modello).
     *
     * @return Dotmquoterequest
     */
    public static function get()
    {
        return Module::getInstanceByName('dotmquoterequest');
    }

    /* ------------------------------------------------------------------
     * Impostazioni
     * ------------------------------------------------------------------ */

    public static function getValidityDays()
    {
        $days = (int) Configuration::get(self::CFG_VALIDITY);

        return $days > 0 ? $days : self::DEFAULT_VALIDITY;
    }

    /**
     * Totale minimo (IVA inclusa, prezzi di listino) per inviare la richiesta, nella valuta indicata. 0 = nessun minimo.
     */
    public static function getMinTotal($idCurrency = null)
    {
        $min = max(0, (float) Configuration::get(self::CFG_MIN_TOTAL));
        if ($min > 0 && $idCurrency && (int) $idCurrency !== (int) Configuration::get('PS_CURRENCY_DEFAULT')) {
            $min = Tools::convertPrice($min, new Currency((int) $idCurrency));
        }

        return $min;
    }

    public static function getNumberPrefix()
    {
        $prefix = Configuration::get(self::CFG_PREFIX);

        return $prefix === false ? self::DEFAULT_PREFIX : (string) $prefix;
    }

    /**
     * Moduli di pagamento ammessi per il carrello di un preventivo approvato (vuoto = tutti).
     *
     * @return string[]
     */
    public static function getAllowedPaymentModules()
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) Configuration::get(self::CFG_PAYMENTS)))));
    }

    public static function isPaymentModuleAllowed($name)
    {
        $allowed = self::getAllowedPaymentModules();

        return !$allowed || in_array($name, $allowed, true);
    }

    public static function getNotifyEmails()
    {
        $emails = array_filter(array_map('trim', explode(',', (string) Configuration::get(self::CFG_NOTIFY))), function ($email) {
            return Validate::isEmail($email);
        });

        return $emails ?: array_filter([(string) Configuration::get('PS_SHOP_EMAIL')]);
    }

    public function getRouteSlug($key)
    {
        $default = $key === self::CFG_ROUTE_LIST ? self::DEFAULT_ROUTE_LIST : self::DEFAULT_ROUTE_CART;
        $slug = trim((string) Configuration::get($key), '/');

        return $slug !== '' && Validate::isLinkRewrite($slug) ? $slug : $default;
    }

    /**
     * Colore dei pulsanti (configurazione), validato come #rrggbb.
     */
    public function getColor()
    {
        $color = strtolower(trim((string) Configuration::get(self::CFG_COLOR)));

        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : '';
    }

    /**
     * Variabili CSS per il colore configurato, da mettere nell'attributo style dei contenitori.
     */
    public function getCssVars()
    {
        $color = $this->getColor();
        if ($color === '') {
            return '';
        }
        $dark = '#';
        foreach (str_split(substr($color, 1), 2) as $hex) {
            $dark .= sprintf('%02x', (int) round(hexdec($hex) * 0.75));
        }

        return '--dqr-accent:' . $color . ';--dqr-accent-dark:' . $dark . ';';
    }

    /* ------------------------------------------------------------------
     * Utilità
     * ------------------------------------------------------------------ */

    /**
     * Prezzo formattato nella valuta indicata (Locale su PrestaShop 1.7.6+, displayPrice come ripiego).
     */
    public static function formatPrice($amount, $idCurrency = null)
    {
        $context = Context::getContext();
        $currency = $idCurrency ? new Currency((int) $idCurrency) : $context->currency;
        if (!Validate::isLoadedObject($currency)) {
            $currency = Currency::getDefaultCurrency();
        }

        try {
            if (method_exists($context, 'getCurrentLocale') && $context->getCurrentLocale()) {
                return $context->getCurrentLocale()->formatPrice((float) $amount, $currency->iso_code);
            }
        } catch (\Exception $e) {
            // ripiego sotto
        }

        return Tools::displayPrice((float) $amount, $currency);
    }

    public static function isDateSet($date)
    {
        return $date && strpos((string) $date, '0000-00-00') !== 0;
    }

    public function getCartUrl(array $params = [])
    {
        return $this->context->link->getModuleLink($this->name, 'quote', $params, true);
    }

    public function getListUrl(array $params = [])
    {
        return $this->context->link->getModuleLink($this->name, 'quotelist', $params, true);
    }

    public function getActivateUrl(DotmQuoteRequestQuote $quote, array $params = [])
    {
        return $this->context->link->getModuleLink($this->name, 'activate', array_merge([
            'id_quote' => (int) $quote->id,
            'token' => $quote->token,
        ], $params), true);
    }

    /**
     * Numero di righe nel carrello preventivi del cliente collegato.
     */
    public function getDraftCount()
    {
        $customer = $this->context->customer;
        if (!$customer || !$customer->isLogged()) {
            return 0;
        }
        $draft = DotmQuoteRequestQuote::getCurrentDraft((int) $customer->id);

        return $draft ? $draft->countLines() : 0;
    }

    public function getStatusLabel($status, $idLang = null)
    {
        if ($status === 'rejected') {
            $status = 'cancelled';
        }

        return $this->t('status_' . $status, [], $idLang);
    }

    /* ------------------------------------------------------------------
     * Hook: pagine, risorse, link
     * ------------------------------------------------------------------ */

    public function hookModuleRoutes()
    {
        return [
            'module-dotmquoterequest-quote' => [
                'controller' => 'quote',
                'rule' => $this->getRouteSlug(self::CFG_ROUTE_CART),
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => $this->name],
            ],
            'module-dotmquoterequest-quotelist' => [
                'controller' => 'quotelist',
                'rule' => $this->getRouteSlug(self::CFG_ROUTE_LIST),
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => $this->name],
            ],
        ];
    }

    public function hookDisplayHeader($params)
    {
        $controller = $this->context->controller;
        $controller->registerStylesheet('dotmquoterequest-front', 'modules/' . $this->name . '/views/css/front.css', ['media' => 'all', 'priority' => 200]);
        $controller->registerJavascript('dotmquoterequest-front', 'modules/' . $this->name . '/views/js/front.js', ['position' => 'bottom', 'priority' => 200]);

        Media::addJsDef(['dqrFront' => [
            'afterAdd' => Configuration::get(self::CFG_AFTER_ADD) === 'stay' ? 'stay' : 'redirect',
            'networkError' => $this->t('js_network_error'),
            'viewQuote' => $this->t('js_view_quote'),
        ]]);

        // Banner sul carrello e sul checkout del carrello di un preventivo approvato
        $quote = $this->getQuoteForCart($this->context->cart);
        if ($quote && ($controller instanceof CartController || $controller instanceof OrderController)) {
            Media::addJsDef(['dqrLock' => [
                'message' => $this->t('lock_banner', [
                    'number' => $quote->getDisplayNumber(),
                    'payment' => $this->getAllowedPaymentModules() ? ' ' . $this->t('lock_banner_payment') : '',
                ]),
                'cancelUrl' => $this->getActivateUrl($quote, ['action' => 'cancel']),
                'cancelLabel' => $this->t('lock_cancel'),
                'cancelConfirm' => $this->t('lock_cancel_confirm', ['number' => $quote->getDisplayNumber()]),
            ]]);
        }

        return '';
    }

    /**
     * Link al carrello preventivi con il numero di prodotti (header del tema classic).
     */
    public function hookDisplayNav2($params)
    {
        if (!Configuration::get(self::CFG_SHOW_NAV)) {
            return '';
        }

        return $this->renderWidget('displayNav2', $params);
    }

    /**
     * {widget name='dotmquoterequest'} in un template del tema: stesso link dell'header.
     */
    public function renderWidget($hookName, array $configuration)
    {
        $vars = $this->getWidgetVariables($hookName, $configuration);
        if (!$vars['dqr_nav']['logged']) {
            return '';
        }
        $this->context->smarty->assign($vars);

        return $this->fetch('module:dotmquoterequest/views/templates/hook/nav.tpl');
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        $customer = $this->context->customer;

        return ['dqr_nav' => [
            'logged' => (bool) ($customer && $customer->isLogged()),
            'url' => $this->getCartUrl(),
            'count' => $this->getDraftCount(),
            'label' => $this->t('nav_label'),
            'css_vars' => $this->getCssVars(),
        ]];
    }

    public function hookDisplayProductActions($params)
    {
        $product = isset($params['product']) ? $params['product'] : null;
        if (!$product) {
            return '';
        }

        // ProductLazyArray (temi 1.7.7+) o array; Product come ripiego
        if (is_array($product) || $product instanceof ArrayAccess) {
            $idProduct = (int) $product['id_product'];
            $idProductAttribute = isset($product['id_product_attribute']) ? (int) $product['id_product_attribute'] : 0;
        } else {
            $idProduct = (int) $product->id;
            $idProductAttribute = isset($product->id_product_attribute) ? (int) $product->id_product_attribute : 0;
        }
        if (!$idProduct) {
            return '';
        }

        $customer = $this->context->customer;
        $productUrl = $this->context->link->getProductLink($idProduct);

        $this->context->smarty->assign(['dqr_product' => [
            'logged' => (bool) ($customer && $customer->isLogged()),
            'id_product' => $idProduct,
            'id_product_attribute' => $idProductAttribute,
            'token' => Tools::getToken(false),
            'ajax_url' => $this->context->link->getModuleLink($this->name, 'addtoquote', [], true),
            'cart_url' => $this->getCartUrl(),
            'login_url' => $this->context->link->getPageLink('authentication', true, null, ['back' => $productUrl]),
            'count' => $this->getDraftCount(),
            'css_vars' => $this->getCssVars(),
            't' => [
                'add' => $this->t('btn_add'),
                'login' => $this->t('btn_login'),
                'in_quote' => $this->t('product_in_quote'),
            ],
        ]]);

        return $this->fetch('module:dotmquoterequest/views/templates/hook/product-actions.tpl');
    }

    public function hookDisplayCustomerAccount($params)
    {
        $this->context->smarty->assign(['dqr_account' => [
            'cart_url' => $this->getCartUrl(),
            'list_url' => $this->getListUrl(),
            't' => [
                'cart' => $this->t('account_cart'),
                'list' => $this->t('account_list'),
            ],
        ]]);

        return $this->fetch('module:dotmquoterequest/views/templates/hook/account-links.tpl');
    }

    /* ------------------------------------------------------------------
     * Checkout di un preventivo approvato
     * ------------------------------------------------------------------ */

    /**
     * Il preventivo approvato e ancora valido di cui questo è il carrello, oppure null.
     *
     * @return DotmQuoteRequestQuote|null
     */
    public function getQuoteForCart($cart)
    {
        if (!$cart instanceof Cart || !(int) $cart->id) {
            return null;
        }

        $idCart = (int) $cart->id;
        if (!isset($this->quoteByCart[$idCart])) {
            $quote = DotmQuoteRequestQuote::getByCartId($idCart);
            $this->quoteByCart[$idCart] = ($quote && $quote->status === 'approved' && !$quote->isExpired()) ? $quote : false;
        }

        return $this->quoteByCart[$idCart] ?: null;
    }

    /**
     * Esce dal carrello del preventivo: il cliente ritrova il carrello che aveva prima di aprire il
     * link (o uno nuovo vuoto). Il preventivo resta approvato e il link lo riapre.
     */
    public function leaveQuoteCart()
    {
        $cookie = $this->context->cookie;
        $previous = isset($cookie->dqr_prev_cart) ? new Cart((int) $cookie->dqr_prev_cart) : null;
        unset($cookie->dqr_prev_cart);

        if ($previous && Validate::isLoadedObject($previous)
            && (int) $previous->id_customer === (int) $this->context->customer->id
            && !$previous->orderExists()
            && !$this->getQuoteForCart($previous)
        ) {
            $cookie->id_cart = (int) $previous->id;
            $this->context->cart = $previous;
        } else {
            $cookie->id_cart = 0;
            $cart = new Cart();
            $cart->id_lang = (int) $this->context->language->id;
            $cart->id_currency = (int) $this->context->currency->id;
            $cart->id_customer = (int) $this->context->customer->id;
            $this->context->cart = $cart;
        }

        $cookie->write();
    }

    /**
     * Blocca il carrello di un preventivo approvato: niente modifiche ai prodotti, niente altri buoni
     * sconto, solo i pagamenti ammessi. Tornare a navigare il negozio fa uscire dal carrello del
     * preventivo (vedi leaveQuoteCart()).
     */
    public function hookActionFrontControllerInitAfter($params)
    {
        $controller = isset($params['controller']) ? $params['controller'] : $this->context->controller;
        $cart = $this->context->cart;
        $quote = $this->getQuoteForCart($cart);
        if (!$controller || !$quote) {
            return;
        }

        $isAjax = Tools::getValue('ajax')
            || strtolower(isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : '') === 'xmlhttprequest';

        if ($controller instanceof CartController) {
            if (Tools::getIsset('addDiscount') || Tools::getIsset('deleteDiscount')) {
                // Letto da CartController::updateCart(), che allora lascia stare il carrello
                $controller->errors[] = $this->t('lock_no_voucher');
            } elseif (Tools::getIsset('add') || Tools::getIsset('update') || Tools::getIsset('delete')) {
                $controller->errors[] = $this->t('lock_locked', ['number' => $quote->getDisplayNumber()]);
            }
            $this->keepOnlyQuoteCartRule($cart, $quote);

            return;
        }

        if ($controller instanceof OrderController) {
            $this->keepOnlyQuoteCartRule($cart, $quote);

            return;
        }

        if ($controller instanceof ModuleFrontController && isset($controller->module) && $controller->module) {
            $module = $controller->module;

            if ($module instanceof PaymentModule && !self::isPaymentModuleAllowed($module->name)) {
                Tools::redirect($this->context->link->getPageLink('order', true));
            }

            if ($module instanceof PaymentModule || $module->name === $this->name) {
                return;
            }
        }

        // Tornato a navigare il negozio (solo pagine, non le richieste ajax/modali del checkout)
        $browsing = $controller instanceof ProductListingFrontController
            || $controller instanceof ProductController
            || $controller instanceof IndexController
            || ($controller instanceof CmsController && !Tools::getValue('content_only'));
        if ($browsing && !$isAjax) {
            $this->leaveQuoteCart();
        }
    }

    /**
     * Toglie ogni codice sconto diverso da quello del preventivo e rimette quest'ultimo se manca.
     * Le regole automatiche (senza codice) restano: il core le riaggiunge a ogni Cart::getCartRules().
     */
    private function keepOnlyQuoteCartRule(Cart $cart, DotmQuoteRequestQuote $quote)
    {
        $hasQuoteRule = false;
        foreach ($cart->getCartRules(CartRule::FILTER_ACTION_ALL, false) as $rule) {
            if ((int) $rule['id_cart_rule'] === (int) $quote->id_cart_rule) {
                $hasQuoteRule = true;
            } elseif (!empty($rule['code'])) {
                $cart->removeCartRule((int) $rule['id_cart_rule']);
            }
        }

        if (!$hasQuoteRule && (int) $quote->id_cart_rule) {
            $cart->addCartRule((int) $quote->id_cart_rule);
        }
    }

    /**
     * Nasconde il campo buoni sconto nel carrello di un preventivo approvato (lo sconto del preventivo resta nei totali).
     */
    public function hookActionPresentCart($params)
    {
        if (empty($params['presentedCart']) || !$this->getQuoteForCart($this->context->cart)) {
            return;
        }

        $presented = $params['presentedCart'];
        if (is_object($presented) && method_exists($presented, 'offsetSet')) {
            $presented->offsetSet('vouchers', ['allowed' => 0, 'added' => []], true);
        }
    }

    /**
     * Solo i pagamenti ammessi per il carrello di un preventivo approvato.
     */
    public function hookActionPresentPaymentOptions($params)
    {
        if (!isset($params['paymentOptions']) || !$this->getAllowedPaymentModules() || !$this->getQuoteForCart($this->context->cart)) {
            return;
        }

        $params['paymentOptions'] = array_intersect_key(
            $params['paymentOptions'],
            array_fill_keys($this->getAllowedPaymentModules(), true)
        );
    }

    public function hookDisplayPaymentTop($params)
    {
        if (!$this->getAllowedPaymentModules() || !$this->getQuoteForCart($this->context->cart)) {
            return '';
        }

        return '<div class="alert alert-info dqr-payment-info" role="status">'
            . htmlspecialchars($this->t('payment_info'), ENT_QUOTES, 'UTF-8')
            . '</div>';
    }

    public function hookActionValidateOrder($params)
    {
        if (empty($params['cart']) || !($params['cart'] instanceof Cart)) {
            return;
        }

        $quote = DotmQuoteRequestQuote::getByCartId((int) $params['cart']->id);
        if (!$quote || $quote->status !== 'approved') {
            return;
        }

        if (isset($this->context->cookie->dqr_prev_cart)) {
            unset($this->context->cookie->dqr_prev_cart);
        }

        // Pagamenti differiti (es. bonifico): l'ordine attende il pagamento (vedi hookActionOrderStatusPostUpdate())
        $orderState = isset($params['orderStatus']) ? $params['orderStatus'] : null;
        $paid = $orderState instanceof OrderState && $orderState->paid;
        $quote->status = $paid ? 'converted' : 'awaiting_payment';
        $quote->id_order = isset($params['order']) ? (int) $params['order']->id : 0;
        if ($paid) {
            $quote->converted_at = date('Y-m-d H:i:s');
        }
        $quote->save();

        DotmQuoteRequestManager::disableCartRule($quote);
    }

    /**
     * L'ordine del preventivo è passato a uno stato pagato.
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $orderState = isset($params['newOrderStatus']) ? $params['newOrderStatus'] : null;
        $idOrder = isset($params['id_order']) ? (int) $params['id_order'] : 0;
        if (!$orderState instanceof OrderState || !$orderState->paid || !$idOrder) {
            return;
        }

        $quote = DotmQuoteRequestQuote::getByOrderId($idOrder);
        if ($quote && $quote->status === 'awaiting_payment') {
            $quote->status = 'converted';
            $quote->converted_at = date('Y-m-d H:i:s');
            $quote->save();
        }
    }

    /* ------------------------------------------------------------------
     * Configurazione
     * ------------------------------------------------------------------ */

    public function getContent()
    {
        $output = '';

        // Cartella admin per il link nelle email di notifica (che partono dal front office)
        if (defined('_PS_ADMIN_DIR_') && Configuration::get(self::CFG_ADMIN_DIR) !== basename(_PS_ADMIN_DIR_)) {
            Configuration::updateValue(self::CFG_ADMIN_DIR, basename(_PS_ADMIN_DIR_));
        }

        if (Tools::isSubmit('submitDotmquoterequest')) {
            $minTotal = str_replace(',', '.', trim((string) Tools::getValue(self::CFG_MIN_TOTAL)));
            $validity = (int) Tools::getValue(self::CFG_VALIDITY);
            $emails = array_filter(array_map('trim', explode(',', (string) Tools::getValue(self::CFG_NOTIFY))));
            $invalid = array_filter($emails, function ($e) {
                return !Validate::isEmail($e);
            });
            $prefix = trim((string) Tools::getValue(self::CFG_PREFIX));
            $color = strtolower(trim((string) Tools::getValue(self::CFG_COLOR)));
            $routeCart = trim((string) Tools::getValue(self::CFG_ROUTE_CART), '/ ');
            $routeList = trim((string) Tools::getValue(self::CFG_ROUTE_LIST), '/ ');
            $payments = array_filter(array_map('strval', (array) Tools::getValue(self::CFG_PAYMENTS, [])), function ($name) {
                return Validate::isModuleName($name);
            });
            $afterAdd = Tools::getValue(self::CFG_AFTER_ADD) === 'stay' ? 'stay' : 'redirect';

            if (!is_numeric($minTotal) || (float) $minTotal < 0) {
                $output .= $this->displayError('L\'importo minimo deve essere un numero maggiore o uguale a 0.');
            } elseif ($validity < 1) {
                $output .= $this->displayError('La validità deve essere di almeno 1 giorno.');
            } elseif ($invalid) {
                $output .= $this->displayError('Indirizzi email non validi: ' . htmlspecialchars(implode(', ', $invalid)));
            } elseif (Tools::strlen($prefix) > 10 || !preg_match('/^[A-Za-z0-9\/#._-]*$/', $prefix)) {
                $output .= $this->displayError('Prefisso non valido: massimo 10 caratteri tra lettere, numeri e - _ / # .');
            } elseif ($color !== '' && !preg_match('/^#[0-9a-f]{6}$/', $color)) {
                $output .= $this->displayError('Colore non valido: usa il formato #rrggbb oppure lascia vuoto.');
            } elseif (!Validate::isLinkRewrite($routeCart) || !Validate::isLinkRewrite($routeList) || $routeCart === $routeList) {
                $output .= $this->displayError('Indirizzi delle pagine non validi: usa lettere minuscole, numeri e trattini, diversi tra loro.');
            } else {
                Configuration::updateValue(self::CFG_VALIDITY, $validity);
                Configuration::updateValue(self::CFG_MIN_TOTAL, round((float) $minTotal, 2));
                Configuration::updateValue(self::CFG_NOTIFY, implode(',', $emails));
                Configuration::updateValue(self::CFG_PREFIX, $prefix);
                Configuration::updateValue(self::CFG_PAYMENTS, implode(',', $payments));
                Configuration::updateValue(self::CFG_AFTER_ADD, $afterAdd);
                Configuration::updateValue(self::CFG_SHOW_NAV, (int) (bool) Tools::getValue(self::CFG_SHOW_NAV));
                Configuration::updateValue(self::CFG_ROUTE_CART, $routeCart);
                Configuration::updateValue(self::CFG_ROUTE_LIST, $routeList);
                Configuration::updateValue(self::CFG_COLOR, $color);
                $output .= $this->displayConfirmation('Impostazioni salvate. Se hai cambiato gli indirizzi delle pagine, svuota la cache.');
            }
        }

        $cartUrl = $this->getCartUrl();
        $listUrl = $this->getListUrl();
        $output .= '<div class="alert alert-info">'
            . '<p><strong>Carrello preventivi</strong> (pagina pubblica, richiede l\'accesso del cliente): <a href="' . htmlspecialchars($cartUrl) . '" target="_blank">' . htmlspecialchars($cartUrl) . '</a></p>'
            . '<p><strong>I miei preventivi</strong> (area clienti): <a href="' . htmlspecialchars($listUrl) . '" target="_blank">' . htmlspecialchars($listUrl) . '</a></p>'
            . '<p>Le richieste ricevute sono in <a href="' . htmlspecialchars($this->context->link->getAdminLink(self::ADMIN_TAB)) . '">Clienti → Preventivi</a>.</p>'
            . '</div>';

        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $currency = Currency::getDefaultCurrency();

        $payments = [];
        foreach (PaymentModule::getInstalledPaymentModules() ?: [] as $row) {
            $module = Module::getInstanceByName($row['name']);
            $payments[] = ['id' => $row['name'], 'name' => ($module ? $module->displayName : $row['name']) . ' (' . $row['name'] . ')'];
        }

        $form = [
            'form' => [
                'legend' => ['title' => 'Impostazioni richiesta preventivo', 'icon' => 'icon-cogs'],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => 'Validità del preventivo approvato (giorni)',
                        'name' => self::CFG_VALIDITY,
                        'class' => 'fixed-width-sm',
                        'desc' => 'Per quanti giorni il link mandato al cliente e lo sconto restano validi. Dal back office si può prorogare.',
                    ],
                    [
                        'type' => 'text',
                        'label' => 'Importo minimo per richiedere un preventivo',
                        'name' => self::CFG_MIN_TOTAL,
                        'suffix' => $currency->sign,
                        'class' => 'fixed-width-md',
                        'desc' => 'Totale IVA inclusa a prezzi di listino, nella valuta predefinita. Sotto questo importo il cliente non può inviare la richiesta. 0 = nessun minimo.',
                    ],
                    [
                        'type' => 'text',
                        'label' => 'Email per le notifiche',
                        'name' => self::CFG_NOTIFY,
                        'desc' => 'Uno o più indirizzi separati da virgola. Se vuoto, si usa l\'email del negozio.',
                    ],
                    [
                        'type' => 'text',
                        'label' => 'Prefisso del numero',
                        'name' => self::CFG_PREFIX,
                        'class' => 'fixed-width-sm',
                        'desc' => 'I preventivi inviati sono numerati in progressione: con "P-" diventano P-10001, P-10002, …',
                    ],
                    [
                        'type' => 'radio',
                        'label' => 'Dopo «Aggiungi al preventivo»',
                        'name' => self::CFG_AFTER_ADD,
                        'values' => [
                            ['id' => 'dqr_after_redirect', 'value' => 'redirect', 'label' => 'Apri il carrello preventivi'],
                            ['id' => 'dqr_after_stay', 'value' => 'stay', 'label' => 'Resta sulla pagina prodotto (messaggio con link al carrello preventivi)'],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => 'Link nell\'header',
                        'name' => self::CFG_SHOW_NAV,
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'dqr_nav_on', 'value' => 1, 'label' => 'Sì'],
                            ['id' => 'dqr_nav_off', 'value' => 0, 'label' => 'No'],
                        ],
                        'desc' => 'Link «Preventivo» con il numero di prodotti nell\'hook displayNav2, per i clienti collegati. In altri punti del tema: {widget name=\'dotmquoterequest\'}.',
                    ],
                    [
                        'type' => 'select',
                        'label' => 'Pagamenti ammessi per i preventivi',
                        'name' => self::CFG_PAYMENTS . '[]',
                        'multiple' => true,
                        'size' => 6,
                        'options' => ['query' => $payments, 'id' => 'id', 'name' => 'name'],
                        'desc' => 'Metodi di pagamento offerti al cliente che completa l\'acquisto di un preventivo approvato. Nessuna selezione = tutti. Ctrl/Cmd per selezioni multiple.',
                    ],
                    [
                        'type' => 'text',
                        'label' => 'Indirizzo del carrello preventivi',
                        'name' => self::CFG_ROUTE_CART,
                        'prefix' => '/',
                        'class' => 'fixed-width-xl',
                        'desc' => 'Con gli URL semplificati attivi. Predefinito: ' . self::DEFAULT_ROUTE_CART . '.',
                    ],
                    [
                        'type' => 'text',
                        'label' => 'Indirizzo di «I miei preventivi»',
                        'name' => self::CFG_ROUTE_LIST,
                        'prefix' => '/',
                        'class' => 'fixed-width-xl',
                        'desc' => 'Predefinito: ' . self::DEFAULT_ROUTE_LIST . '.',
                    ],
                    [
                        'type' => 'color',
                        'label' => 'Colore pulsanti',
                        'name' => self::CFG_COLOR,
                        'desc' => 'Formato #rrggbb, ad esempio il colore principale del tema. Se vuoto: ' . self::DEFAULT_COLOR . '. Usato anche nelle email.',
                    ],
                ],
                'submit' => ['title' => 'Salva'],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->submit_action = 'submitDotmquoterequest';

        $submitted = Tools::isSubmit('submitDotmquoterequest');
        $value = function ($key, $current) use ($submitted) {
            return $submitted ? Tools::getValue($key, $current) : $current;
        };
        $helper->fields_value = [
            self::CFG_VALIDITY => $value(self::CFG_VALIDITY, self::getValidityDays()),
            self::CFG_MIN_TOTAL => $value(self::CFG_MIN_TOTAL, (float) Configuration::get(self::CFG_MIN_TOTAL)),
            self::CFG_NOTIFY => $value(self::CFG_NOTIFY, (string) Configuration::get(self::CFG_NOTIFY)),
            self::CFG_PREFIX => $value(self::CFG_PREFIX, self::getNumberPrefix()),
            self::CFG_AFTER_ADD => Configuration::get(self::CFG_AFTER_ADD) === 'stay' ? 'stay' : 'redirect',
            self::CFG_SHOW_NAV => (int) Configuration::get(self::CFG_SHOW_NAV),
            self::CFG_PAYMENTS . '[]' => self::getAllowedPaymentModules(),
            self::CFG_ROUTE_CART => $value(self::CFG_ROUTE_CART, $this->getRouteSlug(self::CFG_ROUTE_CART)),
            self::CFG_ROUTE_LIST => $value(self::CFG_ROUTE_LIST, $this->getRouteSlug(self::CFG_ROUTE_LIST)),
            self::CFG_COLOR => $this->getColor(),
        ];

        return $helper->generateForm([$form]);
    }
}
