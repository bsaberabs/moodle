YUI.add('moodle-availability_forcedaccess-form', function (Y, NAME) {

/**
 * JavaScript for Moodle's "Restrict access" editor: the condition has no setting, it only says where it is managed.
 *
 * @module moodle-availability_forcedaccess-form
 */
M.availability_forcedaccess = M.availability_forcedaccess || {};

/**
 * @class M.availability_forcedaccess.form
 * @extends M.core_availability.plugin
 */
M.availability_forcedaccess.form = Y.Object(M.core_availability.plugin);

M.availability_forcedaccess.form.getNode = function() {
    return Y.Node.create('<span class="availability-group">' +
            M.util.get_string('editor_note', 'availability_forcedaccess') + '</span>');
};

M.availability_forcedaccess.form.fillValue = function() {
    // No setting.
};

}, '@VERSION@', {"requires": ["base", "node", "moodle-core_availability-form"]});
