<?php
/**
 * Copyright 2018 ADATA Technology Co., Ltd.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
?>
<?php

if(isset($_POST['action']) && ($_POST['action']=="add")){

    try{
        include("conndb.php");
        $sql_query = "INSERT INTO `user`(`uID`, `uName`, `uEnable`, `uPermissions`, `uLogin` ) VALUES (?,?,?,?,?)";
        $stmt = $dbLink->prepare($sql_query);
        $stmt->execute(array($_POST['id'], $_POST['name'], $_POST['enable'], implode(",",$_POST['permissions']), NULL));
        $dbLink = NULL;
    }
    catch(PDOException $e){
        echo $e->getMessage(); die();
    }

    header("Location: admin.php");
}

readfile('_header.html');
include_once("nav.php");
?>

<h1 align="center">ADD USER</h1>
<form action="" method="post">
<table border='2' cellpadding='10' align='center'>
    <tr>
        <td>ID</td> 
        <td><input type="txt" name="id" id="id" minlengh="8" maxlengh="8" pattern="[0-9]{8}" title="Enter 8 digits" required></td>
    </tr>
    <tr>
        <td>NAME</td> 
        <td><input type="txt" name="name" id="name" required></td>
    </tr>
    <tr>
        <td>SATAUS</td> 
        <td><input type="radio" name="enable" id="enable" value="1" checked>Enable
            <input type="radio" name="enable" id="enable" value="0">Disable</td>
    </tr>
    <tr>
        <td>PERMISSION</td> 
        <td><input type="checkbox" name="permissions[]" value="R">Read
            <input type="checkbox" name="permissions[]" value="W">Write
            <input type="checkbox" name="permissions[]" value="D">Delete
            <input type="checkbox" name="permissions[]" value="A">Administer</td>
    </tr>
    <tr>
        <td colspan="2" align="center">
        <input type="hidden" name="action" value="add">
        <input type="submit" name="button" value="Add">
        <input type="reset" name="button2" value="Reset">
    </tr>
</table>
</form>

<?php readfile('_footer.html');?>