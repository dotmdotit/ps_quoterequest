{**
 * Carrello preventivi.
 *}
{extends file='page.tpl'}

{block name='page_title'}
  {$dqr.t.page_title|escape:'html':'UTF-8'}
{/block}

{block name='page_content'}
<div class="dqr dqr-cart" id="dotmquoterequest-cart"{if $dqr.css_vars} style="{$dqr.css_vars|escape:'html':'UTF-8'}"{/if}>

  {if $dqr.flash}
    <div class="dqr-alert dqr-alert-success" role="status">{$dqr.flash|escape:'html':'UTF-8'}</div>
  {/if}

  {if !$dqr.logged}
    {* ---------- visitatore: invito ad accedere ---------- *}
    <div class="dqr-card dqr-empty">
      <div class="dqr-empty-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="40" height="40"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 12h8v2H8v-2zm0 4h8v2H8v-2z"/></svg>
      </div>
      <h2 class="dqr-title">{$dqr.t.guest_title|escape:'html':'UTF-8'}</h2>
      <p class="dqr-muted">{$dqr.t.guest_text|escape:'html':'UTF-8'}</p>
      <div class="dqr-actions">
        <a class="dqr-btn" href="{$dqr.login_url|escape:'html':'UTF-8'}">{$dqr.t.btn_sign_in|escape:'html':'UTF-8'}</a>
        <a class="dqr-btn dqr-btn-ghost" href="{$dqr.register_url|escape:'html':'UTF-8'}">{$dqr.t.btn_register|escape:'html':'UTF-8'}</a>
      </div>
    </div>

  {elseif !$dqr.lines}
    {* ---------- carrello preventivi vuoto ---------- *}
    <div class="dqr-card dqr-empty">
      <div class="dqr-empty-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="40" height="40"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 12h8v2H8v-2zm0 4h8v2H8v-2z"/></svg>
      </div>
      <h2 class="dqr-title">{$dqr.t.empty_title|escape:'html':'UTF-8'}</h2>
      <p class="dqr-muted">{$dqr.t.empty_text|escape:'html':'UTF-8'}</p>
      <div class="dqr-actions">
        <a class="dqr-btn" href="{$dqr.shop_url|escape:'html':'UTF-8'}">{$dqr.t.btn_continue|escape:'html':'UTF-8'}</a>
        <a class="dqr-btn dqr-btn-ghost" href="{$dqr.list_url|escape:'html':'UTF-8'}">{$dqr.t.account_list|escape:'html':'UTF-8'}</a>
      </div>
    </div>

  {else}
    {* ---------- righe + riepilogo ---------- *}
    <div class="dqr-grid">
      <section class="dqr-card dqr-lines" aria-label="{$dqr.t.lines_title|escape:'html':'UTF-8'}">
        <header class="dqr-card-head">
          <h2 class="dqr-title">{$dqr.t.lines_title|escape:'html':'UTF-8'}</h2>
          <span class="dqr-muted">{$dqr.count_label|escape:'html':'UTF-8'}</span>
        </header>

        <ul class="dqr-items">
          {foreach $dqr.lines as $line}
            <li class="dqr-item{if !$line.available} is-unavailable{/if}">
              <div class="dqr-item-media">
                {if $line.image}
                  {if $line.url}<a href="{$line.url|escape:'html':'UTF-8'}">{/if}
                    <img src="{$line.image|escape:'html':'UTF-8'}" alt="{$line.product_name|escape:'html':'UTF-8'}" loading="lazy" width="80" height="80">
                  {if $line.url}</a>{/if}
                {else}
                  <span class="dqr-item-noimg" aria-hidden="true"></span>
                {/if}
              </div>

              <div class="dqr-item-info">
                {if $line.url}
                  <a class="dqr-item-name" href="{$line.url|escape:'html':'UTF-8'}">{$line.product_name|escape:'html':'UTF-8'}</a>
                {else}
                  <span class="dqr-item-name">{$line.product_name|escape:'html':'UTF-8'}</span>
                {/if}
                {if $line.combination_name}<span class="dqr-item-meta">{$line.combination_name|escape:'html':'UTF-8'}</span>{/if}
                {if $line.product_reference}<span class="dqr-item-meta">{$dqr.t.ref|escape:'html':'UTF-8'} {$line.product_reference|escape:'html':'UTF-8'}</span>{/if}
                <span class="dqr-item-unit">{$line.unit_price|escape:'html':'UTF-8'} <small>{$dqr.t.each|escape:'html':'UTF-8'}</small></span>
                {if !$line.available}<span class="dqr-item-warning">{$dqr.t.product_unavailable|escape:'html':'UTF-8'}</span>{/if}
              </div>

              <form class="dqr-item-qty js-dqr-qty-form" method="post" action="{$dqr.action|escape:'html':'UTF-8'}">
                <input type="hidden" name="token" value="{$dqr.token|escape:'html':'UTF-8'}">
                <input type="hidden" name="dqr_action" value="update">
                <input type="hidden" name="id_quote_detail" value="{$line.id|intval}">
                <label class="dqr-sr" for="dqr-qty-{$line.id|intval}">{$dqr.t.col_qty|escape:'html':'UTF-8'}</label>
                <div class="dqr-stepper">
                  <button type="button" class="dqr-step js-dqr-step" data-step="-1" aria-label="{$dqr.t.qty_less|escape:'html':'UTF-8'}">&minus;</button>
                  <input id="dqr-qty-{$line.id|intval}" class="dqr-qty-input js-dqr-qty" type="number" name="quantity" min="0" max="{$dqr.max_qty|intval}" step="1" value="{$line.quantity|intval}" inputmode="numeric">
                  <button type="button" class="dqr-step js-dqr-step" data-step="1" aria-label="{$dqr.t.qty_more|escape:'html':'UTF-8'}">+</button>
                </div>
                <noscript><button type="submit" class="dqr-link">{$dqr.t.btn_update|escape:'html':'UTF-8'}</button></noscript>
              </form>

              <div class="dqr-item-total">{$line.total|escape:'html':'UTF-8'}</div>

              <form class="dqr-item-remove" method="post" action="{$dqr.action|escape:'html':'UTF-8'}">
                <input type="hidden" name="token" value="{$dqr.token|escape:'html':'UTF-8'}">
                <input type="hidden" name="dqr_action" value="remove">
                <input type="hidden" name="id_quote_detail" value="{$line.id|intval}">
                <button type="submit" class="dqr-remove" title="{$dqr.t.btn_remove|escape:'html':'UTF-8'}" aria-label="{$dqr.t.btn_remove|escape:'html':'UTF-8'}: {$line.product_name|escape:'html':'UTF-8'}">
                  <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm-3 6h12l-1 12H7L6 9zm4 2v8h2v-8h-2zm4 0v8h2v-8h-2z"/></svg>
                </button>
              </form>
            </li>
          {/foreach}
        </ul>

        <footer class="dqr-card-foot">
          <a class="dqr-link" href="{$dqr.shop_url|escape:'html':'UTF-8'}">&larr; {$dqr.t.btn_continue|escape:'html':'UTF-8'}</a>
          <form method="post" action="{$dqr.action|escape:'html':'UTF-8'}" class="js-dqr-clear" data-confirm="{$dqr.t.clear_confirm|escape:'html':'UTF-8'}">
            <input type="hidden" name="token" value="{$dqr.token|escape:'html':'UTF-8'}">
            <input type="hidden" name="dqr_action" value="clear">
            <button type="submit" class="dqr-link dqr-link-muted">{$dqr.t.btn_clear|escape:'html':'UTF-8'}</button>
          </form>
        </footer>
      </section>

      <aside class="dqr-card dqr-summary">
        <h2 class="dqr-title">{$dqr.t.summary_title|escape:'html':'UTF-8'}</h2>

        <dl class="dqr-totals">
          <div><dt>{$dqr.t.summary_products|escape:'html':'UTF-8'}</dt><dd>{$dqr.qty_total|intval}</dd></div>
          <div class="dqr-totals-main"><dt>{$dqr.t.list_total_tax_incl|escape:'html':'UTF-8'}</dt><dd>{$dqr.total|escape:'html':'UTF-8'}</dd></div>
        </dl>
        <p class="dqr-note">{$dqr.t.summary_note|escape:'html':'UTF-8'}</p>

        {if $dqr.show_minimum}
          <div class="dqr-minimum" role="status">
            <div class="dqr-progress" aria-hidden="true"><span style="width: {$dqr.minimum_progress|intval}%"></span></div>
            <p>{$dqr.minimum_message|escape:'html':'UTF-8'}</p>
          </div>
        {/if}

        <form method="post" action="{$dqr.action|escape:'html':'UTF-8'}" class="dqr-submit" id="dqr-submit-form">
          <input type="hidden" name="token" value="{$dqr.token|escape:'html':'UTF-8'}">
          <input type="hidden" name="dqr_action" value="submit">
          <label for="dqr-message" class="dqr-label">{$dqr.t.message_label|escape:'html':'UTF-8'}</label>
          <textarea id="dqr-message" name="customer_message" rows="4" maxlength="2000" class="dqr-textarea js-dqr-message" placeholder="{$dqr.t.message_placeholder|escape:'html':'UTF-8'}">{$dqr.message|escape:'html':'UTF-8'}</textarea>
          <button type="submit" class="dqr-btn dqr-btn-block"{if $dqr.below_minimum} disabled aria-disabled="true"{/if}>
            {$dqr.t.btn_submit|escape:'html':'UTF-8'}
          </button>
        </form>

        <ol class="dqr-steps">
          <li>{$dqr.t.how_1|escape:'html':'UTF-8'}</li>
          <li>{$dqr.t.how_2|escape:'html':'UTF-8'}</li>
          <li>{$dqr.t.how_3|escape:'html':'UTF-8'}</li>
        </ol>
      </aside>
    </div>
  {/if}
</div>
{/block}
