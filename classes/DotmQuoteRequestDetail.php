<?php
/**
 * Riga di un preventivo (tabella ps_dotmquoterequest_detail).
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmQuoteRequestDetail extends ObjectModel
{
    /** Righe massime per preventivo */
    const MAX_LINES = 50;

    /** Quantità massima per riga */
    const MAX_QTY = 99999;

    public $id_quote;
    public $id_product;
    public $id_product_attribute = 0;
    public $combination_name;
    public $quantity = 1;
    public $product_name;
    public $product_reference;
    public $unit_price_tax_excl = 0;
    public $unit_price_tax_incl = 0;
    /** Sconto sulla riga, in percentuale del totale di listino (impostato dal negozio prima dell'approvazione) */
    public $discount_percent = 0;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'dotmquoterequest_detail',
        'primary' => 'id_quote_detail',
        'fields' => [
            'id_quote' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_product_attribute' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'combination_name' => ['type' => self::TYPE_STRING, 'size' => 255],
            'quantity' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'product_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
            'product_reference' => ['type' => self::TYPE_STRING, 'validate' => 'isReference', 'size' => 64],
            'unit_price_tax_excl' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'unit_price_tax_incl' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'discount_percent' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * @return self[]
     */
    public static function getByQuoteId($idQuote)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
             WHERE `id_quote` = ' . (int) $idQuote . '
             ORDER BY `id_quote_detail` ASC'
        );

        $lines = [];
        foreach ($rows ?: [] as $row) {
            $line = new self();
            $line->hydrate($row);
            $line->id = (int) $row['id_quote_detail'];
            $lines[] = $line;
        }

        return $lines;
    }

    public static function findLine($idQuote, $idProduct, $idProductAttribute)
    {
        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_quote_detail` FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
             WHERE `id_quote` = ' . (int) $idQuote . '
             AND `id_product` = ' . (int) $idProduct . '
             AND `id_product_attribute` = ' . (int) $idProductAttribute
        );

        return $id ? new self($id) : null;
    }

    /**
     * Descrizione leggibile della variante, ad esempio "Taglia: M, Colore: Rosso".
     */
    public static function buildCombinationName(Product $product, $idProductAttribute, $idLang)
    {
        if (!$idProductAttribute || !Validate::isLoadedObject($product)) {
            return '';
        }

        $parts = [];
        foreach ($product->getAttributeCombinationsById((int) $idProductAttribute, (int) $idLang) ?: [] as $attribute) {
            if (isset($attribute['group_name'], $attribute['attribute_name'])) {
                $parts[] = $attribute['group_name'] . ': ' . $attribute['attribute_name'];
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Nuova riga con nome, variante, riferimento e prezzi del prodotto (per il cliente indicato).
     */
    public static function build($idQuote, Product $product, $idProductAttribute, $qty, $idLang, $idCustomer = null)
    {
        $idProductAttribute = (int) $idProductAttribute;
        $line = new self();
        $line->id_quote = (int) $idQuote;
        $line->id_product = (int) $product->id;
        $line->id_product_attribute = $idProductAttribute;
        $line->combination_name = self::buildCombinationName($product, $idProductAttribute, $idLang);
        $line->quantity = (int) $qty;
        $line->product_name = is_array($product->name) ? (string) reset($product->name) : (string) $product->name;
        $line->product_reference = (string) $product->reference;
        if ($idProductAttribute) {
            $combination = new Combination($idProductAttribute);
            if (Validate::isLoadedObject($combination) && $combination->reference) {
                $line->product_reference = (string) $combination->reference;
            }
        }
        $line->unit_price_tax_excl = self::customerPrice((int) $product->id, $idProductAttribute, false, $idCustomer);
        $line->unit_price_tax_incl = self::customerPrice((int) $product->id, $idProductAttribute, true, $idCustomer);

        return $line;
    }

    /**
     * Prezzo unitario come lo vede il cliente (riduzioni di gruppo e prezzi specifici compresi).
     */
    public static function customerPrice($idProduct, $idProductAttribute, $taxIncl = true, $idCustomer = null)
    {
        return (float) Product::getPriceStatic(
            (int) $idProduct,
            (bool) $taxIncl,
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

    public function getTotalTaxIncl()
    {
        return (float) $this->unit_price_tax_incl * (int) $this->quantity;
    }

    /**
     * Sconto sul totale di listino della riga, arrotondato al centesimo come nel back office.
     */
    public function getDiscountTaxIncl()
    {
        return round($this->getTotalTaxIncl() * (float) $this->discount_percent / 100, 2);
    }

    public function getNetTotalTaxIncl()
    {
        return $this->getTotalTaxIncl() - $this->getDiscountTaxIncl();
    }
}
