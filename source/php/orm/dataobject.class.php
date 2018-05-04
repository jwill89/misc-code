<?php

/**
 * Created by PhpStorm.
 * User: jwill
 * Date: 10/17/2017
 * Time: 10:21 AM
 */

namespace System;
use Exceptions\QueryException, Exceptions\ObjectException;
use \stdClass, \PDO;

/**
 * Class DataObject
 * The basic class for all objects that are stored in the database.
 * @package System
 */
abstract class DataObject
{

    // Abstract Methods Must be implemeneted by Child Classes

    abstract protected static function getPrimaryKeyName();

    abstract protected function getPrimaryKeyValue();

    abstract protected static function getTableName();

    abstract public static function filterPost();

    /**
     * DatabaseObject constructor. Takes array of properties and sets matching keys.
     * @param array|null $arr
     */
    function __construct(array $arr = null)
    {
        // Is the Array Not Empty?
        if (!empty($arr) && is_array($arr)) {

            // Loop Through Array
            foreach ($arr as $key => $value) {

                // Does the Key Match a Property of the Class?
                if (property_exists(get_class($this), $key)) {

                    // Is the Value Set?
                    if (isset($value) && $value != "" && !is_array($value)) {

                        // Set the Property Value
                        $this->$key = $value;
                    }
                }
            }
        }
    }

    /**
     * Gets all properties of the object except the primary key and returns an array.
     * @return array
     */
    protected function getProperties(): array
    {

        // Return Array without Primary Key Property
        $class_props = get_class_vars(get_class($this));
        $properties = array_filter(get_object_vars($this), function ($key) use ($class_props) {
            return array_key_exists($key, $class_props);
        }, ARRAY_FILTER_USE_KEY);
        unset($properties[static::getPrimaryKeyName()]);

        return $properties;
    }

    /**
     * Returns a true/false if an object exists with the requested primary key value.
     * @param int $primary_key_value
     * @return bool
     * @throws \Exception
     */
    final public static function exists(int $primary_key_value): bool
    {
        // Using the DB
        $db = DB::getInstance();

        // Query the database
        $sql = "SELECT 1 FROM " . static::getTableName() . " WHERE " . static::getPrimaryKeyName() . " = $primary_key_value LIMIT 1";
        $stmt = $db->query($sql);

        // If we got false, then there was a big fat error.
        if ($stmt === false) {
            throw new QueryException("Error: The 'item exists' query failed in the DataObject class.");
        }

        // If we got a result, if there are no rows, then it doesn't exist, or it does.
        if ($stmt->rowCount() > 0)
            return true;
        else {
            return false;
        }

    }

    /**
     * Takes an object and adds it to the database.
     * @return stdClass
     */
    final public function add()
    {

        // DB Connection
        $db = DB::getInstance();

        // Set the Return Value object
        $return_value = new stdClass;

        // Set default empty arrays
        $field_array = array();
        $param_array = array();

        // If property is not empty, then set to add it.
        foreach (static::getProperties() as $key => $value) {
            if (isset($value)) {
                $field_array[] = $key;
                $param_array[] = $value;
            }
        }

        // Start the Transaction so we can rollback on error.
        $db->beginTransaction();

        try {

            // Setup the Query
            $sql = "INSERT INTO " . static::getTableName() . "  (" . implode(",", $field_array) . ") VALUES (" . implode(",", array_fill(0, count($field_array), "?")) . ")";
            $stmt = $db->prepare($sql);

            if ($stmt->execute($param_array)) {

                // Set the Last Inserted ID
                $return_value->last_id = $db->lastInsertId();

                // Execute the add hook if main was successful.
                $this->addHook($db, $return_value->last_id);

                // It Was Successful!
                $return_value->success = true;

                // Commit the Changes
                $db->commit();

            } else {

                // Throw Exception
                throw new QueryException(implode(", ", $stmt->errorInfo()));

            }

        } catch (QueryException $e) {

            // Set failed flag and error information.
            $return_value->success = false;
            $return_value->errorInfo = $e->getMessage();

            // Roll back the changes
            $db->rollBack();

        }

        // Return the info
        return $return_value;

    }

    /**
     * Updates an object already in the database.
     * @return stdClass
     */
    final public function update()
    {

        // Use GLOBAL to access DB
        $db = DB::getInstance();

        // Set the Return Value object
        $return_value = new stdClass;

        // Set default empty arrays
        $field_array = array();
        $param_array = array();

        // If property is not empty, then set to update it.
        foreach (static::getProperties() as $key => $value) {
            if (isset($value)) {
                $field_array[] = $key . " = ?";
                $param_array[] = $value;
            }
        }

        // Start the Transaction so we can rollback on error.
        $db->beginTransaction();

        try {

            // Setup the Query
            $sql = "UPDATE " . static::getTableName() . " SET " . implode(",", $field_array) . " WHERE " . static::getPrimaryKeyName() . " = " . $this->getPrimaryKeyValue();
            $stmt = $db->prepare($sql);

            if ($stmt->execute($param_array)) {

                // If main was successful, run update hook
                $this->updateHook($db);

                // Success!
                $return_value->success = true;

                // Commit the Changes
                $db->commit();

            } else {

                // Throw Exception
                throw new QueryException(implode(", ", $stmt->errorInfo()));

            }

        } catch (QueryException $e) {

            // Set failed flag and error information.
            $return_value->success = false;
            $return_value->errorInfo = $e->getMessage();

            // Roll back the changes
            $db->rollBack();

        }

        // Return the info
        return $return_value;

    }

