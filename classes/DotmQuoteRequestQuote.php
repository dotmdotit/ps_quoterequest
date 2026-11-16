<?php
/**
 * Preventivo (tabella ps_dotmquoterequest_quote).
 *
 * Stati: draft (carrello preventivi del cliente), pending (inviato, da gestire), approved (mandato al
 * cliente con lo sconto), awaiting_payment (ordinato, in attesa di pagamento), converted (pagato),
 * expired (scaduto), cancelled (annullato dal negozio o dal cliente).
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmQuoteRequestQuote extends ObjectModel
{
    const STATUSES = ['draft', 'pending', 'approved', 'awaiting_payment', 'converted', 'expired', 'cancelled'];

    /** Primo numero progressivo: P-10001, P-10002, … */
    const NUMBER_START = 10001;

    public $id_customer;
    public $id_shop;
    public $id_lang;
    public $id_currency;
    public $status = 'draft';
    public $customer_message;
    public $admin_note;
    public $discount_type;
    public $discount_value;
    public $token = '';
    public $id_cart_rule = 0;
    public $id_cart = 0;
    public $id_order = 0;
    public $date_add;
    public $date_upd;
    public $submitted_at;
    public $approved_at;
    public $cancelled_at;
    public $expires_at;
    public $converted_at;

    /**
     * Numero progressivo, assegnato all'invio (vedi assignNumber()). Volutamente fuori da $definition:
     * viene scritto solo da assignNumber(), così un save() non lo azzera.
     */
    public $number;

    public static $definition = [
        'table' => 'dotmquoterequest_quote',
        'primary' => 'id_quote',
        'fields' => [
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_lang' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_currency' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 32],
            'customer_message' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'],
            'admin_note' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'],
            'discount_type' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16],
            'discount_value' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'token' => ['type' => self::TYPE_STRING, 'size' => 64],
            'id_cart_rule' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_cart' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'submitted_at' => ['type' => self::TYPE_DATE, 'allow_null' => true],
            'approved_at' => ['type' => self::TYPE_DATE, 'allow_null' => true],
            'cancelled_at' => ['type' => self::TYPE_DATE, 'allow_null' => true],
            'expires_at' => ['type' => self::TYPE_DATE, 'allow_null' => true],
            'converted_at' => ['type' => self::TYPE_DATE, 'allow_null' => true],
        ],
    ];

    /**
     * Dà al preventivo inviato il numero progressivo successivo (una volta sola). La chiave UNIQUE
     * su `number` fa riprovare con il numero seguente due invii contemporanei.
     */
    public function assignNumber()
    {
        if (!$this->id) {
            return false;
        }

        $db = Db::getInstance();
        $table = _DB_PREFIX_ . self::$definition['table'];
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $current = $db->getValue('SELECT `number` FROM `' . $table . '` WHERE `id_quote` = ' . (int) $this->id);
            if ($current) {
                $this->number = (int) $current;

                return true;
            }

            $next = max(self::NUMBER_START, (int) $db->getValue('SELECT MAX(`number`) FROM `' . $table . '`') + 1);
            // false se il numero è stato preso nel frattempo; 0 righe se il preventivo è stato numerato nel frattempo
            if ($db->execute('UPDATE `' . $table . '` SET `number` = ' . (int) $next . '
                WHERE `id_quote` = ' . (int) $this->id . ' AND `number` IS NULL', false)
                && $db->Affected_Rows() > 0
            ) {
                $this->number = $next;

                return true;
            }
        }

        return false;
    }

    /**
     * "P-10001", oppure "#id" per i preventivi non ancora numerati.
     */
    public function getDisplayNumber()
    {
        return self::formatNumber($this->number, $this->id);
    }

    public static function formatNumber($number, $id)
    {
        return (int) $number ? Dotmquoterequest::getNumberPrefix() . (int) $number : '#' . (int) $id;
    }

    public function isExpired()
    {
        return $this->status === 'expired'
            || (Dotmquoterequest::isDateSet($this->expires_at) && strtotime($this->expires_at) < time());
    }

    /**
     * Il carrello preventivi aperto del cliente, oppure null.
     */
    public static function getCurrentDraft($idCustomer)
    {
        if (!(int) $idCustomer) {
            return null;
        }

        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_quote` FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
             WHERE `id_customer` = ' . (int) $idCustomer . ' AND `status` = \'draft\'
             ORDER BY `id_quote` DESC'
        );

        return $id ? new self($id) : null;
    }

    public static function getOrCreateDraft($idCustomer, $idShop, $idLang, $idCurrency)
    {
        $draft = self::getCurrentDraft($idCustomer);
        if ($draft) {
            return $draft;
        }

        $draft = new self();
        $draft->id_customer = (int) $idCustomer;
        $draft->id_shop = (int) $idShop;
        $draft->id_lang = (int) $idLang;
        $draft->id_currency = (int) $idCurrency;
        $draft->status = 'draft';
        $draft->add();

        return $draft;
    }

    /**
     * Il preventivo solo se appartiene al cliente, altrimenti null.
     */
    public static function getForCustomer($id, $idCustomer)
    {
        $quote = new self((int) $id);
        if (!Validate::isLoadedObject($quote) || (int) $quote->id_customer !== (int) $idCustomer) {
            return null;
        }

        return $quote;
    }

    public static function getByCartId($idCart)
    {
        if (!(int) $idCart) {
            return null;
        }
        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_quote` FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` WHERE `id_cart` = ' . (int) $idCart
        );

        return $id ? new self($id) : null;
    }

    public static function getByOrderId($idOrder)
    {
        if (!(int) $idOrder) {
            return null;
        }
        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_quote` FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` WHERE `id_order` = ' . (int) $idOrder
        );

        return $id ? new self($id) : null;
    }

    public static function getCustomerQuotes($idCustomer)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_quote` FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
             WHERE `id_customer` = ' . (int) $idCustomer . ' AND `status` != \'draft\'
             ORDER BY `date_add` DESC'
        );

        return $rows ?: [];
    }

    /**
     * @return DotmQuoteRequestDetail[]
     */
    public function getLines()
    {
        return DotmQuoteRequestDetail::getByQuoteId((int) $this->id);
    }

    public function countLines()
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . DotmQuoteRequestDetail::$definition['table'] . '` WHERE `id_quote` = ' . (int) $this->id
        );
    }

    public function getTotalTaxIncl()
    {
        $total = 0;
        foreach ($this->getLines() as $line) {
            $total += $line->getTotalTaxIncl();
        }

        return $total;
    }

    public function getFormattedTotal()
    {
        return Dotmquoterequest::formatPrice($this->getTotalTaxIncl(), (int) $this->id_currency);
    }

    /**
     * Somma degli sconti di riga del negozio (IVA inclusa): diventa l'importo dello sconto sul carrello.
     */
    public function getLinesDiscountTotal()
    {
        $total = 0;
        foreach ($this->getLines() as $line) {
            $total += $line->getDiscountTaxIncl();
        }

        return round($total, 2);
    }

    /**
     * Totale di listino meno lo sconto del negozio: quanto paga il cliente.
     */
    public function getDiscountedTotal()
    {
        $total = $this->getTotalTaxIncl();

        if ($this->discount_type === 'percent') {
            $total -= $total * ((float) $this->discount_value / 100);
        } elseif ($this->discount_type === 'amount') {
            $total -= (float) $this->discount_value;
        }

        return max(0, $total);
    }

    public function delete()
    {
        Db::getInstance()->delete(DotmQuoteRequestDetail::$definition['table'], '`id_quote` = ' . (int) $this->id);

        return parent::delete();
    }
}
