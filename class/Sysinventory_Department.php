<?php
/**
 * Class for adding and editing departments
 * @author Micah Carter <mcarter at tux dot appstate dot edu>
 **/

class Sysinventory_Department {
    
    var $id = NULL;
    var $description = NULL;
    var $last_update = NULL;
    
    function showDepartments($whatToDo,$department) {
        PHPWS_Core::initModClass('sysinventory','UI/Sysinventory_DepartmentUI.php');
        $disp = &new Sysinventory_DepartmentUI;
        if ($whatToDo == 'addDep' && isset($department)) {
            Sysinventory_Department::addDepartment($department);
        }
        else if ($whatToDo == 'delDep' && isset($department)) {
            Sysinventory_Department::delDepartment($department);
        }
        Layout::addStyle('sysinventory','style.css');
        Layout::add($disp->display());
    }

    function get_row_tags() {
        $template = array();
        $template['LAST_UPDATE'] = date("r",$this->getLastUpdate());
        $template['DELETE'] = PHPWS_Text::moduleLink('Delete','sysinventory',array('action'=>'edit_departments','delDep'=>TRUE,'id'=>$this->getID()));
        return $template;
    }

    function addDepartment($depName) {
        //test($depName,1);
        if (!isset($depName)) return;
        $db = &new PHPWS_DB('sysinventory_department');
        $db->addValue('id','NULL');
        $db->addValue('description',$depName);
        $db->addValue('last_update',time());
        $result = $db->insert();
    }

    function delDepartment($depName) {
        if (!isset($depName)) return;
        $db = new PHPWS_DB('sysinventory_department');
        $db->addWhere('id',$depName);
        $db->delete();
    }

    function getID() {
        return $this->id;
    }

    function getDescription() {
        return $this->description;
    }

    function getLastUpdate() {
        return $this->last_update;
    }

    function setID($newid) {
        $this->id = $newid;
    }

    function setDescription($newdesc) {
        $this->description = $newdesc;
    }

    function setLastUpdate($newupd) {
        $this->last_update = $newupd;
    }
}
?>