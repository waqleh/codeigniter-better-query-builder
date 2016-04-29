<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * A base model with a series of CRUD functions (powered by CI's query builder),
 * validation-in-model support, event callbacks and more.
 *
 * @author		Walid Aqleh <waleedakleh23@hotmail.com>
 * @version		1.0.0
 * @based on	        codeigniter-base-model by jamierumbelow (https://github.com/jamierumbelow/codeigniter-base-model)
 * @link https://github.com/waqleh/codeigniter-better-query-builder
 */
class MY_Model extends CI_Model {
    /* --------------------------------------------------------------
     * VARIABLES
     * ------------------------------------------------------------ */

    /**
     * This model's default database table. Automatically
     * guessed by pluralising the model name.
     */
    protected $_table;

    /**
     * The database connection object. Will be set to the default
     * connection. This allows individual models to use different DBs
     * without overwriting CI's global $this->db connection.
     */
    public $_database;

    /**
     * This model's default primary key or unique identifier.
     * Used by the get(), update() and delete() functions.
     */
    protected $primary_key = 'id';
    protected $created_at_key = 'created_at';
    protected $created_by_key = 'created_by';
    protected $updated_at_key = 'updated_at';
    protected $updated_by_key = 'updated_by';
    protected $deleted_at_key = 'deleted_at';
    protected $deleted_by_key = 'deleted_by';
    
    /**
     * User ID key in session
     */
    protected $user_id_session_key = 'User_id';

    /**
     * Support for soft deletes and this model's 'deleted' key
     */
    protected $soft_delete = TRUE;
    protected $soft_delete_key = 'deleted';
    protected $_temporary_with_deleted = FALSE;
    protected $_temporary_only_deleted = FALSE;

    /**
     * The various callbacks available to the model. Each are
     * simple lists of method names (methods will be run on $this).
     */
    protected $before_create = array('created_at');
    protected $after_create = array();
    protected $before_update = array('updated_at');
    protected $after_update = array();
    protected $before_get = array();
    protected $after_get = array();
    protected $before_delete = array();
    protected $after_delete = array();
    protected $callback_parameters = array();

    /**
     * Protected, non-modifiable attributes
     */
    protected $protected_attributes = array();

    /**
     * Relationship arrays. Use flat strings for defaults or string
     * => array to customise the class name and primary key
     */
    protected $belongs_to = array();
    protected $has_many = array();
    protected $_with = array();

    /**
     * An array of validation rules. This needs to be the same format
     * as validation rules passed to the Form_validation library.
     */
    protected $validate = array();

    /**
     * Optionally skip the validation. Used in conjunction with
     * skip_validation() to skip data validation for any future calls.
     */
    protected $skip_validation = FALSE;

    /**
     * By default we return our results as objects. If we need to override
     * this, we can, or, we could use the `as_array()` and `as_object()` scopes.
     */
    protected $return_type = 'object';
    protected $_temporary_return_type = NULL;

    /**
     * Last insert, updated, deleted,.. id
     */
    protected $last_id;

    /* --------------------------------------------------------------
     * GENERIC METHODS
     * ------------------------------------------------------------ */

    /**
     * Initialise the model, tie into the CodeIgniter superobject and
     * try our best to guess the table name.
     */
    public function __construct() {
        parent::__construct();

        $this->load->helper('inflector');

        $this->_fetch_table();

        $this->_database = $this->db;

        array_unshift($this->before_create, 'protect_attributes');
        array_unshift($this->before_update, 'protect_attributes');

        $this->_temporary_return_type = $this->return_type;
    }

    /* --------------------------------------------------------------
     * CRUD INTERFACE
     * ------------------------------------------------------------ */

    /**
     * Fetch a single record based on the primary key. Returns an object.
     */
    public function get($primary_value) {
        return $this->get_by($this->primary_key, $primary_value);
    }

    /**
     * Fetch a single record based on an arbitrary WHERE call. Can be
     * any valid value to $this->_database->where().
     */
    public function get_by() {
        $where = func_get_args();

        if ($this->soft_delete && $this->_temporary_with_deleted !== TRUE) {
            $this->_database->where($this->_table . '.' . $this->soft_delete_key, (bool) $this->_temporary_only_deleted);
        }

        $this->_set_where($where);

        $this->trigger('before_get');

        $row = $this->_database->get($this->_table)
                ->{$this->_return_type()}();
        $this->_temporary_return_type = $this->return_type;

        $row = $this->trigger('after_get', $row);

        $this->_with = array();
        return $row;
    }

    /**
     * Fetch an array of records based on an array of primary values.
     */
    public function get_many($values) {
        $this->_database->where_in($this->primary_key, $values);

        return $this->get_all();
    }

    public function get_global_many($values) {
        $this->_database->where_in($values);

        return $this->get_all();
    }

    /**
     * Fetch an array of records based on an arbitrary WHERE call.
     */
    public function get_many_by() {
        $where = func_get_args();

        $this->_set_where($where);

        return $this->get_all();
    }

    /**
     * Fetch all the records in the table. Can be used as a generic call
     * to $this->_database->get() with scoped methods.
     */
    public function get_all() {
        $this->trigger('before_get');

        if ($this->soft_delete && $this->_temporary_with_deleted !== TRUE) {
            $this->_database->where($this->_table . '.' . $this->soft_delete_key, (bool) $this->_temporary_only_deleted);
        }

        $result = $this->_database->get($this->_table)
                ->{$this->_return_type(1)}();
        $this->_temporary_return_type = $this->return_type;

        foreach ($result as $key => &$row) {
            $row = $this->trigger('after_get', $row, ($key == count($result) - 1));
        }

        $this->_with = array();
        return $result;
    }

    /**
     * Insert a new row into the table. $data should be an associative array
     * of data to be inserted. Returns newly created ID.
     */
    public function insert($data, $skip_validation = FALSE) {
        if ($skip_validation === FALSE) {
            $data = $this->validate($data);
        }

        if ($data !== FALSE) {
            $data = $this->trigger('before_create', $data);

            $this->_database->insert($this->_table, $data);
            $insert_id = $this->_database->insert_id();

            $this->trigger('after_create', $insert_id);

            return $insert_id;
        } else {
            return FALSE;
        }
    }

    /**
     * Insert multiple rows into the table. Returns an array of multiple IDs.
     */
    public function insert_many($data, $skip_validation = FALSE) {
        $ids = array();

        foreach ($data as $key => $row) {
            $ids[] = $this->insert($row, $skip_validation, ($key == count($data) - 1));
        }

        return $ids;
    }

    /**
     * Updated a record based on the primary value.
     */
    public function update($primary_value, $data, $skip_validation = FALSE, $escape = true) {
        $data = $this->trigger('before_update', $data);

        if ($skip_validation === FALSE) {
            $data = $this->validate($data);
        }

        if ($data !== FALSE) {
            $result = $this->_database->where($this->primary_key, $primary_value)
                    ->set($data, '', $escape)
                    ->update($this->_table);

            $this->trigger('after_update', array($data, $result));

            return $result;
        } else {
            return FALSE;
        }
    }

    /**
     * Update many records, based on an array of primary values.
     */
    public function update_many($primary_values, $data, $skip_validation = FALSE) {
        $data = $this->trigger('before_update', $data);

        if ($skip_validation === FALSE) {
            $data = $this->validate($data);
        }

        if ($data !== FALSE) {
            $result = $this->_database->where_in($this->primary_key, $primary_values)
                    ->set($data)
                    ->update($this->_table);

            $this->trigger('after_update', array($data, $result));

            return $result;
        } else {
            return FALSE;
        }
    }

    /**
     * Updated a record based on an arbitrary WHERE clause.
     */
    public function update_by() {
        $args = func_get_args();
        $data = array_pop($args);

        $data = $this->trigger('before_update', $data);

        if ($this->validate($data) !== FALSE) {
            $this->_set_where($args);
            $result = $this->_database->set($data)
                    ->update($this->_table);
            $this->trigger('after_update', array($data, $result));

            return $result;
        } else {
            return FALSE;
        }
    }

    /**
     * Update all records
     */
    public function update_all($data) {
        $data = $this->trigger('before_update', $data);
        $result = $this->_database->set($data)
                ->update($this->_table);
        $this->trigger('after_update', array($data, $result));

        return $result;
    }

    /**
     * Delete a row from the table by the primary value
     */
    public function delete($id) {
        $this->trigger('before_delete', $id);

        $this->_database->where($this->primary_key, $id);

        if ($this->soft_delete) {
            $data = array($this->soft_delete_key => TRUE);
            $data = $this->deleted_at($data);
            $result = $this->_database->update($this->_table, $data);
        } else {
            $result = $this->_database->delete($this->_table);
        }

        $this->last_id = $id;
        $this->trigger('after_delete', $result);

        return $result;
    }

    /**
     * Delete a row from the database table by an arbitrary WHERE clause
     */
    public function delete_by() {
        $where = func_get_args();

        $where = $this->trigger('before_delete', $where);

        $this->_set_where($where);


        if ($this->soft_delete) {
            $data = array($this->soft_delete_key => TRUE);
            $data = $this->deleted_at($data);
            $result = $this->_database->update($this->_table, $data);
        } else {
            $result = $this->_database->delete($this->_table);
        }

        $this->trigger('after_delete', $result);

        return $result;
    }

    /**
     * Delete many rows from the database table by multiple primary values
     */
    public function delete_many($primary_values) {
        $primary_values = $this->trigger('before_delete', $primary_values);

        $this->_database->where_in($this->primary_key, $primary_values);

        if ($this->soft_delete) {
            $result = $this->_database->update($this->_table, array($this->soft_delete_key => TRUE));
        } else {
            $result = $this->_database->delete($this->_table);
        }

        $this->last_id[] = $primary_values;
        $this->trigger('after_delete', $result);

        return $result;
    }

    /**
     * Truncates the table
     */
    public function truncate() {
        $result = $this->_database->truncate($this->_table);

        return $result;
    }

    /* --------------------------------------------------------------
     * RELATIONSHIPS
     * ------------------------------------------------------------ */

    public function with($relationship) {
        $this->_with[] = $relationship;

        if (!in_array('relate', $this->after_get)) {
            $this->after_get[] = 'relate';
        }

        return $this;
    }

    public function relate($row) {
        if (empty($row)) {
            return $row;
        }

        foreach ($this->belongs_to as $key => $value) {
            if (is_string($value)) {
                $relationship = $value;
                $options = array('primary_key' => $value . '_id', 'model' => $value . '_model');
            } else {
                $relationship = $key;
                $options = $value;
            }

            if (in_array($relationship, $this->_with)) {
                $this->load->model($options['model'], $relationship . '_model');

                if (is_object($row)) {
                    $row->{$relationship} = $this->{$relationship . '_model'}->get($row->{$options['primary_key']});
                } else {
                    $row[$relationship] = $this->{$relationship . '_model'}->get($row[$options['primary_key']]);
                }
            }
        }

        foreach ($this->has_many as $key => $value) {
            if (is_string($value)) {
                $relationship = $value;
                $options = array('primary_key' => singular($this->_table) . '_id', 'model' => singular($value) . '_model');
            } else {
                $relationship = $key;
                $options = $value;
            }

            if (in_array($relationship, $this->_with)) {
                $this->load->model($options['model'], $relationship . '_model');

                if (is_object($row)) {
                    $row->{$relationship} = $this->{$relationship . '_model'}->get_many_by($options['primary_key'], $row->{$this->primary_key});
                } else {
                    $row[$relationship] = $this->{$relationship . '_model'}->get_many_by($options['primary_key'], $row[$this->primary_key]);
                }
            }
        }

        return $row;
    }

    /* --------------------------------------------------------------
     * UTILITY METHODS
     * ------------------------------------------------------------ */

    /**
     * Retrieve and generate a form_dropdown friendly array
     */
    function dropdown() {
        $args = func_get_args();

        if (count($args) == 2) {
            list($key, $value) = $args;
        } else {
            $key = $this->primary_key;
            $value = $args[0];
        }

        $this->trigger('before_dropdown', array($key, $value));

        if ($this->soft_delete && $this->_temporary_with_deleted !== TRUE) {
            $this->_database->where($this->soft_delete_key, FALSE);
        }

        $result = $this->_database->select(array($key, $value))
                ->get($this->_table)
                ->result();

        $options = array();

        foreach ($result as $row) {
            $options[$row->{$key}] = $row->{$value};
        }

        $options = $this->trigger('after_dropdown', $options);

        return $options;
    }

    /**
     * Fetch a count of rows based on an arbitrary WHERE call.
     */
    public function count_by() {
        if ($this->soft_delete && $this->_temporary_with_deleted !== TRUE) {
            $this->_database->where($this->_table . '.' . $this->soft_delete_key, (bool) $this->_temporary_only_deleted);
        }

        $where = func_get_args();
        $this->_set_where($where);

        return $this->_database->count_all_results($this->_table);
    }

    /**
     * Fetch a total count of rows. Queries will accept Active Record restrictors such as where(), or_where(), like(), or_like(), etc.
     */
    public function count_all_results() {
        if ($this->soft_delete && $this->_temporary_with_deleted !== TRUE) {
            $this->_database->where($this->_table . '.' . $this->soft_delete_key, (bool) $this->_temporary_only_deleted);
        }
        return $this->_database->count_all_results($this->_table);
    }
    
    /**
     * Fetch a total count of rows, disregarding any previous conditions (SOFT DELETED IS COUNTED)
     */
    public function count_all() {

        return $this->_database->count_all($this->_table);
    }

    /**
     * Tell the class to skip the insert validation
     */
    public function skip_validation() {
        $this->skip_validation = TRUE;
        return $this;
    }

    /**
     * Get the skip validation status
     */
    public function get_skip_validation() {
        return $this->skip_validation;
    }

    /**
     * Return the next auto increment of the table. Only tested on MySQL.
     */
    public function get_next_id() {
        return (int) $this->_database->select('AUTO_INCREMENT')
                        ->from('information_schema.TABLES')
                        ->where('TABLE_NAME', $this->_table)
                        ->where('TABLE_SCHEMA', $this->_database->database)->get()->row()->AUTO_INCREMENT;
    }

    /**
     * Getter for the table name
     */
    public function table() {
        return $this->_table;
    }

    /* --------------------------------------------------------------
     * GLOBAL SCOPES
     * ------------------------------------------------------------ */

    /**
     * Return the next call as an array rather than an object
     */
    public function as_array() {
        $this->_temporary_return_type = 'array';
        return $this;
    }

    /**
     * Return the next call as an object rather than an array
     */
    public function as_object() {
        $this->_temporary_return_type = 'object';
        return $this;
    }

    /**
     * Don't care about soft deleted rows on the next call
     */
    public function with_deleted() {
        $this->_temporary_with_deleted = TRUE;
        return $this;
    }

    /**
     * Only get deleted rows on the next call
     */
    public function only_deleted() {
        $this->_temporary_only_deleted = TRUE;
        return $this;
    }

    /* --------------------------------------------------------------
     * OBSERVERS
     * ------------------------------------------------------------ */

    /**
     * MySQL DATETIME created_at and created_by who: user_id
     */
    public function created_at($row) {
        if (is_object($row)) {
            $row->{$this->created_at_key} = date('Y-m-d H:i:s');
            $row->{$this->created_by_key} = ($this->session->userdata($this->user_id_session_key) ? $this->session->userdata($this->user_id_session_key) : 0);
        } else {
            $row[$this->created_at_key] = date('Y-m-d H:i:s');
            $row[$this->created_by_key] = ($this->session->userdata($this->user_id_session_key) ? $this->session->userdata($this->user_id_session_key) : 0);
        }

        return $row;
    }

    /**
     * MySQL DATETIME updated_at and updated_by who: user_id
     */
    public function updated_at($row) {
        if (is_object($row)) {
            $row->{$this->updated_at_key} = date('Y-m-d H:i:s');
            $row->{$this->updated_by_key} = ($this->session->userdata($this->user_id_session_key) ? $this->session->userdata($this->user_id_session_key) : 0);
        } else {
            $row[$this->updated_at_key] = date('Y-m-d H:i:s');
            $row[$this->updated_by_key] = ($this->session->userdata($this->user_id_session_key) ? $this->session->userdata($this->user_id_session_key) : 0);
        }

        return $row;
    }

    /**
     * MySQL DATETIME deleted_at and deleted_by who: user_id if soft delete is TRUE
     */
    public function deleted_at($row) {
        if (is_object($row)) {
            $row->{$this->deleted_at_key} = date('Y-m-d H:i:s');
            $row->{$this->deleted_by_key} = ($this->session->userdata($this->user_id_session_key) ? $this->session->userdata($this->user_id_session_key) : 0);
        } else {
            $row[$this->deleted_at_key] = date('Y-m-d H:i:s');
            $row[$this->deleted_by_key] = ($this->session->userdata($this->user_id_session_key) ? $this->session->userdata($this->user_id_session_key) : 0);
        }

        return $row;
    }

    /**
     * Serialises data for you automatically, allowing you to pass
     * through objects and let it handle the serialisation in the background
     */
    public function serialize($row) {
        foreach ($this->callback_parameters as $column) {
            if (isset($row[$column])) {
                $row[$column] = serialize($row[$column]);
            }
        }

        return $row;
    }

    public function unserialize($row) {
        foreach ($this->callback_parameters as $column) {
            if (is_array($row) && isset($row[$column])) {
                $row[$column] = unserialize($row[$column]);
            } else if (isset($row->$column)) {
                $row->$column = unserialize($row->$column);
            }
        }

        return $row;
    }

    /**
     * Protect attributes by removing them from $row array
     */
    public function protect_attributes($row) {
        foreach ($this->protected_attributes as $attr) {
            if (is_object($row)) {
                unset($row->$attr);
            } else {
                unset($row[$attr]);
            }
        }

        return $row;
    }

    /* --------------------------------------------------------------
     * QUERY BUILDER DIRECT ACCESS METHODS
     * ------------------------------------------------------------ */

    /**
     * A wrapper to $this->_database->order_by()
     */
    public function order_by($criteria, $order = 'ASC') {
        if (is_array($criteria)) {
            foreach ($criteria as $key => $value) {
                $this->_database->order_by($key, $value);
            }
        } else {
            $this->_database->order_by($criteria, $order);
        }
        return $this;
    }

    /**
     * A wrapper to $this->_database->limit()
     */
    public function limit($limit, $offset = 0) {
        $this->_database->limit($limit, $offset);
        return $this;
    }

    /* --------------------------------------------------------------
     * INTERNAL METHODS
     * ------------------------------------------------------------ */

    /**
     * Trigger an event and call its observers. Pass through the event name
     * (which looks for an instance variable $this->event_name), an array of
     * parameters to pass through and an optional 'last in interation' boolean
     */
    public function trigger($event, $data = FALSE, $last = TRUE) {
        if (isset($this->$event) && is_array($this->$event)) {
            foreach ($this->$event as $method) {
                if (strpos($method, '(')) {
                    preg_match('/([a-zA-Z0-9\_\-]+)(\(([a-zA-Z0-9\_\-\., ]+)\))?/', $method, $matches);

                    $method = $matches[1];
                    $this->callback_parameters = explode(',', $matches[3]);
                }

                $data = call_user_func_array(array($this, $method), array($data, $last));
            }
        }

        return $data;
    }

    /**
     * Run validation on the passed data
     */
    public function validate($data) {
        if ($this->skip_validation) {
            return $data;
        }

        if (!empty($this->validate)) {
            foreach ($data as $key => $val) {
                $_POST[$key] = $val;
            }

            $this->load->library('form_validation');

            if (is_array($this->validate)) {
                $this->form_validation->set_rules($this->validate);

                if ($this->form_validation->run() === TRUE) {
                    return $data;
                } else {
                    return FALSE;
                }
            } else {
                if ($this->form_validation->run($this->validate) === TRUE) {
                    return $data;
                } else {
                    return FALSE;
                }
            }
        } else {
            return $data;
        }
    }

    /**
     * Guess the table name by pluralising the model name
     */
    private function _fetch_table() {
        if ($this->_table == NULL) {
            $this->_table = plural(preg_replace('/(_m|_model)?$/', '', strtolower(get_class($this))));
        }
    }

    /**
     * Guess the primary key for current table
     */
    private function _fetch_primary_key() {
        if ($this->primary_key == NULl) {
            $this->primary_key = $this->_database->query("SHOW KEYS FROM `" . $this->_table . "` WHERE Key_name = 'PRIMARY'")->row()->Column_name;
        }
    }

    /**
     * Set WHERE parameters, cleverly
     */
    protected function _set_where($params) {
        if (count($params) == 1 && is_array($params[0])) {
            foreach ($params[0] as $field => $filter) {
                if (is_array($filter)) {
                    $this->_database->where_in($field, $filter);
                } else {
                    if (is_int($field)) {
                        $this->_database->where($filter);
                    } else {
                        $this->_database->where($field, $filter);
                    }
                }
            }
        } else if (count($params) == 1) {
            $this->_database->where($params[0]);
        } else if (count($params) == 2) {
            if (is_array($params[1])) {
                $this->_database->where_in($params[0], $params[1]);
            } else {
                $this->_database->where($params[0], $params[1]);
            }
        } else if (count($params) == 3) {
            $this->_database->where($params[0], $params[1], $params[2]);
        } else {
            if (is_array($params[1])) {
                $this->_database->where_in($params[0], $params[1]);
            } else {
                $this->_database->where($params[0], $params[1]);
            }
        }
    }

    /**
     * Return the method name for the current return type
     */
    protected function _return_type($multi = FALSE) {
        $method = ($multi) ? 'result' : 'row';
        return $this->_temporary_return_type == 'array' ? $method . '_array' : $method;
    }

    //--------------------------------------------------------------------
    // !CHAINABLE UTILITY METHODS
    //--------------------------------------------------------------------

    /**
     * Sets the where portion of the query in a chainable format.
     *
     * @param mixed  $field The field to search the db on. Can be either a string with the field name to search, or an associative array of key/value pairs.
     * @param string $value The value to match the field against. If $field is an array, this value is ignored.
     *
     * @return BF_Model An instance of this class.
     */
    public function where($field = null, $value = null) {
        if (empty($field)) {
            return $this;
        }

        if (is_string($field)) {
            $this->_database->where($field, $value);
        } elseif (is_array($field)) {
            $this->_database->where($field);
        }

        return $this;
    }

    //--------------------------------------------------------------------
    // CI Database  Wrappers
    //--------------------------------------------------------------------
    // To allow for more expressive syntax, we provide wrapper functions
    // for most of the query builder methods here.
    //
    // This allows for calls such as:
    //      $result = $this->model->select('...')
    //                            ->where('...')
    //                            ->having('...')
    //                            ->get();
    //

    public function select($select = '*', $escape = NULL) {
        $this->_database->select($select, $escape);
        return $this;
    }

    public function select_max($select = '', $alias = '') {
        $this->_database->select_max($select, $alias);
        return $this;
    }

    public function select_min($select = '', $alias = '') {
        $this->_database->select_min($select, $alias);
        return $this;
    }

    public function select_avg($select = '', $alias = '') {
        $this->_database->select_avg($select, $alias);
        return $this;
    }

    public function select_sum($select = '', $alias = '') {
        $this->_database->select_sum($select, $alias);
        return $this;
    }

    public function distinct($val = true) {
        $this->_database->distinct($val);
        return $this;
    }

    public function from($from) {
        $this->_database->from($from);
        return $this;
    }

    public function join($table, $cond, $type = '') {
        $this->_database->join($table, $cond, $type);
        return $this;
    }

    //public function where($key, $value = null, $escape = true) { $this->_database->where($key, $value, $escape); return $this; }
    public function or_where($key, $value = null, $escape = true) {
        if (empty($key)) {
            return $this;
        }
        if (!isset($value)) {
            $this->_database->or_where($key);
        } else {
            $this->_database->or_where($key, $value, $escape);
        }
        return $this;
    }

    public function where_in($key = null, $values = null) {
        $this->_database->where_in($key, $values);
        return $this;
    }

    public function or_where_in($key = null, $values = null) {
        $this->_database->or_where_in($key, $values);
        return $this;
    }

    public function where_not_in($key = null, $values = null) {
        $this->_database->where_not_in($key, $values);
        return $this;
    }

    public function or_where_not_in($key = null, $values = null) {
        $this->_database->or_where_not_in($key, $values);
        return $this;
    }

    public function like($field, $match = '', $side = 'both') {
        $this->_database->like($field, $match, $side);
        return $this;
    }

    public function not_like($field, $match = '', $side = 'both') {
        $this->_database->not_like($field, $match, $side);
        return $this;
    }

    public function or_like($field, $match = '', $side = 'both') {
        $this->_database->or_like($field, $match, $side);
        return $this;
    }

    public function or_not_like($field, $match = '', $side = 'both') {
        $this->_database->or_not_like($field, $match, $side);
        return $this;
    }

    public function group_by($by) {
        $this->_database->group_by($by);
        return $this;
    }

    public function having($key, $value = '', $escape = true) {
        $this->_database->having($key, $value, $escape);
        return $this;
    }

    public function or_having($key, $value = '', $escape = true) {
        $this->_database->or_having($key, $value, $escape);
        return $this;
    }

    public function offset($offset) {
        $this->_database->offset($offset);
        return $this;
    }

    public function set($key, $value = '', $escape = true) {
        $this->_database->set($key, $value, $escape);
        return $this;
    }

    /**
     * try insert ON DUPLICATE KEY UPDATE record based on the primary value.
     */
    public function on_duplicate_key_update($data, $skip_validation = FALSE) {
        if ($skip_validation === FALSE) {
            $data = $this->validate($data);
        }

        if ($data !== FALSE) {
            if (!is_array($data)) {
                $data = json_decode(json_encode($data), true);
                if (!is_array($data)) {
                    return FALSE;
                }
            }
            $sql = $this->_insert_on_duplicate_update_batch($this->_table, array_keys($data));
            $values_array = array_values($data);
            $values = array_merge($values_array, $values_array);
            $this->_database->query($sql, $values);

            $insert_id = $this->_database->insert_id();
            return $insert_id;
        } else {
            return FALSE;
        }
    }

    /**
     * Insert_on_duplicate_update_batch statement
     *
     * Generates a platform-specific insert string from the supplied data
     * MODIFIED to include ON DUPLICATE UPDATE
     *
     * @access private
     * @param string the table name
     * @param array the insert keys
     * @return string
     */
    private function _insert_on_duplicate_update_batch($table, $keys) {
        foreach ($keys as $key) {
            $update_fields[] = $key . "=?";
        }
        return "INSERT INTO " . $table . " (" . implode(', ', $keys) . ", " . $this->created_at_key . ") VALUES (" . preg_replace('/, $/', '', preg_replace("/\?\?/", "?, ?, ", str_repeat("?", sizeof($keys)))) . ", '" . date('Y-m-d H:i:s', time()) . "') ON DUPLICATE KEY UPDATE " . implode(", ", $update_fields);
    }

}
