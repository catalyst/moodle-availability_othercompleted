// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Frameworks datasource.
 *
 * This module is compatible with core/form-autocomplete.
 *
 * @package    availability_othercompleted
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    return /** @alias module:availability_othercompleted/search_form_datasource */ {

        /**
         * List groupings.
         *
         * @param {Object} options Additional parameters to pass to the external function.
         * @return {Promise}
         */
        list: function(options) {
            return Ajax.call([{
                methodname: 'availability_othercompleted_list_potential_courses',
                args: options
            }])[0];
        },

        /**
         * Process the results for auto complete elements.
         *
         * @param {String} selector The selector of the auto complete element.
         * @param {Array} results An array or results.
         * @return {Array} New array of results.
         */
        processResults: function(selector, results) {
            return results.map((data) => ({
                value: data.id,
                label: data.fullname
            }));
        },

        /**
         * Source of data for Ajax element.
         *
         * @param {String} selector The selector of the auto complete element.
         * @param {String} query The query string.
         * @param {Function} callback A callback function receiving an array of results.
         */
        /* eslint-disable promise/no-callback-in-promise */
        transport: function(selector, query, callback) {
            this.list({
                query: query,
            }).then(callback).catch(Notification.exception);
        }
    };

});

