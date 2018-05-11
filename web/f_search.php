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
readfile('_header.html');
include_once("nav.php");
include("conndb.php");

if(!isset($_POST['action'])){
    $sql_query = "SELECT * FROM `user`";            
    $stmt = $dbLink->prepare($sql_query);
    $stmt->execute();
    $dbLink = NULL;
    echo'<h1 align="center">Search Data</h1>';
    echo'<form action="f_search.php" method="post">';
    echo'<table border=\'2\' cellpadding=\'10\' align=\'center\'>';
    echo'    <tr>';
    echo'        <td>DATE</td>';
    echo'        <td><input type="date" name="date1"> to <input type="date" name="date2"></td>';
    echo'    </tr>';
    echo'    <tr>';
    echo'        <td>SSD</td>';
    echo'        <td><input type="text" name="ssd"> </td>';
    echo'    </tr>';
    echo'    <tr>';
    echo'        <td>OPERTOR</td>';
    echo'        <td><input list="user" name="user"><datalist id=user>';
    while($data = $stmt->fetch(PDO::FETCH_ASSOC)){
        echo'<option value="'.$data['uID'].'" label="'.$data['uName'].'">';
    }
    echo'        </datalist></td>';
    echo'    </tr>';        
    echo'    <tr>';
    echo'        <td colspan="2" align="center">';         
    echo'        <input type="hidden" name="action" value="search">';
    echo'        <input type="submit" name="button" value="SEARCH">';
    echo'        <input type="reset" name="button2" value="RESET">';
    echo'    </tr>';
    echo'</table>';
    echo'</form>';
}else{

    if($_POST['date1'] && $_POST['date2']){
        $condition = sprintf("`stime` >= '%s 00:00:00' AND `stime` <= '%s 23:59:59'",$_POST['date1'],$_POST['date2']);
    }
    if($_POST['ssd']){
        if($condition){
            $condition .= sprintf(" AND `ssd` LIKE '%s%s%s'", '%',$_POST['ssd'],'%'); 
        }else{
            $condition = sprintf("`ssd` LIKE '%s%s%s'", '%',$_POST['ssd'],'%'); 
        }
    }
    if($_POST['user']){
        if($condition){
            $condition .= sprintf(" AND `uID` = '%d'", $_POST['user']); 
        }else{
            $condition = sprintf("`uID` = '%d'", $_POST['user']); 
        }
    }

    try{
        
        $sql_query = "SELECT * FROM `info` WHERE $condition ORDER BY info.no DESC";
        $stmt = $dbLink->prepare($sql_query);
        $stmt->execute();
        $total = $stmt->rowCount();
        $dbLink = NULL;
    }catch(PDOException $e){
        echo $e->getMessage(); die();
    }

    echo '<p align="center">Total&nbsp;';
    echo $total;
    echo '&nbsp;datas</p>';
    echo '<table border="2" cellpadding="10" align="center">';
    echo '<tr>';
    echo '<th>SN</th> <th>SSD</th> <th>ITEM</th> <th>REPORT</th> <th>INFO</th>';
    echo '</tr>';
    try{
        while($data = $stmt->fetch(PDO::FETCH_ASSOC)){
            preg_match("/Vendor:([a-zA-Z0-9]{1,})/", $data['ssd'], $vendor);
            preg_match("/Model:([a-zA-Z0-9]{1,})/", $data['ssd'], $model);

            echo "<tr><td>{$data['no']}</td> <td>{$vendor[1]}&nbsp;{$model[1]}</td> <td>{$data['item']}</td>";
            
            if(file_exists($data['report'].'/report.pdf')){
                echo "<td><a href='f_download.php?no={$data['no']}&type=pdf'>Download</a></td>";
            }else{
                echo "<td>N/A</td>";
            }
            
            echo "<td><a href='f_detail.php?no={$data['no']}'>LINK</a></td></tr>";
        }

        echo "</table>";
    }
    catch(PDOException $e){
        echo $e->getMessage();
    }
}
readfile('_footer.html');
?>


