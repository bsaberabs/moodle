YUI.add('moodle-availability_enroldays-form', function (Y, NAME) {

/**
 * JavaScript for form editing "days after enrolment" conditions.
 *
 * @module moodle-availability_enroldays-form
 */
M.availability_enroldays = M.availability_enroldays || {};

/**
 * @class M.availability_enroldays.form
 * @extends M.core_availability.plugin
 */
M.availability_enroldays.form = Y.Object(M.core_availability.plugin);

M.availability_enroldays.form.getNode = function(json) {
    var html = '<label class="d-inline-flex flex-wrap align-items-center gap-2">' +
            '<span>' + M.util.get_string('label_days', 'availability_enroldays') + '</span>' +
            '<input type="number" name="d" min="0" step="1" class="form-control" style="width: 6em">' +
            '<span>' + M.util.get_string('days_after', 'availability_enroldays') + '</span></label>';
    var node = Y.Node.create('<span class="availability-group">' + html + '</span>');

    if (json.d !== undefined) {
        node.one('input[name=d]').set('value', json.d);
    }

    // Add event handlers (first time only).
    if (!M.availability_enroldays.form.addedEvents) {
        M.availability_enroldays.form.addedEvents = true;
        var root = Y.one('.availability-field');
        root.delegate('valuechange', function() {
            M.core_availability.form.update();
        }, '.availability_enroldays input[name=d]');
    }
    return node;
};

M.availability_enroldays.form.fillValue = function(value, node) {
    var days = node.one('input[name=d]').get('value').trim();
    value.d = /^\d+$/.test(days) ? parseInt(days, 10) : days;
};

M.availability_enroldays.form.fillErrors = function(errors, node) {
    var value = {};
    this.fillValue(value, node);
    if (typeof value.d !== 'number') {
        errors.push('availability_enroldays:error_days');
    }
};

}, '@VERSION@', {"requires": ["base", "node", "event", "event-valuechange", "moodle-core_availability-form"]});
