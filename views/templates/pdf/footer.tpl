<table style="width: 100%; border-top: 0.5px solid #cccccc;" cellpadding="4">
  <tr>
    <td style="text-align: center; font-size: 7pt; color: #555555;">
      <b>{$shop.name|escape:'html':'UTF-8'}</b>{if $shop.address} &middot; {$shop.address|escape:'html':'UTF-8'}{/if}<br />
      {if $shop.phone}{$t.phone|escape:'html':'UTF-8'} {$shop.phone|escape:'html':'UTF-8'}{/if}{if $shop.phone && $shop.email} &middot; {/if}{if $shop.email}{$shop.email|escape:'html':'UTF-8'}{/if}{if $shop.website} &middot; {$shop.website|escape:'html':'UTF-8'}{/if}
      {if $shop.details}<br />{$shop.details|escape:'html':'UTF-8'}{/if}
    </td>
  </tr>
</table>
