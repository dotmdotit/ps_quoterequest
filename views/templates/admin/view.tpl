<div class="panel">
  <div class="panel-heading">
    {l s='Preventivo'} {$quote_number|escape:'html':'UTF-8'} &mdash; {$customer->firstname|escape:'html':'UTF-8'} {$customer->lastname|escape:'html':'UTF-8'}
    <span class="label label-{$status_colors[$quote->status]|default:'default'} pull-right">{$status_labels[$quote->status]}</span>
  </div>
  <p class="text-right">
    <a href="{$pdf_link|escape:'html':'UTF-8'}" class="btn btn-default">
      <i class="icon-file-pdf-o"></i> {l s='Scarica PDF'}
    </a>
    {if $quote->status == 'pending'}<br><small class="text-muted">{l s='Anteprima con i dati salvati: salva le modifiche prima di scaricarlo.'}</small>{/if}
  </p>
  <div class="panel-body">

    {if $quote->customer_message}
      <p><strong>{l s='Messaggio del cliente'}:</strong><br>{$quote->customer_message|escape:'html':'UTF-8'|nl2br nofilter}</p>
    {/if}

    {if $quote->admin_note}
      <p><strong>{l s='Nota admin'}:</strong><br>{$quote->admin_note|escape:'html':'UTF-8'|nl2br nofilter}</p>
    {/if}

    {if $discount_summary}
      <p><strong>{l s='Sconto applicato'}:</strong> {$discount_summary}</p>
    {/if}

    {if $order_reference}
      <p><strong>{l s='Ordine'}:</strong> <a href="{$order_link|escape:'html':'UTF-8'}">{$order_reference|escape:'html':'UTF-8'}</a></p>
    {/if}

    {if $activate_link}
      <p><strong>{l s='Link di attivazione'}:</strong> <code>{$activate_link}</code></p>
    {/if}

    {if $quote->status == 'pending'}
      <form method="post" action="{$current_index}&token={$token}" id="dqr-edit-form">
        <input type="hidden" name="id_quote" value="{$quote->id}">

        <div class="row">
          <div class="col-lg-6">
            <div class="form-group">
              <label class="control-label">{l s='Aggiungi prodotto'}</label>
              <input type="hidden" id="dqr_edit_id_customer" value="{$quote->id_customer|intval}">
              <input type="text" id="dqr_edit_product_query" class="form-control" autocomplete="off"
                     placeholder="{l s='Cerca per nome o riferimento (min. 2 caratteri)'}">
              <div id="dqr-edit-product-results" class="list-group" style="margin-top:5px"></div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="form-group">
              <label class="control-label">{l s='Sconto sul totale (IVA inclusa)'}</label>
              <div class="form-inline">
                <label class="radio-inline">
                  <input type="radio" name="discount_type" value="percent" checked="checked" id="dqr_discount_percent"> %
                </label>
                <label class="radio-inline">
                  <input type="radio" name="discount_type" value="amount" id="dqr_discount_amount"> {$currency_sign|escape:'html':'UTF-8'}
                </label>
                <input type="text" id="discount_value" class="form-control" style="width:120px;margin-left:10px" value="" inputmode="decimal" placeholder="0">
              </div>
              <p class="help-block">{l s='Viene applicato a tutte le righe: poi puoi modificarlo riga per riga. Un importo fisso viene ripartito in proporzione.'}</p>
            </div>
          </div>
        </div>

        <table class="table" id="dqr-edit-lines">
          <thead>
            <tr>
              <th>{l s='Prodotto'}</th>
              <th>{l s='Variante'}</th>
              <th class="text-right">{l s='Prezzo unitario'}</th>
              <th style="width:90px">{l s='Quantità'}</th>
              <th class="text-right">{l s='Totale listino'}</th>
              <th style="width:110px">{l s='Sconto %'}</th>
              <th style="width:120px">{l s='Sconto'} {$currency_sign|escape:'html':'UTF-8'}</th>
              <th class="text-right">{l s='Totale scontato'}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$lines item=line}
              <tr class="js-dqr-line" data-price="{$line.unit_price_raw}" data-product="{$line.id_product|intval}" data-attribute="{$line.id_product_attribute|intval}">
                <td>
                  {$line.product_name|escape:'html':'UTF-8'}
                  {if $line.product_reference}<br><small class="text-muted">{$line.product_reference|escape:'html':'UTF-8'}</small>{/if}
                  <input type="hidden" name="line_id[]" value="{$line.id|intval}">
                  <input type="hidden" name="line_product[]" value="{$line.id_product|intval}">
                  <input type="hidden" name="line_attribute[]" value="{$line.id_product_attribute|intval}">
                  <input type="hidden" name="line_discount[]" class="js-dqr-discount" value="{$line.discount_percent}">
                </td>
                <td>{if $line.combination_name}{$line.combination_name|escape:'html':'UTF-8'}{else}&mdash;{/if}</td>
                <td class="text-right js-dqr-unit">{$line.unit_price}</td>
                <td><input type="number" name="line_qty[]" class="form-control js-dqr-qty" min="1" step="1" value="{$line.quantity|intval}"></td>
                <td class="text-right js-dqr-total">{$line.total}</td>
                <td><input type="text" class="form-control js-dqr-percent" inputmode="decimal"></td>
                <td><input type="text" class="form-control js-dqr-amount" inputmode="decimal"></td>
                <td class="text-right js-dqr-net">{$line.net_total}</td>
                <td class="text-right"><a href="#" class="btn btn-default btn-sm js-dqr-remove" title="{l s='Rimuovi'}"><i class="icon-trash"></i></a></td>
              </tr>
            {/foreach}
            <tr class="js-dqr-empty"{if $lines|@count} style="display:none"{/if}>
              <td colspan="9" class="text-center text-muted">{l s='Nessun prodotto nel preventivo.'}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="4" class="text-right">{l s='Totale di listino (IVA inclusa)'}</th>
              <th class="text-right" id="dqr-edit-total">{$quote_total}</th>
              <th colspan="4"></th>
            </tr>
            <tr>
              <th colspan="4" class="text-right">{l s='Sconto totale'}</th>
              <th class="text-right" id="dqr-edit-discount">{$lines_discount_total}</th>
              <th colspan="4" class="text-muted" id="dqr-edit-discount-percent"></th>
            </tr>
            <tr class="success">
              <th colspan="4" class="text-right">{l s='Totale da pagare (IVA inclusa)'}</th>
              <th class="text-right" id="dqr-edit-net" style="font-size:1.2em">{$lines_net_total}</th>
              <th colspan="4"></th>
            </tr>
          </tfoot>
        </table>
        <p class="help-block">
          {l s='I prezzi dei prodotti aggiunti sono quelli per questo cliente e vengono ricalcolati al salvataggio. Lo sconto totale diventa lo sconto "Sconto preventivo" applicato al carrello del cliente.'}
          {l s='Massimo'} {$max_lines|intval} {l s='righe.'}
        </p>

        <div class="form-group">
          <label for="admin_note">{l s='Nota (facoltativa, visibile al cliente solo se annulli)'}</label>
          <textarea name="admin_note" id="admin_note" class="form-control" rows="3"></textarea>
        </div>

        <button type="submit" name="submitDqrsave" value="1" class="btn btn-default">
          <i class="icon-save"></i> {l s='Salva modifiche'}
        </button>
        <button type="submit" name="submitDqrapprove" value="1" class="btn btn-success js-dqr-approve">
          {l s='Approva e invia link al cliente'}
        </button>
        <button type="submit" name="submitDqrreject" value="1" class="btn btn-danger js-dqr-cancel" formnovalidate>
          {l s='Annulla preventivo'}
        </button>
      </form>
    {else}
      <table class="table">
        <thead>
          <tr>
            <th>{l s='Prodotto'}</th>
            <th>{l s='Variante'}</th>
            <th>{l s='Riferimento'}</th>
            <th class="text-right">{l s='Prezzo unitario'}</th>
            <th>{l s='Quantità'}</th>
            <th class="text-right">{l s='Totale'}</th>
            {if $has_line_discounts}
              <th class="text-right">{l s='Sconto'}</th>
              <th class="text-right">{l s='Totale scontato'}</th>
            {/if}
          </tr>
        </thead>
        <tbody>
          {foreach from=$lines item=line}
            <tr>
              <td>{$line.product_name|escape:'html':'UTF-8'}</td>
              <td>{if $line.combination_name}{$line.combination_name|escape:'html':'UTF-8'}{else}&mdash;{/if}</td>
              <td>{$line.product_reference|escape:'html':'UTF-8'}</td>
              <td class="text-right">{$line.unit_price}</td>
              <td>{$line.quantity}</td>
              <td class="text-right">{$line.total}</td>
              {if $has_line_discounts}
                <td class="text-right">{$line.discount} <small class="text-muted">({$line.discount_percent_label}%)</small></td>
                <td class="text-right">{$line.net_total}</td>
              {/if}
            </tr>
          {/foreach}
        </tbody>
        <tfoot>
          <tr>
            <th colspan="5" class="text-right">{l s='Totale di listino (IVA inclusa)'}</th>
            <th class="text-right">{$quote_total}</th>
            {if $has_line_discounts}<th class="text-right">{$lines_discount_total}</th><th class="text-right">{$lines_net_total}</th>{/if}
          </tr>
        </tfoot>
      </table>
    {/if}

    {if $can_cancel_sent}
      <hr>
      <form method="post" action="{$current_index}&token={$token}" id="dqr-cancel-sent">
        <input type="hidden" name="id_quote" value="{$quote->id|intval}">
        <div class="form-group">
          <label for="dqr_cancel_note">{l s='Nota per il cliente (facoltativa)'}</label>
          <textarea name="admin_note" id="dqr_cancel_note" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" name="submitDqrreject" value="1" class="btn btn-danger js-dqr-cancel">
          <i class="icon-remove"></i> {l s='Annulla preventivo'}
        </button>
        <p class="help-block">{l s='Il link del cliente smette di funzionare e lo sconto viene disattivato. Il cliente riceve una mail con la nota.'}</p>
      </form>
    {/if}

    {if $can_extend}
      <hr>
      <form method="post" action="{$current_index}&token={$token}" id="dqr-extend" class="form-inline">
        <input type="hidden" name="id_quote" value="{$quote->id}">
        <p>
          <strong>{l s='Scadenza attuale'}:</strong>
          {if $quote->expires_at}{dateFormat date=$quote->expires_at full=1}{else}&mdash;{/if}
        </p>
        <div class="form-group">
          <label for="dqr_expires_at">{l s='Nuova data di scadenza'}</label>
          <input type="date" name="expires_at" id="dqr_expires_at" class="form-control" required="required"
                 min="{$min_expires_at}" value="{$expires_at_value}">
        </div>
        <button type="submit" name="submitDqrextend" value="1" class="btn btn-default">
          <i class="icon-calendar"></i> {l s='Proroga scadenza'}
        </button>
        <p class="help-block">{l s='Valido fino alle 23:59 del giorno scelto. Aggiorna anche la scadenza dello sconto; se il preventivo è scaduto torna "Mandato al cliente".'}</p>
      </form>
    {/if}
  </div>
</div>