    /**
     * Deleted an existing object from the datase.
     * @return stdClass
     */
    final public function delete()
    {

        // Use GLOBAL to access DB
        $db = DB::getInstance();

        // Set the Return Value object
        $return_value = new stdClass;

        // Start the Transaction so we can rollback on error.
        $db->beginTransaction();

        try {

            // Setup the Query
            $sql = "DELETE FROM " . static::getTableName() . " WHERE " . static::getPrimaryKeyName() . " = " . $this->getPrimaryKeyValue();

            if ($db->exec($sql)) {

                // If successful, run the delete hook.
                $this->deleteHook($db);

                // It Was Successful!
                $return_value->success = true;

                // Commit the Changes
                $db->commit();

            } else {

                // Error, throw a query exception.
                throw new QueryException(implode(", ", $db->errorInfo()));

            }

        } catch (QueryException $e) {

            // Set fail value and error message.
            $return_value->success = false;
            $return_value->errorInfo = $e->getMessage();

            // Roll back the changes
            $db->rollBack();

        }

        // Return the info
        return $return_value;

    }

    /**
     * Hook for add method. Child overwrite required.
     * @param PDO $db
     * @param mixed $new_id
     */
    protected function addHook(PDO $db, $new_id = null) { }

    /**
     * Hook for update method. Child overwrite required.
     * @param PDO $db
     */
    protected function updateHook(PDO $db) { }

    /**
     * Hook for delete method. Child overwrite required.
     * @param PDO $db
     */
    protected function deleteHook(PDO $db) { }

