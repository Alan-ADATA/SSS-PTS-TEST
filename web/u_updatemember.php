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
include("conndb.php");
if(isset($_POST['action']) && ($_POST['action']=="update")){

    try{
        
        $sql_query = "UPDATE `user` SET `uID`=?,`uName`=?,`uEnable`=?,`uPermissions`=? WHERE `user`.`uID`=?";
        $stmt = $dbLink->prepare($sql_query);
        $stmt->execute(array($_POST['id'], $_POST['name'], $_POST['enable'], implode(",",$_POST['permissions']), $_POST['uid']));
        $dbLink = NULL;
    }
    catch(PDOException $e){
        echo $e->getMessage(); die();
    }

    header("Location: admin.php");
}

$sql_select = "SELECT `uID`, `uName`, `uEnable`, `uPermissions`, `uLogin` FROM `user` WHERE `uID`=?";
$stmt = $dbLink->prepare($sql_select);
$stmt->execute(array($_GET['id']));
$data = $stmt->fetch(PDO::FETCH_ASSOC);
$dbLink = NULL;
readfile('_header.html');
include_once("nav.php");
?>

<h1 align="center">Update Member Data</h1>
<form action="" method="post">
<table border='2' cellpadding='10' align='center'>
    <tr>
        <td>ID</td> 
        <td><input type="txt" name="id" id="id" value="<?php echo $data['uID'];?>" minlengh="8" maxlengh="8" pattern="[0-9]{8}" title="enter 8 digits" required></td>
    </tr>
    <tr>
        <td>NAME</td> 
        <td><input type="txt" name="name" id="name" value="<?php echo $data['uName'];?>" required></td>
    </tr>
    <tr>
        <td>STATUS</td> 
        <td><input type="radio" name="enable" id="enable" value="1" <?php if($data['uEnable']==1) echo 'checked';?>>Enable
            <input type="radio" name="enable" id="enable" value="0" <?php if($data['uEnable']==0) echo 'checked';?>>Disable</td>
    </tr>
    <tr>
        <td>PERMISSIONS</td> 
        <td><input type="checkbox" name="permissions[]" value="R" <?php if(preg_match("/R/",$data['uPermissions'])) echo 'checked';?>>Read
            <input type="checkbox" name="permissions[]" value="W" <?php if(preg_match("/W/",$data['uPermissions'])) echo 'checked';?>>Write
            <input type="checkbox" name="permissions[]" value="D" <?php if(preg_match("/D/",$data['uPermissions'])) echo 'checked';?>>Delete
            <input type="checkbox" name="permissions[]" value="A" <?php if(preg_match("/A/",$data['uPermissions'])) echo 'checked';?>>Administer</td>
    </tr>
    <tr>
        <td colspan="2" align="center">
        <input type="hidden" name="uid" value="<?php echo $data['uID'];?>">            
        <input type="hidden" name="action" value="update">
        <input type="submit" name="button" value="UPDATE">
        <input type="reset" name="button2" value="RESET">
    </tr>
</table>
</form>

<?php readfile('_footer.html');?>