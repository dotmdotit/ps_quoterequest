<div class="dqr dqr-add"{if $dqr_product.css_vars} style="{$dqr_product.css_vars|escape:'html':'UTF-8'}"{/if}>
  {if $dqr_product.logged}
    <button type="button"
            class="dqr-btn dqr-btn-ghost js-dqr-add"
            data-url="{$dqr_product.ajax_url|escape:'html':'UTF-8'}"
            data-id-product="{$dqr_product.id_product|intval}"
            data-id-product-attribute="{$dqr_product.id_product_attribute|intval}"
            data-token="{$dqr_product.token|escape:'html':'UTF-8'}">
      <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11zm-7-8h2v2h2v2h-2v2h-2v-2H9v-2h2v-2z"/></svg>
      <span>{$dqr_product.t.add|escape:'html':'UTF-8'}</span>
    </button>
    <a class="dqr-add-link js-dqr-cart-link"{if !$dqr_product.count} hidden{/if} href="{$dqr_product.cart_url|escape:'html':'UTF-8'}">
      {$dqr_product.t.in_quote|escape:'html':'UTF-8'} (<span class="js-dqr-count">{$dqr_product.count|intval}</span>)
    </a>
    <p class="dqr-add-message js-dqr-message" role="status" aria-live="polite"></p>
  {else}
    <a href="{$dqr_product.login_url|escape:'html':'UTF-8'}" class="dqr-btn dqr-btn-ghost">{$dqr_product.t.login|escape:'html':'UTF-8'}</a>
  {/if}
</div>