    /**
     * Gets a single instance of a class object.
     * @param $primary_key_value
     * @return bool|mixed
     */
    final public static function getSingle($primary_key_value)
    {
        // DB Connection
        $db = DB::getInstance();

        // Set Class Name
        $class_name = get_called_class();

        $sql = "SELECT * FROM " . static::getTableName() . " WHERE " . static::getPrimaryKeyName() . " = $primary_key_value";

        $result = $db->query($sql, PDO::FETCH_CLASS, $class_name);

        if ($result) {
            if ($result->rowCount() == 1) {
                return $result->fetch();
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Begins the query to find objects of the type of the calling class.
     * @return mixed
     */
    final public static function find()
    {
        $class_name = get_called_class();

        $obj = new $class_name();
        $obj->where_array = array();
        $obj->order_array = array();

        return $obj;
    }

    /**
     * Adds to the "WHERE" clause of the query to find objects.
     * @param string $property
     * @param string $operator
     * @param mixed  $value
     * @param null   $value_2
     * @param string $type
     * @param bool   $group_start_end
     * @return $this
     * @throws \Exception
     */
    final public function where(string $property, string $operator, $value, $value_2 = null, string $type = "AND", bool $group_start_end = false)
    {
        $class_name = get_class($this);
        $operator = strtoupper($operator);
        $type = strtoupper($type);
        $allowed_operators = ['=', '!=', '>=', '<=', '>', '<', '<>', 'BETWEEN', 'NOT BETWEEN', 'IN', 'NOT IN', 'LIKE', 'NOT LIKE', '~', '&', '|', '^'];

        // Ensure property is valid for class.
        if (!array_key_exists($property, get_class_vars($class_name))) {
            throw new ObjectException("Property '" . $property . "' was not found in class '" . $class_name . "'. (WHERE)");
        }

        // Ensure operator is allowed and safe.
        if (!in_array($operator, $allowed_operators)) {
            throw new QueryException("Operator '" . $operator . "' is not an allowed operator.");
        }

        // Ensure that BETWEEN and NOT BETWEEN have both values.
        if ($operator == "BETWEEN" || $operator == "NOT BETWEEN") {
            if (!isset($value_2)) {
                throw new QueryException($operator . " requires 2 values; only one was passed.");
            }
        }

        // Ensure that type is either AND/OR.
        if (!in_array($type,["AND","OR"])) {
            if (!isset($value_2)) {
                throw new QueryException($operator . " type can only be 'AND' or 'OR'.");
            }
        }

        // Add array of arguments to the where array.
        $this->where_array[] = [$property, $operator, $value, $value_2, $type, $group_start_end];

        // Return the instance to allow for fluent interfacing.
        return $this;

    }

    /**
     * Adds to the "ORDER BY" clause of the query to find objects.
     * @param string $property
     * @param string $order
     * @param string $function
     * @return $this
     * @throws \Exception
     */
    final public function order(string $property, string $order = "DESC", string $function = null)
    {
        $class_name = get_class($this);
        $order = strtoupper($order);
        $function = (isset($function)) ? strtoupper($function) : null;

        // Ensure property is valid for class.
        if (!array_key_exists($property, get_class_vars($class_name))) {
            throw new ObjectException("Property '" . $property . "' was not found in class '" . $class_name . "'. (ORDER)");
        }

        // Ensure order is allowed and safe.
        if (!in_array($order, ["ASC", "DESC"])) {
            throw new QueryException("Order '" . $order . "' is not an allowed order.");
        }

        // Ensure function is allowed and safe.
        if (isset($function) && !in_array($function, ["COUNT", "LENGTH", "TRIM"])) {
            throw new QueryException("Function '" . $function . "' is not an allowed order-level function.");
        }

        // Add array of arguments to the order array.
        $this->order_array[] = [$property, $order, $function];

        // Return the instance to allow for fluent interfacing.
        return $this;

    }

    /**
     * Finalizes the query and gets objects that match. Returns single objects, array of objects, or false for no objects.
     * @return array|bool
     * @throws \Exception
     */
    final public function get()
    {

        // DB Connection
        $db = DB::getInstance();

        $class_name = get_class($this);

        $where_clause = "";
        $order_clause = "";

        // If there are where clauses to be added, generate the clause.
        if (!empty($this->where_array)) {

            // Initialize
            $where_clause = "WHERE ";
            $group_open = false;

            foreach ($this->where_array as $entry) {

                // Set Variables
                $property = $entry[0];
                $operator = $entry[1];
                $value_1 = $entry[2];
                $value_2 = $entry[3];
                $type = $entry[4];
                $group_start_end = $entry[5];

                // If this is not the first clause, add the type.
                if ($where_clause !== "WHERE ") {
                    $where_clause .= "{$type} ";
                }

                // Are we starting the group?
                if ($group_start_end && !$group_open) {
                    $where_clause .= "( ";
                }

                // Surround values with single quotes to ensure strings work. MySQL implictly converts to other formats.
                if ($operator != "IN" && $operator != "NOT IN") {
                    $value_1 = "'" . $value_1 . "'";
                }
                if (isset($value_2)) {
                    $value_2 = "'" . $value_2 . "'";
                }

                // Add to the clause
                $where_clause .= "{$property} {$operator} {$value_1} ";

                // If this is a BETWEEN clause, we need to do AND and then add the second value.
                if ($operator == "BETWEEN" || $operator == "NOT BETWEEN") {
                    $where_clause .= "AND {$value_2} ";
                }

                // Are we closing a group?
                if ($group_start_end && $group_open) {
                    $where_clause .= ") ";
                }

                // Did we Open/Close a group?
                if ($group_start_end) {
                    $group_open = !$group_open;
                }

            }

        }

        // If there are order clauses to be added, generate the clause.
        if (!empty($this->order_array)) {

            // Initialize
            $order_clause = "ORDER BY ";
            $total = 0;

            foreach ($this->order_array as $entry) {

                // Set the Properties
                $property = $entry[0];
                $order = $entry[1];
                $function = $entry[2];

                // If this is an additional clause, add a comma
                if ($total > 0) {
                    $order_clause .= ", ";
                }

                // Add to the Order Clause
                if (isset($function)) {
                    $order_clause .= "{$function}({$property}) {$order}";
                } else {
                    $order_clause .= "{$property} {$order}";
                }
                $total++;

            }
        }

        // Setup the query to the DB and get the info of the item we are going to edit
        $sql = "SELECT * FROM " . static::getTableName() . " " . $where_clause . $order_clause;

        // Display SQl for Debug
        //echo $sql;

        $result = $db->query($sql, PDO::FETCH_CLASS, $class_name);

        // Determine if any results were had.
        if ($result) {
            if ($result->rowCount() === 1) {
                $the_return = $result->fetch();
            } else if ($result->rowCount() > 1) {
                $the_return = $result->fetchAll();
            } else {
                return false;
            }

            // If the return is a user class, we need to remove the password
            if (property_exists($class_name, 'cleared_properties')) {
                // If we have one object, unset password. if array, unset all passwords.
                if (!is_array($the_return)) {
                    foreach ($class_name::$cleared_properties as $property) {
                        unset($the_return->$property);
                    }
                } else {
                    foreach ($the_return as &$user) {
                        foreach ($class_name::$cleaered_properties as $property) {
                            unset($user->$property);
                        }
                    }
                }
            }

            // Did we request to return an array?
            if (!is_array($the_return)) {
                $the_return = [$the_return];
            }

            return $the_return;

        } else {
            throw new QueryException("Error Code " . $result->errorCode() . ": " . $result->errorInfo() . ".");
        }

    }

}
