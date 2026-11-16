{extends file='page.tpl'}

{block name='page_title'}
  {$dqr.title|escape:'html':'UTF-8'}
{/block}

{block name='page_content'}
<div class="dqr"{if $dqr.css_vars} style="{$dqr.css_vars|escape:'html':'UTF-8'}"{/if}>
  <div class="dqr-alert dqr-alert-error" role="alert">{$dqr.error|escape:'html':'UTF-8'}</div>
  <p><a class="dqr-btn dqr-btn-ghost" href="{$dqr.list_url|escape:'html':'UTF-8'}">{$dqr.list_label|escape:'html':'UTF-8'}</a></p>
</div>
{/block}
