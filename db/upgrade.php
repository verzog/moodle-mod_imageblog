<?php
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
 * Upgrade steps for mod_imageblog.
 *
 * @package    mod_imageblog
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Apply the mod_imageblog upgrade steps.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool true on success
 */
function xmldb_imageblog_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026100401) {
        $table = new xmldb_table('imageblog');

        $field = new xmldb_field('casedifficulty');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'grade');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('difficultyscale');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '1, 1.5, 2, 3, 5', 'casedifficulty');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('participationfactor');
        $field->set_attributes(XMLDB_TYPE_NUMBER, '10, 4', null, XMLDB_NOTNULL, null, '0.5', 'difficultyscale');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('correctfactor');
        $field->set_attributes(XMLDB_TYPE_NUMBER, '10, 4', null, XMLDB_NOTNULL, null, '1', 'participationfactor');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026100401, 'imageblog');
    }

    if ($oldversion < 2026100402) {
        $table = new xmldb_table('imageblog');

        $field = new xmldb_field('bestfactor');
        $field->set_attributes(XMLDB_TYPE_NUMBER, '10, 4', null, XMLDB_NOTNULL, null, '1', 'correctfactor');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('bestdiagnosisid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'bestfactor');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026100402, 'imageblog');
    }

    if ($oldversion < 2026100701) {
        $table = new xmldb_table('imageblog_questions');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('imageblogid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('question', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('answer', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('answeredby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timeanswered', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_imageblog', XMLDB_KEY_FOREIGN, ['imageblogid'], 'imageblog', ['id']);
            $table->add_index('imageblogid', XMLDB_INDEX_NOTUNIQUE, ['imageblogid']);
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026100701, 'imageblog');
    }

    return true;
}
