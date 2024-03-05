YUI.add('moodle-availability_othercompleted-form', function (Y, NAME) {

/**
 * JavaScript for form editing completion conditions.
 *
 * @module moodle-availability_othercompleted-form
 */
M.availability_othercompleted = M.availability_othercompleted || {};

/**
 * @class M.availability_othercompleted.form
 * @extends M.core_availability.plugin
 */
M.availability_othercompleted.form = Y.Object(M.core_availability.plugin);

/**
 * Initialises this plugin.
 *
 * @method initInner
 * @param {Array} cms Array of objects containing cmid => name
 */
M.availability_othercompleted.form.initInner = function (prefill) {
    // Save the course name prefills.
    this.prefill = prefill;

    // Used for unique ids, so that multiple instances of the same condition do not collide.
    this.numinstances = 0;
};

/**
 * Returns selector for the autocomplete select element
 * @param {int} i unique id counter
 * @returns string
 */
function get_autocomplete_id(i) {
    return 'availability-othercompleted-autocomplete-select-' + i;
}

/**
 * Returns the class for the completion status dropdown
 * @returns string
 */
function get_completion_status_dropdown_class() {
    return 'availability-othercompleted-dropdown';
}

/**
 * Returns the class for the hidden autocomplete input.
 * @returns string
 */
function get_autocomplete_hidden_input_class() {
    return 'availability-othercompleted-autcomplete-mirror-input';
}

/**
 * Returns the class for the completion status dropdown
 * @param {Object} json json initialised from the frontend.php script
 * @returns YUI node
 */
M.availability_othercompleted.form.getNode = function (json) {
    this.numinstances += 1;
    var autocomplete_id = get_autocomplete_id(this.numinstances);
    var dropdown_class = get_completion_status_dropdown_class();
    var autocomplete_input_class = get_autocomplete_hidden_input_class();

    var html = '';
    html += '<div>';

    // Select element that the form-autocomplete AMD module attaches to.
    html += '<div>';
    html += '<select id="' + autocomplete_id + '"></select>';
    html += '</div>';

    // A hidden input that actually stores the current course value.
    // This is because the autocomplete is loaded async via AMD modules
    // And Yui expects nodes to be ready instantly.
    // We mirror the autocomplete value to this input using event listeners.
    html += '<div>';
    html += '<input style="display: none" class="' + autocomplete_input_class + '"></input>';
    html += '</div>';

    // Dropdown for selecting complete/notcomplete for the course above.
    html += '<div>';
    html += '<select class="custom-select ' + dropdown_class + '">';
    html += '<option value="1">' + M.util.get_string('option_complete', 'availability_othercompleted') + '</option>'; 
    html += '<option value="0">' + M.util.get_string('option_incomplete', 'availability_othercompleted') + '</option>'; 
    html += '</select>';
    html += '</div>';

    html += '</div>';

    var node = Y.Node.create(html);
    var dropdown = node.one('.' + dropdown_class);
    var hiddenselect = node.one('.' + autocomplete_input_class);

    // e = if complete/not complete.
    if (json.e !== undefined) {
        dropdown.set('value', json.e);
    }

    // cm = the course.
    if (json.cm !== undefined) {
        hiddenselect.set('value', json.cm);
    }

    // Add event listeners to update the internal availability JSON value.
    if (!M.availability_othercompleted.form.addedEvents) {
        M.availability_othercompleted.form.addedEvents = true;

        var root = Y.one('.availability-field');

        root.delegate('change', function() {
            M.core_availability.form.update();
        }, '.availability_othercompleted');
        
        root.delegate('input', function() {
            M.core_availability.form.update();
        }, '.availability_othercompleted');
    }

    // Load the autocomplete search via AMD module.
    // Note - this is asynchronous.
    var ctx = this;
    require(['availability_othercompleted/search_form'], function(params) {
        var initialiseSelector = async function() {
            // Wait for this to initialise
            await params.init('#' + autocomplete_id);
            var autocomplete = document.getElementById(autocomplete_id);

            // Prefill initial if exists.
            if (json.cm) {
                // Add an option for the initial value.
                var option = document.createElement("option");
                option.value = json.cm;

                // Get the prefill record, if given.
                option.innerText = ctx.prefill && ctx.prefill[json.cm] ? ctx.prefill[json.cm] : "Unknown course";
                autocomplete.appendChild(option);

                // Set this option as selected.
                autocomplete.value = json.cm;
            }

            // Make any changes to the autocomplete mirror into the hidden input.
            autocomplete.addEventListener('change', async function() {
                var originalValue = hiddenselect.get('value');
                var pollValue = autocomplete.value;

                window.console.log("availability_othercompleted: polling for update from value:" + originalValue + ', current poll: ' + pollValue);

                // For strange reasons, the change event is emitted approximately 1 second BEFORE the input value changes
                // So as a workaround, we poll for 10 seconds, every 100ms for changes.
                // If the value changes in this time, exit early and trigger the update.
                var poll_count = 100;
                await poll(() => {
                    pollValue = autocomplete.value;
                }, 100, () => {
                    poll_count -= 1;
                    return poll_count <= 0 || pollValue != originalValue;
                });

                window.console.log("availability_othercompleted: value changed, or poll timed out, saving " + pollValue);
                hiddenselect.set('value', pollValue);

                // Trigger form update.
                M.core_availability.form.update();
            });

        }
        initialiseSelector();
    })


    return node;
};

/**
 * Updates the value object to include the data from the nodes.
 * @param {Object} value
 * @param {Object} node
 */
M.availability_othercompleted.form.fillValue = function (value, node) {
    // Nothing to read.
    if (node === null) {
        window.console.log("availability_othercompleted: node was null");
        return value;
    }

    var hidden_input = node.one('.' + get_autocomplete_hidden_input_class());
    var status_dropdown = node.one('.' + get_completion_status_dropdown_class());

    if(!hidden_input || !status_dropdown) {
        window.console.log("availability_othercompleted: no input or dropdown");
        return value;
    }

    // cm = course, e = expected.
    value.cm = hidden_input.get('value');
    value.e = status_dropdown.get('value');

    window.console.log("availability_othercompleted: saved " + JSON.stringify(value));
    return value;
};

/**
 * Checks for any errors.
 * @param {Array} errors
 * @param {Object} node
 */
M.availability_othercompleted.form.fillErrors = function (errors, node) {
    // No errors possible.
};

/**
 * Calls a given function and keeps calling it after the specified delay has passed.
 * From https://github.com/kleinfreund/poll/blob/main/src/poll.js
 *
 * @param {() => any} fn The function to call.
 * @param {number | (() => number)} delayOrDelayCallback The delay (in milliseconds) to wait before calling the function again. Can be a function.
 * @param {() => boolean | Promise<boolean>} [shouldStopPolling] A callback function indicating whether to stop polling.
 * @returns {Promise<void>}
 */
async function poll(fn, delayOrDelayCallback, shouldStopPolling = () => false) {
	do {
		await fn()

		if (await shouldStopPolling()) {
			break
		}

		const delay = typeof delayOrDelayCallback === 'number' ? delayOrDelayCallback : delayOrDelayCallback()
		await new Promise((resolve) => setTimeout(resolve, Math.max(0, delay)))
	} while (!await shouldStopPolling())
}


}, '@VERSION@', {"requires": ["base", "node", "event", "moodle-core_availability-form"]});
