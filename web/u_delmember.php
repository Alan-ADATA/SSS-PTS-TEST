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
if(isset($_POST['action']) && ($_POST['action']=="delete")){

    try{
        
        $sql_query = "DELETE FROM `user` WHERE `user`.`uID` = ?";
        $stmt = $dbLink->prepare($sql_query);
        $stmt->execute(array($_POST['uid']));
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

<h1 align="center">DELETED</h1>
<form action="" method="post">
<table border='2' cellpadding='10' align='center'>
    <tr>
        <th>ID</th> <th>NAME</th> <th>PERMISSION</th> <th>LAST LOGIN</th> <th>STATUS</th>    
    </tr>

    <tr>        
        <td><?php echo $data['uID'];?></td>
        <td><?php echo $data['uName'];?></td>        
        <td>
            <?php 
                if(preg_match("/R/",$data['uPermissions'])) echo 'Read ';
                if(preg_match("/W/",$data['uPermissions'])) echo 'Write ';
                if(preg_match("/D/",$data['uPermissions'])) echo 'Delete ';
                if(preg_match("/A/",$data['uPermissions'])) echo 'Administer'; 
            ?>
        </td>
        <td><?php echo $data['uLogin'];?></td>
        <td><?php if($data['uEnable']==1) echo 'Enable'; else echo 'Disable'; ?></td>
    </tr>
    
    <tr>
        <td colspan="5" align="center">
        <input type="hidden" name="uid" value="<?php echo $data['uID'];?>">            
        <input type="hidden" name="action" value="delete">
        <input type="submit" name="button" value="Delete">
    </tr>
</table>
</form>

<?php readfile('_footer.html');?>