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
require_once("nav.php");
try{
    include("conndb.php");
    $sql_select = "SELECT info.*,user.uName FROM `info`,`user` WHERE info.no=?";
    $stmt = $dbLink->prepare($sql_select);
    $stmt->execute(array($_GET['no']));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $dbLink = NULL;
}catch(PDOException $e){
    echo $e->getMessage(); die();
}
?>

<h1 align="center">TEST LOG</h1>

<table border='2' cellpadding='10' align='center'>
    <tr>
        <td>SN</td> 
        <td><?php echo $data['no'];?></td>
    </tr>
    <tr>
        <td>START TIME</td> 
        <td><?php echo $data['stime'];?></td>
    </tr>
    <tr>
        <td>FINISH TIME</td> 
        <td><?php echo $data['etime'];?></td>
    </tr>
    <tr>
        <td>PTS TYPE</td> 
        <td><?php echo $data['type'];?></td>
    </tr>
    <tr>
        <td>ITEM</td> 
        <td><?php 
                $itemArray = explode(',', $data['item']);
                foreach($itemArray as $item){
                    echo "<a href= #$item>$item</a>&nbsp;";
                }
            ?></td>
    </tr>
    <tr>
        <td>OPERATOR</td> 
        <td><?php echo $data['uName'];?></td>
    </tr>
    <tr>
        <td>SSD INFO</td> 
        <td><?php echo $data['ssd'];?></td>
    </tr>
    <tr>
        <td>COMMAND</td> 
        <td><?php echo $data['command'];?></td>
    </tr>
    <tr>
        <td>PDF REPORT</td>
        <?php             
            if(file_exists(sprintf("%s/report.pdf", $data['report']))){
                echo "<td><a href='f_download.php?no={$data['no']}&type=pdf'>Download</a></td>";
            }else{
                echo "<td>N/A</td>";
            }
        ?>
    </tr>
    <tr>
        <td>HTML DATA</td> 
        <?php 
            if(file_exists(sprintf("%s/report.zip", $data['report']))){
                echo "<td><a href='f_download.php?no={$data['no']}&type=zip'>Download</a></td>";
            }else{
                echo "<td>N/A</td>";
            }
        ?>
    </tr>
    <tr>
        <td>TEST LOG</td> 
        <?php 
            if(file_exists(sprintf("%s/log.txt", $data['report']))){
                echo "<td><a href='f_download.php?no={$data['no']}&type=txt'>Download</a></td>";
            }else{
                echo "<td>N/A</td>";
            }
        ?>
    </tr>                                
</table>


<?php
    if(count($itemArray)>0){
        echo "<p><div id=$item><table border='1' cellpadding='10' align='center'>
                <tr><td id='title'>ITEM</td><td>FIO LOG</td></tr>";
    }

    foreach($itemArray as $item){
        //show fio json file
        $jsonFile = array();
        switch($item){
            case 'TP':
                $jsonFile = array('fio-throughput-128k', 'fio-throughput-1024k');
                break;
            case 'LAT':
                $jsonFile = array('fio-latency');
                break;    
            case 'ECW':
                $jsonFile = array('fio-ecw-ecw1', 'fio-ecw-ecw2', 'fio-ecw-ecw3', 'ecw');
                break;
            case 'DIRTH':
                $jsonFile = array('fio-dirth-dirth1', 'fio-dirth-dirth2', 'fio-dirth-dirth3', 'dirth');
                break;
            default:
                $name = sprintf("fio-%s", strtolower($item));
                $jsonFile = array($name);
        }
        
        
        echo "<tr><td id='title'>$item</td><td>";
        $jsonNum = count($jsonFile);
        for($n=0; $n<$jsonNum; $n++){
            if(file_exists(sprintf("%s/%s.json", $data['report'], $jsonFile[$n]))){
                echo "<a href=\"f_json_viewer.php?no=$_GET[no]&file=$jsonFile[$n]\">$jsonFile[$n]</a>";
                if($jsonNum-$n != 1){
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;";
                }

                $file = TRUE;
            }
        }
        if($file != TRUE) echo "N/A";
        echo "</td></tr>";        
    }

    if(count($jsonFile)>0) echo "</table></div>";

    readfile('_footer.html');
?>
    