document.addEventListener('DOMContentLoaded', function () {
    var percentRadio = document.getElementById('dqr_discount_percent');
    var amountRadio = document.getElementById('dqr_discount_amount');
    var valueInput = document.getElementById('discount_value');

    if (!percentRadio || !amountRadio || !valueInput) {
        return;
    }

    function updateMax() {
        if (percentRadio.checked) {
            valueInput.setAttribute('max', '100');
        } else {
            valueInput.removeAttribute('max');
        }
    }

    percentRadio.addEventListener('change', updateMax);
    amountRadio.addEventListener('change', updateMax);
    updateMax();
});

// "Proroga scadenza" row action in the quotes list: asks for the new date in a
// modal and posts it to the admin controller. The form lives outside the list
// form, since forms cannot be nested.
document.addEventListener('DOMContentLoaded', function () {
    if (typeof dqrExtend === 'undefined' || typeof jQuery === 'undefined') {
        return;
    }

    var cfg = dqrExtend;
    var modal = null;

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;

        return div.innerHTML;
    }

    function buildModal() {
        var html =
            '<div class="modal fade" id="dqr-extend-modal" tabindex="-1" role="dialog">' +
            '<div class="modal-dialog" role="document"><div class="modal-content">' +
            '<form method="post" action="' + escapeHtml(cfg.action) + '">' +
            '<input type="hidden" name="id_quote" value="">' +
            '<input type="hidden" name="back" value="list">' +
            '<div class="modal-header">' +
            '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
            '<h4 class="modal-title">' + escapeHtml(cfg.labels.title) + ' <span class="js-dqr-id"></span></h4>' +
            '</div>' +
            '<div class="modal-body"><div class="form-group">' +
            '<label for="dqr_modal_expires_at">' + escapeHtml(cfg.labels.date) + '</label>' +
            '<input type="date" name="expires_at" id="dqr_modal_expires_at" class="form-control" required="required" min="' + escapeHtml(cfg.minDate) + '">' +
            '<p class="help-block">' + escapeHtml(cfg.labels.help) + '</p>' +
            '</div></div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-default" data-dismiss="modal">' + escapeHtml(cfg.labels.cancel) + '</button>' +
            '<button type="submit" name="submitDqrextend" value="1" class="btn btn-primary">' + escapeHtml(cfg.labels.save) + '</button>' +
            '</div>' +
            '</form></div></div></div>';

        modal = jQuery(html).appendTo('body');
    }

    jQuery(document).on('click', '.js-dqr-extend', function (e) {
        e.preventDefault();

        if (!modal) {
            buildModal();
        }

        var id = this.getAttribute('data-id');
        var expires = this.getAttribute('data-expires') || '';

        modal.find('input[name="id_quote"]').val(id);
        modal.find('.js-dqr-id').text('#' + id);
        modal.find('input[name="expires_at"]').val(expires >= cfg.minDate ? expires : '');
        modal.modal('show');
    });
});

