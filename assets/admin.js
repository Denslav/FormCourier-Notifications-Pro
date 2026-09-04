document.addEventListener('DOMContentLoaded', function () {
    let container = document.getElementById('fcnp-destinations');
    let addButton = document.getElementById('fcnp-add-destination');
    let defaultSelect = document.getElementById('fcnp-default-destination');
    if (!container || !addButton) { return; }

    let refreshDefaultOptions = function () {
        if (!defaultSelect) { return; }
        let selected = defaultSelect.value;
        defaultSelect.innerHTML = '';
        container.querySelectorAll('.fct-destination').forEach(function (card) {
            let id = card.dataset.id;
            let nameInput = card.querySelector('.fcnp-destination-name');
            let option = document.createElement('option');
            option.value = id;
            option.textContent = nameInput && nameInput.value ? nameInput.value : id;
            option.selected = id === selected;
            defaultSelect.appendChild(option);
        });
    };

    addButton.addEventListener('click', function () {
        let id = 'destination-' + Date.now();
        let prefix = 'formcourier_notifications_pro_settings[destinations][' + id + ']';
        let card = document.createElement('div');
        card.className = 'fct-destination';
        card.dataset.id = id;
        card.innerHTML = '<div class="fct-card-heading"><h3>New Destination</h3><button type="button" class="button-link-delete fcnp-remove-destination">Remove</button></div>' +
            '<div class="fct-destination-grid">' +
            '<p><label>Name<br><input class="regular-text fcnp-destination-name" type="text" name="' + prefix + '[name]" value="New Destination"></label></p>' +
            '<p><label>Bot Token<br><input class="regular-text" type="password" autocomplete="new-password" name="' + prefix + '[bot_token]" value=""></label></p>' +
            '<p><label>Chat ID<br><input class="regular-text" type="text" name="' + prefix + '[chat_id]" value=""></label></p>' +
            '<p><label><input type="checkbox" name="' + prefix + '[enabled]" value="1" checked> Enabled</label></p>' +
            '</div>';
        container.appendChild(card);
        refreshDefaultOptions();
    });

    container.addEventListener('click', function (event) {
        if (!event.target.classList.contains('fcnp-remove-destination')) { return; }
        let cards = container.querySelectorAll('.fct-destination');
        if (cards.length <= 1) { return; }
        event.target.closest('.fct-destination').remove();
        refreshDefaultOptions();
    });

    container.addEventListener('input', function (event) {
        if (event.target.classList.contains('fcnp-destination-name')) {
            let card = event.target.closest('.fct-destination');
            let heading = card.querySelector('h3');
            heading.textContent = event.target.value || card.dataset.id;
            refreshDefaultOptions();
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    let rules = document.getElementById('fcnp-rules');
    let addRule = document.getElementById('fcnp-add-rule');
    let template = document.getElementById('fcnp-rule-template');
    if (!rules || !addRule || !template) { return; }

    let updateValueVisibility = function (row) {
        let operator = row.querySelector('.fcnp-rule-operator');
        let valueWrap = row.querySelector('.fcnp-rule-value-wrap');
        if (!operator || !valueWrap) { return; }
        let hide = operator.value === 'is_empty' || operator.value === 'is_not_empty';
        valueWrap.style.display = hide ? 'none' : '';
    };

    let updateFieldOptions = function (row, preserveSelected) {
        let formSelect = row.querySelector('.fcnp-rule-form');
        let fieldSelect = row.querySelector('.fcnp-rule-field');
        if (!formSelect || !fieldSelect) { return; }

        let data = window.FormCourierNotificationsProAdmin || {};
        let fieldMap = data.formFields || {};
        let fields = fieldMap[formSelect.value] || {};
        let selected = preserveSelected ? (fieldSelect.value || fieldSelect.dataset.selected || '') : '';

        fieldSelect.innerHTML = '';
        let empty = document.createElement('option');
        empty.value = '';
        empty.textContent = data.fieldPlaceholder || 'Select a field';
        fieldSelect.appendChild(empty);

        Object.keys(fields).forEach(function (key) {
            let option = document.createElement('option');
            option.value = key;
            option.textContent = fields[key] + ' (' + key + ')';
            if (key === selected) { option.selected = true; }
            fieldSelect.appendChild(option);
        });

        if (selected && !Object.prototype.hasOwnProperty.call(fields, selected)) {
            let custom = document.createElement('option');
            custom.value = selected;
            custom.textContent = selected + ' - ' + (data.customFieldLabel || 'Custom / previously saved field');
            custom.selected = true;
            fieldSelect.appendChild(custom);
        }

        fieldSelect.dataset.selected = fieldSelect.value;
    };

    rules.querySelectorAll('.fct-rule-row').forEach(function (row) {
        updateValueVisibility(row);
        updateFieldOptions(row, true);
    });

    addRule.addEventListener('click', function () {
        let index = Date.now().toString();
        let html = template.innerHTML.replaceAll('__INDEX__', index);
        let wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        let row = wrapper.firstElementChild;
        if (!row) { return; }
        rules.appendChild(row);
        updateValueVisibility(row);
        updateFieldOptions(row, false);
    });

    rules.addEventListener('click', function (event) {
        if (!event.target.classList.contains('fcnp-remove-rule')) { return; }
        let row = event.target.closest('.fct-rule-row');
        if (row) { row.remove(); }
    });

    rules.addEventListener('change', function (event) {
        let row = event.target.closest('.fct-rule-row');
        if (!row) { return; }
        if (event.target.classList.contains('fcnp-rule-operator')) {
            updateValueVisibility(row);
        }
        if (event.target.classList.contains('fcnp-rule-form')) {
            updateFieldOptions(row, false);
        }
        if (event.target.classList.contains('fcnp-rule-field')) {
            event.target.dataset.selected = event.target.value;
        }
    });
});


document.addEventListener('DOMContentLoaded', function () {
    let toggles = document.querySelectorAll('.fcnp-template-enabled');
    if (!toggles.length) { return; }

    toggles.forEach(function (toggle) {
        let update = function () {
            let card = toggle.closest('.fct-form-template-card');
            if (!card) { return; }
            let body = card.querySelector('.fct-form-template-body');
            if (!body) { return; }
            body.hidden = !toggle.checked;
            card.classList.toggle('is-enabled', toggle.checked);
        };

        toggle.addEventListener('change', update);
        update();
    });
});
