{* Cliente e dati del preventivo *}
<table style="width: 100%;" cellpadding="0">
  <tr>
    <td style="width: 55%; font-size: 9pt;">
      <span style="font-size: 8pt; color: #888888;">{$t.customer|escape:'html':'UTF-8'}</span><br />
      {if $customer.company}<b>{$customer.company|escape:'html':'UTF-8'}</b><br />{/if}
      {if $customer.company}{$customer.name|escape:'html':'UTF-8'}{else}<b>{$customer.name|escape:'html':'UTF-8'}</b>{/if}<br />
      {foreach from=$customer.address item=row}{$row|escape:'html':'UTF-8'}<br />{/foreach}
      {if $customer.vat_number}{$t.vat_number|escape:'html':'UTF-8'} {$customer.vat_number|escape:'html':'UTF-8'}<br />{/if}
      {$customer.email|escape:'html':'UTF-8'}
    </td>
    <td style="width: 45%; font-size: 9pt;">
      <table style="width: 100%;" cellpadding="3">
        <tr>
          <td style="width: 50%; color: #888888;">{$t.number|escape:'html':'UTF-8'}</td>
          <td style="width: 50%; text-align: right;"><b>{$number|escape:'html':'UTF-8'}</b></td>
        </tr>
        <tr>
          <td style="color: #888888;">{$t.date|escape:'html':'UTF-8'}</td>
          <td style="text-align: right;">{$issued_at|escape:'html':'UTF-8'}</td>
        </tr>
        <tr>
          <td style="color: #888888;">{$t.expiry|escape:'html':'UTF-8'}</td>
          <td style="text-align: right;"><b>{$expires_at|escape:'html':'UTF-8'}</b></td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<br /><br />

{* Righe *}
<table style="width: 100%; font-size: 8pt;" cellpadding="4">
  <thead>
    <tr style="background-color: #222222; color: #ffffff; font-weight: bold;">
      <th style="width: {if $has_line_discounts}34%{else}49%{/if};">{$t.col_product|escape:'html':'UTF-8'}</th>
      <th style="width: 7%; text-align: center;">{$t.col_qty|escape:'html':'UTF-8'}</th>
      <th style="width: 13%; text-align: right;">{$t.col_unit_price|escape:'html':'UTF-8'}</th>
      <th style="width: 14%; text-align: right;">{$t.col_list_total|escape:'html':'UTF-8'}</th>
      {if $has_line_discounts}<th style="width: 15%; text-align: right;">{$t.col_discount|escape:'html':'UTF-8'}</th>{/if}
      <th style="width: 17%; text-align: right;">{$t.col_total|escape:'html':'UTF-8'}</th>
    </tr>
  </thead>
  <tbody>
    {foreach from=$lines item=line name=lines}
      <tr style="background-color: {if $smarty.foreach.lines.iteration % 2}#ffffff{else}#f4f4f4{/if};">
        <td style="width: {if $has_line_discounts}34%{else}49%{/if};">
          {$line.name|escape:'html':'UTF-8'}
          {if $line.variant}<br /><span style="color: #666666;">{$line.variant|escape:'html':'UTF-8'}</span>{/if}
          {if $line.reference}<br /><span style="color: #888888;">{$t.ref|escape:'html':'UTF-8'} {$line.reference|escape:'html':'UTF-8'}</span>{/if}
        </td>
        <td style="width: 7%; text-align: center;">{$line.quantity|intval}</td>
        <td style="width: 13%; text-align: right;">{$line.unit_price|escape:'html':'UTF-8'}</td>
        <td style="width: 14%; text-align: right;">{$line.list_total|escape:'html':'UTF-8'}</td>
        {if $has_line_discounts}
          <td style="width: 15%; text-align: right;">{if $line.discount}-{$line.discount|escape:'html':'UTF-8'}<br /><span style="color: #888888;">({$line.discount_percent|escape:'html':'UTF-8'})</span>{else}&mdash;{/if}</td>
        {/if}
        <td style="width: 17%; text-align: right;"><b>{$line.net_total|escape:'html':'UTF-8'}</b></td>
      </tr>
    {/foreach}
  </tbody>
</table>

<br /><br />

{* Totali *}
<table style="width: 100%; font-size: 9pt;" cellpadding="4">
  <tr>
    <td style="width: 40%;"></td>
    <td style="width: 60%;">
      <table style="width: 100%;" cellpadding="4">
        <tr>
          <td style="width: 65%;">{$t.list_total|escape:'html':'UTF-8'}</td>
          <td style="width: 35%; text-align: right;">{$total|escape:'html':'UTF-8'}</td>
        </tr>
        {if $has_discounts}
          <tr>
            <td>{$t.discount|escape:'html':'UTF-8'}</td>
            <td style="text-align: right;">-{$discount|escape:'html':'UTF-8'}</td>
          </tr>
        {/if}
        <tr>
          <td style="color: #666666;">{$t.tax_excl|escape:'html':'UTF-8'}</td>
          <td style="text-align: right; color: #666666;">{$net_tax_excl|escape:'html':'UTF-8'}</td>
        </tr>
        <tr>
          <td style="color: #666666;">{$t.tax|escape:'html':'UTF-8'}</td>
          <td style="text-align: right; color: #666666;">{$tax|escape:'html':'UTF-8'}</td>
        </tr>
        <tr style="background-color: #222222; color: #ffffff;">
          <td style="font-size: 10pt;"><b>{$t.grand_total|escape:'html':'UTF-8'}</b></td>
          <td style="font-size: 10pt; text-align: right;"><b>{$net|escape:'html':'UTF-8'}</b></td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<br /><br />

{* Condizioni *}
<table style="width: 100%; font-size: 8pt;" cellpadding="6">
  <tr>
    <td style="background-color: #f4f4f4;">
      <b>{$t.terms|escape:'html':'UTF-8'}</b><br />
      &bull; {$t.terms_prices|escape:'html':'UTF-8'}<br />
      &bull; {$t.terms_validity|escape:'html':'UTF-8'}<br />
      &bull; {if $payment_restricted}{$t.terms_payment_restricted|escape:'html':'UTF-8'}{else}{$t.terms_payment|escape:'html':'UTF-8'}{/if}
      {if $bank}
        <br /><br /><b>{$t.bank_title|escape:'html':'UTF-8'}</b><br />
        {if $bank.owner}{$t.bank_owner|escape:'html':'UTF-8'}: {$bank.owner|escape:'html':'UTF-8'}<br />{/if}
        {if $bank.details}{$bank.details|escape:'html':'UTF-8'|nl2br nofilter}<br />{/if}
        {if $bank.address}{$bank.address|escape:'html':'UTF-8'|nl2br nofilter}<br />{/if}
        {$t.bank_reference|escape:'html':'UTF-8'}
      {/if}
    </td>
  </tr>
</table>

{if $customer_message}
  <br /><br />
  <span style="font-size: 8pt; color: #888888;">{$t.customer_notes|escape:'html':'UTF-8'}</span><br />
  <span style="font-size: 8pt;">{$customer_message|escape:'html':'UTF-8'|nl2br nofilter}</span>
{/if}
