{**
 * I miei preventivi (area clienti).
 *}
{extends file='customer/page.tpl'}

{block name='page_title'}
  {$dqr.t.account_list|escape:'html':'UTF-8'}
{/block}

{block name='page_content'}
<div class="dqr" id="dotmquoterequest-list"{if $dqr.css_vars} style="{$dqr.css_vars|escape:'html':'UTF-8'}"{/if}>
  {if $dqr.submitted}
    <div class="dqr-alert dqr-alert-success" role="status">{$dqr.submitted|escape:'html':'UTF-8'}</div>
  {/if}
  {if $dqr.cancelled}
    <div class="dqr-alert" role="status">{$dqr.cancelled|escape:'html':'UTF-8'}</div>
  {/if}

  {if !$dqr.quotes}
    <div class="dqr-card dqr-empty">
      <p class="dqr-muted">{$dqr.t.list_empty|escape:'html':'UTF-8'}</p>
      <div class="dqr-actions">
        <a class="dqr-btn" href="{$dqr.cart_url|escape:'html':'UTF-8'}">{$dqr.t.account_cart|escape:'html':'UTF-8'}</a>
      </div>
    </div>
  {else}
    <div class="dqr-card dqr-table-wrap">
      <table class="dqr-table">
        <thead>
          <tr>
            <th>{$dqr.t.col_number|escape:'html':'UTF-8'}</th>
            <th>{$dqr.t.col_submitted|escape:'html':'UTF-8'}</th>
            <th>{$dqr.t.col_status|escape:'html':'UTF-8'}</th>
            <th class="dqr-right">{$dqr.t.col_list_total|escape:'html':'UTF-8'}</th>
            <th><span class="dqr-sr">{$dqr.t.btn_details|escape:'html':'UTF-8'}</span></th>
          </tr>
        </thead>
        <tbody>
          {foreach $dqr.quotes as $q}
            <tr>
              <td data-label="{$dqr.t.col_number|escape:'html':'UTF-8'}"><strong>{$q.number|escape:'html':'UTF-8'}</strong></td>
              <td data-label="{$dqr.t.col_submitted|escape:'html':'UTF-8'}">{$q.submitted_at|escape:'html':'UTF-8'}</td>
              <td data-label="{$dqr.t.col_status|escape:'html':'UTF-8'}"><span class="dqr-badge dqr-badge-{$q.status|escape:'html':'UTF-8'}">{$q.status_label|escape:'html':'UTF-8'}</span></td>
              <td data-label="{$dqr.t.col_list_total|escape:'html':'UTF-8'}" class="dqr-right">{$q.total|escape:'html':'UTF-8'}</td>
              <td class="dqr-right"><a class="dqr-btn dqr-btn-ghost dqr-btn-sm" href="{$q.url|escape:'html':'UTF-8'}">{$dqr.t.btn_details|escape:'html':'UTF-8'}</a></td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  {/if}
</div>
{/block}
