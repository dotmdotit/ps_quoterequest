<form method="post" action="{$current_index}&adddotmquoterequest_quote&token={$token}" id="dqr-create-form"
      data-prefill="{$prefill_json|escape:'html':'UTF-8'}">
  <div class="panel">
    <div class="panel-heading">
      <i class="icon-file-text"></i> {l s='Nuovo preventivo'}
    </div>

    <div class="form-group">
      <label class="control-label">{l s='Cliente'}</label>
      <input type="hidden" name="id_customer" id="dqr_id_customer" value="">
      <div id="dqr-customer-selected" class="well well-sm" style="display:none"></div>
      <div id="dqr-customer-search">
        <input type="text" id="dqr_customer_query" class="form-control" autocomplete="off"
               placeholder="{l s='Cerca per nome, cognome, email o ID (min. 2 caratteri)'}">
        <div id="dqr-customer-results" class="list-group" style="margin-top:5px"></div>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label">{l s='Prodotti'}</label>
      <input type="text" id="dqr_product_query" class="form-control" autocomplete="off" disabled="disabled"
             placeholder="{l s='Seleziona prima il cliente, poi cerca per nome o riferimento'}">
      <div id="dqr-product-results" class="list-group" style="margin-top:5px"></div>
    </div>

    <table class="table" id="dqr-lines">
      <thead>
        <tr>
          <th>{l s='Prodotto'}</th>
          <th>{l s='Variante'}</th>
          <th class="text-right">{l s='Prezzo unitario'}</th>
          <th style="width:110px">{l s='Quantità'}</th>
          <th class="text-right">{l s='Totale'}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr class="js-dqr-empty">
          <td colspan="6" class="text-center text-muted">{l s='Nessun prodotto aggiunto.'}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="4" class="text-right">{l s='Totale di listino (IVA inclusa)'}</th>
          <th class="text-right" id="dqr-lines-total">&mdash;</th>
          <th></th>
        </tr>
      </tfoot>
    </table>
    <p class="help-block">
      {l s='Prezzi indicativi per il cliente selezionato: vengono ricalcolati al salvataggio.'}
      {l s='Massimo'} {$max_lines} {l s='righe.'}
    </p>

    <div class="form-group">
      <label for="dqr_customer_message" class="control-label">{l s='Note sulla richiesta (facoltative)'}</label>
      <textarea name="customer_message" id="dqr_customer_message" class="form-control" rows="3">{$posted_customer_message|escape:'html':'UTF-8'}</textarea>
    </div>

    <div class="panel-footer">
      <a href="{$current_index}&token={$token}" class="btn btn-default">
        <i class="process-icon-cancel"></i> {l s='Annulla'}
      </a>
      <button type="submit" name="submitDqrcreate" value="1" class="btn btn-default pull-right">
        <i class="process-icon-save"></i> {l s='Crea preventivo'}
      </button>
    </div>
  </div>
  <p class="help-block">
    {l s='Il preventivo viene creato nello stato "Da gestire": nella pagina successiva puoi applicare lo sconto e approvarlo per inviare il link al cliente.'}
  </p>
</form>
