document.addEventListener('DOMContentLoaded', function () {
    const settings = document.querySelector('.formcourier-notifications-pro-settings');
    if (!settings) {
        return;
    }

    const destinationsContainer = document.getElementById('formcourier-destinations');
    const addDestinationButton = document.getElementById('formcourier-add-destination');
    const defaultDestinationSelect = document.getElementById('formcourier-default-destination');

    function slugify(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9_-]+/g, '-')
            .replace(/^-+|-+$/g, '') || ('destination-' + Date.now());
    }

    function updateDefaultDestinationOptions() {
        if (!destinationsContainer || !defaultDestinationSelect) {
            return;
        }

        const current = defaultDestinationSelect.value;
        const cards = destinationsContainer.querySelectorAll('.formcourier-destination-card');
        defaultDestinationSelect.innerHTML = '';

        cards.forEach(function (card) {
            const id = card.dataset.destinationId || '';
            const nameInput = card.querySelector('.formcourier-destination-name');
            const enabledInput = card.querySelector('.formcourier-destination-enabled');
            if (!id || !nameInput || (enabledInput && !enabledInput.checked)) {
                return;
            }
            const option = document.createElement('option');
            option.value = id;
            option.textContent = nameInput.value || id;
            defaultDestinationSelect.appendChild(option);
        });

        if (Array.from(defaultDestinationSelect.options).some(function (option) { return option.value === current; })) {
            defaultDestinationSelect.value = current;
        }
    }

    if (addDestinationButton && destinationsContainer) {
        addDestinationButton.addEventListener('click', function () {
            let id = slugify('destination-' + Date.now());
            const card = document.createElement('div');
            card.className = 'formcourier-destination-card';
            card.dataset.destinationId = id;
            card.innerHTML = '<div class="formcourier-destination-card__header"><strong>New Destination</strong><button type="button" class="button-link-delete formcourier-remove-destination">Remove</button></div>' +
                '<div class="formcourier-grid formcourier-grid--2">' +
                '<label><span>Name</span><input type="text" class="regular-text formcourier-destination-name" name="formcourier_notifications_pro_settings[destinations][' + id + '][name]" value="New Destination"></label>' +
                '<label><span>Bot Token</span><input type="password" class="regular-text" name="formcourier_notifications_pro_settings[destinations][' + id + '][bot_token]" value="" autocomplete="new-password" placeholder="Enter bot token"></label>' +
                '<label><span>Chat ID</span><input type="text" class="regular-text" name="formcourier_notifications_pro_settings[destinations][' + id + '][chat_id]" value=""></label>' +
                '</div>' +
                '<label class="formcourier-checkbox"><input type="checkbox" class="formcourier-destination-enabled" name="formcourier_notifications_pro_settings[destinations][' + id + '][enabled]" value="1" checked> Enabled</label>';
            destinationsContainer.appendChild(card);
            updateDefaultDestinationOptions();
        });

        destinationsContainer.addEventListener('click', function (event) {
            const button = event.target.closest('.formcourier-remove-destination');
            if (!button) {
                return;
            }
            const card = button.closest('.formcourier-destination-card');
            if (card) {
                card.remove();
                updateDefaultDestinationOptions();
            }
        });

        destinationsContainer.addEventListener('input', function (event) {
            if (event.target.matches('.formcourier-destination-name')) {
                const card = event.target.closest('.formcourier-destination-card');
                const strong = card ? card.querySelector('.formcourier-destination-card__header strong') : null;
                if (strong) {
                    strong.textContent = event.target.value || 'Destination';
                }
                updateDefaultDestinationOptions();
            }
        });

        destinationsContainer.addEventListener('change', function (event) {
            if (event.target.matches('.formcourier-destination-enabled')) {
                updateDefaultDestinationOptions();
            }
        });
    }

    const slackDestinationsContainer = document.getElementById('formcourier-slack-destinations');
    const addSlackDestinationButton = document.getElementById('formcourier-add-slack-destination');
    const defaultSlackDestinationSelect = document.getElementById('formcourier-slack-default-destination');

    function updateDefaultSlackDestinationOptions() {
        if (!slackDestinationsContainer || !defaultSlackDestinationSelect) {
            return;
        }
        const current = defaultSlackDestinationSelect.value;
        const cards = slackDestinationsContainer.querySelectorAll('.formcourier-slack-destination-card');
        defaultSlackDestinationSelect.innerHTML = '';
        cards.forEach(function (card) {
            const id = card.dataset.destinationId || '';
            const nameInput = card.querySelector('.formcourier-slack-destination-name');
            const enabledInput = card.querySelector('.formcourier-slack-destination-enabled');
            if (!id || !nameInput || (enabledInput && !enabledInput.checked)) {
                return;
            }
            const option = document.createElement('option');
            option.value = id;
            option.textContent = nameInput.value || id;
            defaultSlackDestinationSelect.appendChild(option);
        });
        if (Array.from(defaultSlackDestinationSelect.options).some(function (option) { return option.value === current; })) {
            defaultSlackDestinationSelect.value = current;
        }
    }

    if (addSlackDestinationButton && slackDestinationsContainer) {
        addSlackDestinationButton.addEventListener('click', function () {
            const id = slugify('slack-' + Date.now());
            const card = document.createElement('div');
            card.className = 'formcourier-destination-card formcourier-slack-destination-card';
            card.dataset.destinationId = id;
            card.innerHTML = '<div class="formcourier-destination-card__header"><strong>New Slack Destination</strong><button type="button" class="button-link-delete formcourier-remove-slack-destination">Remove</button></div>' +
                '<div class="formcourier-grid formcourier-grid--2">' +
                '<label><span>Name</span><input type="text" class="regular-text formcourier-slack-destination-name" name="formcourier_notifications_pro_settings[slack_destinations][' + id + '][name]" value="New Slack Destination"></label>' +
                '<label><span>Incoming Webhook URL</span><input type="password" class="regular-text" name="formcourier_notifications_pro_settings[slack_destinations][' + id + '][webhook_url]" value="" autocomplete="new-password" placeholder="https://hooks.slack.com/services/..."></label>' +
                '</div>' +
                '<label class="formcourier-checkbox"><input type="checkbox" class="formcourier-slack-destination-enabled" name="formcourier_notifications_pro_settings[slack_destinations][' + id + '][enabled]" value="1" checked> Enabled</label>';
            slackDestinationsContainer.appendChild(card);
            updateDefaultSlackDestinationOptions();
        });

        slackDestinationsContainer.addEventListener('click', function (event) {
            const button = event.target.closest('.formcourier-remove-slack-destination');
            if (!button) {
                return;
            }
            const card = button.closest('.formcourier-slack-destination-card');
            if (card) {
                card.remove();
                updateDefaultSlackDestinationOptions();
            }
        });

        slackDestinationsContainer.addEventListener('input', function (event) {
            if (event.target.matches('.formcourier-slack-destination-name')) {
                const card = event.target.closest('.formcourier-slack-destination-card');
                const strong = card ? card.querySelector('.formcourier-destination-card__header strong') : null;
                if (strong) {
                    strong.textContent = event.target.value || 'Slack Destination';
                }
                updateDefaultSlackDestinationOptions();
            }
        });

        slackDestinationsContainer.addEventListener('change', function (event) {
            if (event.target.matches('.formcourier-slack-destination-enabled')) {
                updateDefaultSlackDestinationOptions();
            }
        });
    }

    const rulesContainer = document.getElementById('formcourier-conditional-rules');
    const addRuleButton = document.getElementById('formcourier-add-rule');
    const ruleTemplate = document.getElementById('formcourier-rule-template');

    if (addRuleButton && rulesContainer && ruleTemplate) {
        addRuleButton.addEventListener('click', function () {
            const index = 'new_' + Date.now();
            let html = ruleTemplate.innerHTML.replace(/__INDEX__/g, index);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            if (wrapper.firstElementChild) {
                rulesContainer.appendChild(wrapper.firstElementChild);
            }
        });

        rulesContainer.addEventListener('click', function (event) {
            const button = event.target.closest('.formcourier-remove-rule');
            if (!button) {
                return;
            }
            const row = button.closest('.formcourier-rule-row');
            if (row) {
                row.remove();
            }
        });

        rulesContainer.addEventListener('change', function (event) {
            const formSelect = event.target.closest('.formcourier-rule-form');
            if (!formSelect) {
                return;
            }
            const row = formSelect.closest('.formcourier-rule-row');
            const fieldSelect = row ? row.querySelector('.formcourier-rule-field') : null;
            if (!fieldSelect) {
                return;
            }
            fieldSelect.innerHTML = '<option value="">Select field</option>';
            let fields = {};
            try {
                fields = JSON.parse(formSelect.options[formSelect.selectedIndex].dataset.fields || '{}');
            } catch (e) {
                fields = {};
            }
            Object.keys(fields).forEach(function (key) {
                const option = document.createElement('option');
                option.value = key;
                option.textContent = fields[key] + ' (' + key + ')';
                fieldSelect.appendChild(option);
            });
        });
    }

    const templateForms = document.querySelectorAll('.formcourier-template-form-select');
    templateForms.forEach(function (select) {
        select.addEventListener('change', function () {
            const target = document.querySelector(select.dataset.target || '');
            if (!target) {
                return;
            }
            document.querySelectorAll('.formcourier-form-template-panel').forEach(function (panel) {
                panel.hidden = true;
            });
            const panel = document.getElementById('formcourier-template-' + select.value.replace(/[^a-zA-Z0-9_-]/g, '-'));
            if (panel) {
                panel.hidden = false;
            }
        });
    });
});