// "Nuovo preventivo" form: customer and product search via the admin
// controller's ajax actions, plus the editable lines table.
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('dqr-create-form');
    if (!form || typeof dqrCreate === 'undefined' || typeof jQuery === 'undefined') {
        return;
    }

    var $ = jQuery;
    var cfg = dqrCreate;
    var labels = cfg.labels;
    var $form = $(form);
    var $customerId = $('#dqr_id_customer');
    var $customerSelected = $('#dqr-customer-selected');
    var $customerSearch = $('#dqr-customer-search');
    var $customerQuery = $('#dqr_customer_query');
    var $customerResults = $('#dqr-customer-results');
    var $productQuery = $('#dqr_product_query');
    var $productResults = $('#dqr-product-results');
    var $tbody = $('#dqr-lines tbody');

    var money = new Intl.NumberFormat(document.documentElement.lang || 'it-IT', {
        style: 'currency',
        currency: cfg.currencyIso || 'EUR'
    });

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);

        return div.innerHTML;
    }

    function debounce(fn, wait) {
        var timer = null;

        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    }

    // Only the latest request per search box is rendered, so slow responses
    // never overwrite newer results.
    function search(action, params, $results, render) {
        var requestId = ($results.data('requestId') || 0) + 1;
        $results.data('requestId', requestId);

        if (params.q.length < 2) {
            $results.empty();
            return;
        }

        $results.html('<span class="list-group-item text-muted">' + escapeHtml(labels.searching) + '</span>');

        $.getJSON(cfg.ajaxUrl, $.extend({ action: action }, params)).done(function (data) {
            if ($results.data('requestId') !== requestId) {
                return;
            }
            $results.empty();
            if (!data.results || !data.results.length) {
                $results.html('<span class="list-group-item text-muted">' + escapeHtml(labels.noResults) + '</span>');
                return;
            }
            data.results.forEach(function (item) {
                $results.append(render(item));
            });
        });
    }

    // --- Customer -------------------------------------------------------

    function customerLabel(customer) {
        return escapeHtml(customer.name) + ' &lt;' + escapeHtml(customer.email) + '&gt;'
            + (customer.company ? ' &mdash; ' + escapeHtml(customer.company) : '')
            + ' <span class="text-muted">#' + customer.id + '</span>';
    }

    function selectCustomer(customer) {
        $customerId.val(customer.id);
        $customerSelected
            .html('<i class="icon-user"></i> ' + customerLabel(customer)
                + ' <a href="#" class="js-dqr-change-customer pull-right">' + escapeHtml(labels.change) + '</a>')
            .show();
        $customerSearch.hide();
        $customerResults.empty();
        $productQuery.prop('disabled', false).focus();
    }

    $customerQuery.on('input', debounce(function () {
        search('searchCustomers', { q: $.trim($customerQuery.val()) }, $customerResults, function (customer) {
            return $('<a href="#" class="list-group-item"></a>')
                .html(customerLabel(customer))
                .on('click', function (e) {
                    e.preventDefault();
                    selectCustomer(customer);
                });
        });
    }, 300));

    $customerSelected.on('click', '.js-dqr-change-customer', function (e) {
        e.preventDefault();
        $customerId.val('');
        $customerSelected.hide().empty();
        $customerSearch.show();
        $customerQuery.val('').focus();
        $productQuery.prop('disabled', true);
        $productResults.empty();
    });

    // --- Lines ----------------------------------------------------------

    function lineCount() {
        return $tbody.find('tr.js-dqr-line').length;
    }

    function refreshTotals() {
        var total = 0;

        $tbody.find('tr.js-dqr-line').each(function () {
            var $row = $(this);
            var price = parseFloat($row.find('select.js-dqr-variant option:selected').data('price'));
            if (isNaN(price)) {
                price = parseFloat($row.data('price'));
            }
            var qty = parseInt($row.find('input.js-dqr-qty').val(), 10) || 0;

            $row.find('.js-dqr-unit').text(money.format(price));
            $row.find('.js-dqr-total').text(money.format(price * qty));
            total += price * qty;
        });

        $tbody.find('tr.js-dqr-empty').toggle(lineCount() === 0);
        $('#dqr-lines-total').html(lineCount() ? money.format(total) : '&mdash;');
    }

    function hasLine(idProduct, idProductAttribute) {
        var found = false;
        $tbody.find('tr.js-dqr-line').each(function () {
            var $row = $(this);
            if (String($row.data('product')) === String(idProduct)
                && String($row.find('[name="line_attribute[]"]').val()) === String(idProductAttribute)) {
                found = true;
            }
        });

        return found;
    }

    function addLine(product, idProductAttribute, qty) {
        if (lineCount() >= cfg.maxLines) {
            alert(labels.maxLines);
            return;
        }

        var variantCell;
        if (product.variants.length) {
            if (!idProductAttribute) {
                var preferred = product.variants.filter(function (v) { return v.default; })[0] || product.variants[0];
                idProductAttribute = preferred.id;
            }
            variantCell = '<select name="line_attribute[]" class="form-control js-dqr-variant">'
                + product.variants.map(function (v) {
                    return '<option value="' + v.id + '" data-price="' + v.price + '"'
                        + (String(v.id) === String(idProductAttribute) ? ' selected' : '') + '>'
                        + escapeHtml(v.label) + '</option>';
                }).join('')
                + '</select>';
        } else {
            idProductAttribute = 0;
            variantCell = '&mdash;<input type="hidden" name="line_attribute[]" value="0">';
        }

        if (hasLine(product.id, idProductAttribute)) {
            alert(labels.alreadyAdded);
            return;
        }

        var $row = $('<tr class="js-dqr-line"></tr>')
            .attr('data-product', product.id)
            .attr('data-price', product.price)
            .html(
                '<td>' + escapeHtml(product.name)
                    + (product.reference ? '<br><small class="text-muted">' + escapeHtml(product.reference) + '</small>' : '')
                    + (product.active ? '' : ' <span class="label label-danger">' + escapeHtml(labels.inactive) + '</span>')
                    + '<input type="hidden" name="line_product[]" value="' + product.id + '"></td>'
                + '<td>' + variantCell + '</td>'
                + '<td class="text-right js-dqr-unit"></td>'
                + '<td><input type="number" name="line_qty[]" class="form-control js-dqr-qty" min="1" step="1" value="' + (parseInt(qty, 10) || 1) + '"></td>'
                + '<td class="text-right js-dqr-total"></td>'
                + '<td class="text-right"><a href="#" class="btn btn-default btn-sm js-dqr-remove" title="' + escapeHtml(labels.remove) + '"><i class="icon-trash"></i></a></td>'
            );

        $tbody.append($row);
        refreshTotals();
    }

    $tbody.on('change input', 'select.js-dqr-variant, input.js-dqr-qty', refreshTotals);
    $tbody.on('click', '.js-dqr-remove', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
        refreshTotals();
    });

    $productQuery.on('input', debounce(function () {
        search('searchProducts', { q: $.trim($productQuery.val()), id_customer: $customerId.val() }, $productResults, function (product) {
            var meta = [];
            if (product.reference) {
                meta.push(escapeHtml(product.reference));
            }
            meta.push(money.format(product.price));
            if (product.variants.length) {
                meta.push(product.variants.length + ' varianti');
            }

            return $('<a href="#" class="list-group-item"></a>')
                .html(escapeHtml(product.name)
                    + (product.active ? '' : ' <span class="label label-danger">' + escapeHtml(labels.inactive) + '</span>')
                    + ' <small class="text-muted">' + meta.join(' &middot; ') + '</small>')
                .on('click', function (e) {
                    e.preventDefault();
                    addLine(product, 0, 1);
                    $productResults.empty();
                    $productQuery.val('').focus();
                });
        });
    }, 300));

    // Enter inside a search box must not submit the whole form.
    $customerQuery.add($productQuery).on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
        }
    });

    $form.on('submit', function (e) {
        if (!$customerId.val()) {
            e.preventDefault();
            alert(labels.noCustomer);
            return;
        }
        if (!lineCount()) {
            e.preventDefault();
            alert(labels.noLines);
        }
    });

    // Restore what was posted when the server rejected the form.
    var prefill = null;
    try {
        prefill = JSON.parse(form.getAttribute('data-prefill') || 'null');
    } catch (err) {
        prefill = null;
    }
    if (prefill) {
        if (prefill.customer) {
            selectCustomer(prefill.customer);
        }
        (prefill.lines || []).forEach(function (line) {
            addLine(line.product, line.id_product_attribute, line.qty);
        });
    }
    refreshTotals();
});

