<div class="dqr-nav"{if $dqr_nav.css_vars} style="{$dqr_nav.css_vars|escape:'html':'UTF-8'}"{/if}>
  <a href="{$dqr_nav.url|escape:'html':'UTF-8'}" class="dqr-nav-link" rel="nofollow">
    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM8 12h8v2H8v-2zm0 4h8v2H8v-2z"/></svg>
    <span class="dqr-nav-label">{$dqr_nav.label|escape:'html':'UTF-8'}</span>
    <span class="dqr-nav-count js-dqr-count">{$dqr_nav.count|intval}</span>
  </a>
</div>
