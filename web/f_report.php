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
include_once("util.php");

try{
    require_once("conndb.php");
    $sql = "SELECT info.no,info.item,info.report,info.ssd,user.uName 
            FROM `info`,`user` 
            WHERE info.uID=user.uID
            ORDER BY info.no DESC";            
    $stmt = $dbLink->prepare($sql);
    $stmt->execute();
    $total = $stmt->rowCount();

}catch(PDOException $e){
    echo $e->getMessage();
}
?>

</p>
<h1 align='center'>TEST LOG</h1>
<p align='center'>Total&nbsp;<?php echo $total;?>&nbsp;datas&nbsp;&nbsp;<a href="f_search.php">SEARCH</a></p>

<table border='2' cellpadding='10' align='center'>
    <tr>
        <th>SN</th> <th>SSD</th> <th>ITEM</th> <th>REPORT</th> <th>INFO</th>
    </tr>

<?php

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

        $dbLink = NULL;

    }
    catch(PDOException $e){
        echo $e->getMessage();
    }


readfile('_footer.html');
?>