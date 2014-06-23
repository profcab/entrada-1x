<?php
/**
 * Entrada [ http://www.entrada-project.org ]
 *
 * Entrada is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Entrada is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Entrada.  If not, see <http://www.gnu.org/licenses/>.
 *
 * A model to handle interaction with the draft creators.
 *
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Ryan Warner <rw65@queensu.ca>
 * @copyright Copyright 2014 Queen's University. All Rights Reserved.
 */
class Models_Event_Draft_Option extends Models_Base {
    
    protected $draft_id, $option, $value;
    
    protected $table_name = "draft_options";
    protected $default_sort_column = "option";

    public function __construct($arr = NULL) {
        parent::__construct($arr);
    }
    
    public function getDraftID() {
        return $this->draft_id;
    }
    
    public function getOption() {
        return $this->option;
    }
    
    public function getValue() {
        return $this->value;
    }
    
    public static function fetchAllByDraftID($draft_id = 0) {
        $self = new self();

        $constraints = array(
            array(
                "mode"      => "AND",
                "key"       => "draft_id",
                "value"     => $draft_id,
                "method"    => "="
            )
        );

        $objs = $self->fetchAll($constraints, "=", "AND", $sort_col, $sort_order);
        $output = array();

        if (!empty($objs)) {
            foreach ($objs as $o) {
                $output[] = $o;
            }
        }

        return $output;
    }
    
    public function update() {
        global $db;
        
        if ($db->AutoExecute($this->table_name, $this->toArray(), "UPDATE", "`draft_id` = ".$db->qstr($this->draft_id)." AND `option` = ".$db->qstr($this->option))) {
            return $this;
        } else {
            return false;
        }
        
    }
    
    public function insert() {
        global $db;
        
        if ($db->AutoExecute($this->table_name, $this->toArray(), "INSERT")) {
            return $this;
        } else {
            return false;
        }
    }
    
}