// Quote detail page of a pending quote: editable lines (add/remove products, quantities),
// per-line discount in % or €, a total discount spread over the lines, live totals.
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('dqr-edit-form');
    if (!form || typeof dqrEdit === 'undefined' || typeof jQuery === 'undefined') {
        return;
    }

    var $ = jQuery;
    var cfg = dqrEdit;
    var labels = cfg.labels;
    var $tbody = $('#dqr-edit-lines tbody');
    var $productQuery = $('#dqr_edit_product_query');
    var $productResults = $('#dqr-edit-product-results');
    var $globalValue = $('#discount_value');
    var idCustomer = $('#dqr_edit_id_customer').val();

    var money = new Intl.NumberFormat(document.documentElement.lang || 'it-IT', {
        style: 'currency',
        currency: cfg.currencyIso || 'EUR'
    });
    var number = new Intl.NumberFormat(document.documentElement.lang || 'it-IT', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    });

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);

        return div.innerHTML;
    }

    function debounce(fn, wait) {
        var timer = null;

        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    }

    // Accepts "10", "10,5", "1.250,50" and "1250.50"
    function parseNumber(value) {
        var text = String(value == null ? '' : value).replace(/[^\d,.\-]/g, '');
        if (text.indexOf(',') !== -1) {
            text = text.replace(/\./g, '').replace(',', '.');
        }
        var n = parseFloat(text);

        return isNaN(n) ? 0 : n;
    }

    function round2(n) {
        return Math.round(n * 100) / 100;
    }

    function inputNumber(n) {
        return round2(n).toFixed(2).replace('.', ',');
    }

    function lines() {
        return $tbody.find('tr.js-dqr-line');
    }

    function rowPrice($row) {
        var $variant = $row.find('select.js-dqr-variant option:selected');
        var price = $variant.length ? parseFloat($variant.data('price')) : parseFloat($row.data('price'));

        return isNaN(price) ? 0 : price;
    }

    function rowListTotal($row) {
        return rowPrice($row) * (parseInt($row.find('input.js-dqr-qty').val(), 10) || 0);
    }

    function rowPercent($row) {
        return parseFloat($row.find('input.js-dqr-discount').val()) || 0;
    }

    // The hidden percent (4 decimals) is what gets saved; the visible inputs follow it
    function setRowPercent($row, percent, skip) {
        percent = Math.min(100, Math.max(0, percent));
        $row.find('input.js-dqr-discount').val(percent.toFixed(4));
        if (skip !== 'percent') {
            $row.find('input.js-dqr-percent').val(number.format(percent));
        }
        if (skip !== 'amount') {
            $row.find('input.js-dqr-amount').val(inputNumber(rowListTotal($row) * percent / 100));
        }
    }

    function refreshTotals() {
        var total = 0;
        var discount = 0;

        lines().each(function () {
            var $row = $(this);
            var listTotal = rowListTotal($row);
            var lineDiscount = round2(listTotal * rowPercent($row) / 100);

            $row.find('.js-dqr-unit').text(money.format(rowPrice($row)));
            $row.find('.js-dqr-total').text(money.format(listTotal));
            $row.find('.js-dqr-net').text(money.format(listTotal - lineDiscount));
            total += listTotal;
            discount += lineDiscount;
        });

        $tbody.find('tr.js-dqr-empty').toggle(lines().length === 0);
        $('#dqr-edit-total').text(money.format(total));
        $('#dqr-edit-discount').text(money.format(discount));
        $('#dqr-edit-discount-percent').text(total > 0 && discount > 0 ? '(' + number.format(discount / total * 100) + '%)' : '');
        $('#dqr-edit-net').text(money.format(total - discount));
    }

    // Total discount: a percentage goes on every line as is; a fixed amount becomes the same
    // percentage on every line, with the last cent of rounding put on the largest line.
    function applyGlobalDiscount() {
        var value = parseNumber($globalValue.val());
        var isAmount = $('#dqr_discount_amount').is(':checked');
        var $lines = lines();
        if (!$lines.length || $.trim($globalValue.val()) === '') {
            return;
        }

        if (!isAmount) {
            $lines.each(function () { setRowPercent($(this), value); });
        } else {
            var total = 0;
            var $largest = null;
            $lines.each(function () {
                var listTotal = rowListTotal($(this));
                total += listTotal;
                if (!$largest || listTotal > rowListTotal($largest)) {
                    $largest = $(this);
                }
            });
            var amount = Math.min(value, total);
            var percent = total > 0 ? amount / total * 100 : 0;
            var spread = 0;
            $lines.each(function () {
                setRowPercent($(this), percent);
                spread += round2(rowListTotal($(this)) * percent / 100);
            });
            var diff = round2(amount - spread);
            if (diff !== 0 && $largest && rowListTotal($largest) > 0) {
                var largestAmount = round2(rowListTotal($largest) * percent / 100) + diff;
                setRowPercent($largest, largestAmount / rowListTotal($largest) * 100);
            }
        }
        refreshTotals();
    }

    // Percentage a newly added line starts with: the total discount as currently applied
    function currentGlobalPercent() {
        if ($.trim($globalValue.val()) === '') {
            return 0;
        }
        if (!$('#dqr_discount_amount').is(':checked')) {
            return parseNumber($globalValue.val());
        }
        var total = 0;
        var discount = 0;
        lines().each(function () {
            total += rowListTotal($(this));
            discount += rowListTotal($(this)) * rowPercent($(this)) / 100;
        });

        return total > 0 ? discount / total * 100 : 0;
    }

    function hasLine(idProduct, idProductAttribute) {
        var found = false;
        lines().each(function () {
            var $row = $(this);
            var attribute = $row.find('select.js-dqr-variant').length ? $row.find('select.js-dqr-variant').val() : $row.data('attribute');
            if (String($row.data('product')) === String(idProduct) && String(attribute) === String(idProductAttribute)) {
                found = true;
            }
        });

        return found;
    }

    function addLine(product) {
        if (lines().length >= cfg.maxLines) {
            alert(labels.maxLines);
            return;
        }

        var idProductAttribute = 0;
        var variantCell;
        if (product.variants.length) {
            var preferred = product.variants.filter(function (v) { return v.default; })[0] || product.variants[0];
            idProductAttribute = preferred.id;
            variantCell = '<select name="line_attribute[]" class="form-control js-dqr-variant">'
                + product.variants.map(function (v) {
                    return '<option value="' + v.id + '" data-price="' + v.price + '"'
                        + (v.id === idProductAttribute ? ' selected' : '') + '>' + escapeHtml(v.label) + '</option>';
                }).join('')
                + '</select>';
        } else {
            variantCell = '&mdash;<input type="hidden" name="line_attribute[]" value="0">';
        }

        if (hasLine(product.id, idProductAttribute)) {
            alert(labels.alreadyAdded);
            return;
        }

        var percent = currentGlobalPercent();
        var $row = $('<tr class="js-dqr-line info"></tr>')
            .attr('data-product', product.id)
            .attr('data-attribute', idProductAttribute)
            .attr('data-price', product.price)
            .html(
                '<td>' + escapeHtml(product.name)
                    + ' <span class="label label-info">' + escapeHtml(labels.new) + '</span>'
                    + (product.active ? '' : ' <span class="label label-danger">' + escapeHtml(labels.inactive) + '</span>')
                    + (product.reference ? '<br><small class="text-muted">' + escapeHtml(product.reference) + '</small>' : '')
                    + '<input type="hidden" name="line_id[]" value="0">'
                    + '<input type="hidden" name="line_product[]" value="' + product.id + '">'
                    + '<input type="hidden" name="line_discount[]" class="js-dqr-discount" value="0">'
                    + '</td>'
                + '<td>' + variantCell + '</td>'
                + '<td class="text-right js-dqr-unit"></td>'
                + '<td><input type="number" name="line_qty[]" class="form-control js-dqr-qty" min="1" step="1" value="1"></td>'
                + '<td class="text-right js-dqr-total"></td>'
                + '<td><input type="text" class="form-control js-dqr-percent" inputmode="decimal"></td>'
                + '<td><input type="text" class="form-control js-dqr-amount" inputmode="decimal"></td>'
                + '<td class="text-right js-dqr-net"></td>'
                + '<td class="text-right"><a href="#" class="btn btn-default btn-sm js-dqr-remove" title="' + escapeHtml(labels.remove) + '"><i class="icon-trash"></i></a></td>'
            );

        $tbody.find('tr.js-dqr-empty').before($row);
        setRowPercent($row, percent);
        refreshTotals();
    }

    // --- Events ---------------------------------------------------------

    $tbody.on('input change', 'input.js-dqr-qty, select.js-dqr-variant', function () {
        var $row = $(this).closest('tr');
        setRowPercent($row, rowPercent($row)); // same %, new € amount
        refreshTotals();
    });

    $tbody.on('input', 'input.js-dqr-percent', function () {
        setRowPercent($(this).closest('tr'), parseNumber(this.value), 'percent');
        refreshTotals();
    });

    $tbody.on('input', 'input.js-dqr-amount', function () {
        var $row = $(this).closest('tr');
        var listTotal = rowListTotal($row);
        var amount = Math.min(parseNumber(this.value), listTotal);
        setRowPercent($row, listTotal > 0 ? amount / listTotal * 100 : 0, 'amount');
        refreshTotals();
    });

    // Tidy the typed value once the field is left
    $tbody.on('blur', 'input.js-dqr-percent, input.js-dqr-amount', function () {
        var $row = $(this).closest('tr');
        setRowPercent($row, rowPercent($row));
    });

    $tbody.on('click', '.js-dqr-remove', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
        refreshTotals();
    });

    $globalValue.on('input', debounce(applyGlobalDiscount, 250));
    $('#dqr_discount_percent, #dqr_discount_amount').on('change', applyGlobalDiscount);

    $productQuery.on('input', debounce(function () {
        var query = $.trim($productQuery.val());
        var requestId = ($productResults.data('requestId') || 0) + 1;
        $productResults.data('requestId', requestId);

        if (query.length < 2) {
            $productResults.empty();
            return;
        }
        $productResults.html('<span class="list-group-item text-muted">' + escapeHtml(labels.searching) + '</span>');

        $.getJSON(cfg.ajaxUrl, { action: 'searchProducts', q: query, id_customer: idCustomer }).done(function (data) {
            if ($productResults.data('requestId') !== requestId) {
                return;
            }
            $productResults.empty();
            if (!data.results || !data.results.length) {
                $productResults.html('<span class="list-group-item text-muted">' + escapeHtml(labels.noResults) + '</span>');
                return;
            }
            data.results.forEach(function (product) {
                var meta = [];
                if (product.reference) {
                    meta.push(escapeHtml(product.reference));
                }
                meta.push(money.format(product.price));
                if (product.variants.length) {
                    meta.push(product.variants.length + ' ' + escapeHtml(labels.variants));
                }
                $('<a href="#" class="list-group-item"></a>')
                    .html(escapeHtml(product.name)
                        + (product.active ? '' : ' <span class="label label-danger">' + escapeHtml(labels.inactive) + '</span>')
                        + ' <small class="text-muted">' + meta.join(' &middot; ') + '</small>')
                    .on('click', function (e) {
                        e.preventDefault();
                        addLine(product);
                        $productResults.empty();
                        $productQuery.val('').focus();
                    })
                    .appendTo($productResults);
            });
        });
    }, 300));

    // Enter inside the search or discount fields must not submit the form
    $(form).on('keydown', 'input[type="text"]', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
        }
    });

    $(form).on('submit', function (e) {
        var submitter = e.originalEvent && e.originalEvent.submitter;
        if (submitter && submitter.name === 'submitDqrreject') {
            return;
        }
        if (!lines().length) {
            e.preventDefault();
            alert(labels.noLines);
            return;
        }
        if (submitter && submitter.name === 'submitDqrapprove') {
            var discount = 0;
            lines().each(function () {
                discount += round2(rowListTotal($(this)) * rowPercent($(this)) / 100);
            });
            if (discount <= 0) {
                e.preventDefault();
                alert(labels.noDiscount);
            }
        }
    });

    lines().each(function () { setRowPercent($(this), rowPercent($(this))); });
    refreshTotals();
});

// "Annulla preventivo" (pending and sent quotes): asks for confirmation first
document.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('.js-dqr-cancel') : null;
    if (!button || typeof dqrEdit === 'undefined') {
        return;
    }
    if (!window.confirm(dqrEdit.labels.cancelConfirm)) {
        event.preventDefault();
    }
});
