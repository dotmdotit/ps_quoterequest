{**
 * Dettaglio di un preventivo (area clienti).
 *}
{extends file='customer/page.tpl'}

{block name='page_title'}
  {$dqr.t.detail_title|escape:'html':'UTF-8'} {$dqr.number|escape:'html':'UTF-8'}
{/block}

{block name='page_content'}
<div class="dqr" id="dotmquoterequest-detail"{if $dqr.css_vars} style="{$dqr.css_vars|escape:'html':'UTF-8'}"{/if}>
  <div class="dqr-card">
    <div class="dqr-detail-head">
      <div>
        <span class="dqr-badge dqr-badge-{$dqr.status|escape:'html':'UTF-8'}">{$dqr.status_label|escape:'html':'UTF-8'}</span>
        {if $dqr.submitted_at}<span class="dqr-muted">{$dqr.t.col_submitted|escape:'html':'UTF-8'}: {$dqr.submitted_at|escape:'html':'UTF-8'}</span>{/if}
        {if $dqr.expires_at}<span class="dqr-muted">{$dqr.t.valid_until|escape:'html':'UTF-8'}: <strong>{$dqr.expires_at|escape:'html':'UTF-8'}</strong></span>{/if}
        {if $dqr.order_url}<span class="dqr-muted">{$dqr.t.order|escape:'html':'UTF-8'}: <a href="{$dqr.order_url|escape:'html':'UTF-8'}">{$dqr.order_reference|escape:'html':'UTF-8'}</a></span>{/if}
      </div>
      <div class="dqr-actions">
        {if $dqr.pdf_url}<a class="dqr-btn dqr-btn-ghost" href="{$dqr.pdf_url|escape:'html':'UTF-8'}">{$dqr.t.btn_pdf|escape:'html':'UTF-8'}</a>{/if}
        {if $dqr.activate_url}<a class="dqr-btn" href="{$dqr.activate_url|escape:'html':'UTF-8'}">{$dqr.t.btn_checkout|escape:'html':'UTF-8'}</a>{/if}
      </div>
    </div>

    {if $dqr.status == 'pending'}
      <p class="dqr-note">{$dqr.t.detail_pending|escape:'html':'UTF-8'}</p>
    {/if}

    {if $dqr.admin_note}
      <div class="dqr-alert">{$dqr.admin_note|escape:'html':'UTF-8'|nl2br nofilter}</div>
    {/if}

    <div class="dqr-table-wrap">
      <table class="dqr-table">
        <thead>
          <tr>
            <th>{$dqr.t.col_product|escape:'html':'UTF-8'}</th>
            <th class="dqr-right">{$dqr.t.col_unit_price|escape:'html':'UTF-8'}</th>
            <th class="dqr-right">{$dqr.t.col_qty|escape:'html':'UTF-8'}</th>
            <th class="dqr-right">{$dqr.t.col_total|escape:'html':'UTF-8'}</th>
            {if $dqr.has_line_discounts}<th class="dqr-right">{$dqr.t.col_discount|escape:'html':'UTF-8'}</th><th class="dqr-right">{$dqr.t.col_net_total|escape:'html':'UTF-8'}</th>{/if}
          </tr>
        </thead>
        <tbody>
          {foreach $dqr.lines as $line}
            <tr>
              <td data-label="{$dqr.t.col_product|escape:'html':'UTF-8'}">
                {$line.product_name|escape:'html':'UTF-8'}
                {if $line.combination_name}<br><small class="dqr-muted">{$line.combination_name|escape:'html':'UTF-8'}</small>{/if}
                {if $line.product_reference}<br><small class="dqr-muted">{$dqr.t.ref|escape:'html':'UTF-8'} {$line.product_reference|escape:'html':'UTF-8'}</small>{/if}
              </td>
              <td data-label="{$dqr.t.col_unit_price|escape:'html':'UTF-8'}" class="dqr-right">{$line.unit_price|escape:'html':'UTF-8'}</td>
              <td data-label="{$dqr.t.col_qty|escape:'html':'UTF-8'}" class="dqr-right">{$line.quantity|intval}</td>
              <td data-label="{$dqr.t.col_total|escape:'html':'UTF-8'}" class="dqr-right">{$line.total|escape:'html':'UTF-8'}</td>
              {if $dqr.has_line_discounts}
                <td data-label="{$dqr.t.col_discount|escape:'html':'UTF-8'}" class="dqr-right">{if $line.discount}-{$line.discount|escape:'html':'UTF-8'}{else}&mdash;{/if}</td>
                <td data-label="{$dqr.t.col_net_total|escape:'html':'UTF-8'}" class="dqr-right">{$line.net_total|escape:'html':'UTF-8'}</td>
              {/if}
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>

    <dl class="dqr-totals dqr-totals-detail">
      <div><dt>{$dqr.t.list_total_tax_incl|escape:'html':'UTF-8'}</dt><dd>{$dqr.total|escape:'html':'UTF-8'}</dd></div>
      {if $dqr.discount}
        <div><dt>{$dqr.t.agreed_discount|escape:'html':'UTF-8'}</dt><dd>{$dqr.discount|escape:'html':'UTF-8'}</dd></div>
        <div class="dqr-totals-main"><dt>{$dqr.t.to_pay|escape:'html':'UTF-8'}</dt><dd>{$dqr.discounted_total|escape:'html':'UTF-8'}</dd></div>
      {/if}
    </dl>

    {if $dqr.customer_message}
      <p class="dqr-note"><strong>{$dqr.t.message_label|escape:'html':'UTF-8'}</strong><br>{$dqr.customer_message|escape:'html':'UTF-8'|nl2br nofilter}</p>
    {/if}

    <p><a class="dqr-link" href="{$dqr.list_url|escape:'html':'UTF-8'}">&larr; {$dqr.t.back_to_list|escape:'html':'UTF-8'}</a></p>
  </div>
</div>
{/block}
