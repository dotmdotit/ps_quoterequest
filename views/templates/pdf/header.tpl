<table style="width: 100%;" cellpadding="0">
  <tr>
    <td style="width: 55%;">
      {if $logo}
        <img src="{$logo.path}" style="width:{$logo.width}px; height:{$logo.height}px;" />
      {else}
        <span style="font-size: 16pt; font-weight: bold;">{$shop.name|escape:'html':'UTF-8'}</span>
      {/if}
    </td>
    <td style="width: 45%; text-align: right;">
      <span style="font-size: 16pt; font-weight: bold; color: #222222;">{$t.heading|escape:'html':'UTF-8'} {$number|escape:'html':'UTF-8'}</span><br />
      <span style="font-size: 9pt; color: #666666;">{$t.date|escape:'html':'UTF-8'}: {$issued_at|escape:'html':'UTF-8'}</span><br />
      <span style="font-size: 9pt; color: #666666;">{$t.valid_until|escape:'html':'UTF-8'}: <b>{$expires_at|escape:'html':'UTF-8'}</b></span>
    </td>
  </tr>
</table>
