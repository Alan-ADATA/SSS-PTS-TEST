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
session_start();
readfile('_header.html');
include_once("nav.php");
include_once("util.php");

$str = '';
echo "<div>";

require("conndb.php");
try{
    $sql = "SELECT * FROM `status`";            
    $stmt = $dbLink->prepare($sql);
    $stmt->execute();
}catch(PDOException $e){
    echo $e->getMessage();
}

if($data = $stmt->fetch(PDO::FETCH_ASSOC)){
    $dbLink = null;
    echo 'Testing is ongoing.</br>Can not start the second test！';
    
}else{
    if(!isset($_POST['target'])){
        ErrorMsg('test target');
        
    }elseif(!isset($_POST['test'])){
        ErrorMsg('test item');
        
    }elseif($_POST['purge']<0 || $_POST['purge']>4){
        ErrorMsg('purge mode');
        
    }elseif($_POST['max_round']<5 || $_POST['max_round']>100){
        ErrorMsg('max test rounds');
        
    }elseif($_POST['verbose']<0 || $_POST['verbose']>1){
        ErrorMsg('verbose');
        
    }elseif ($_POST['dirth_rw']<0 || $_POST['dirth_rw']>100) {
        ErrorMsg('DIRTH RW');
        
    }elseif ($_POST['dirth_bs']<1 || $_POST['dirth_bs']>65536) {
        ErrorMsg('DIRTH BS');

    }elseif($_POST['wsat_wl']<0 || $_POST['wsat_wl']>4){
        ErrorMsg('WSAT workload');
    }

    foreach($_POST['test'] as $item){
        switch($item){
            case 'iops':
            case 'throughput':
            case 'latency':
            case 'wsat':
            case 'hir':
            case 'xsr':
            case 'cbw':
            case 'dirth':
                break;
    
            default:
                ErrorMsg('test item');                            
        }
    }

    //shell script command
    $cmdstr = $run.' --threads='.$_POST['tc'];
    $cmdstr.= ' --oio_per_thread='.$_POST['qd'];
    $cmdstr.= ' --target='.$_POST['target'];
    $cmdstr.= ' --test='.implode(' --test=', $_POST['test']);
    $cmdstr.= ' --ss_max_rounds='.$_POST['max_round'];
    
    if(in_array('dirth', $_POST['test'])){
        $cmdstr.= ' --dirth_rw='.$_POST['dirth_rw'];
        $cmdstr.= ' --dirth_bs='.$_POST['dirth_bs'];
    }

    if(in_array('wsat', $_POST['test'])){
        $cmdstr.= ' --wsat_wl='.$_POST['wsat_wl'];
        $cmdstr.= ' --wsat_time='.$_POST['wsat_time'];
    }

    if($_POST['spec'] == 0){
        $cmdstr.= ' --spec=enterprise';
    }else{
        $cmdstr.= ' --spec=client';
    }

    if($_POST['purge'] == 0){
        $cmdstr.= ' --nopurge';
    }elseif($_POST['purge'] == 1){
        $cmdstr.= ' --secureerase_pswd=pts';
        shell_exec(sprintf("sudo hdparm --user-master u --security-set-pass pts %s",$_POST['test']));
    }elseif($_POST['purge'] == 4){
        $cmdstr.= ' --nvmeformat=1';
    }

    if($_POST['verbose'] == 1){        
        $cmdstr.= ' --verbose';
    }

    echo 'Start testing...</p>';

    //make a repot folder
    $GLOBALS['date'] = date('Ymd');
    $GLOBALS['time'] = date('His');
    $startTime = date("Y-m-d H:i:s");
    $new_folder = $report_folder.$date.'/'.$time;
    if(!mkdir($new_folder, 0777, true)){
        die('Failed to create folder...');
    }
    chdir($new_folder);

    //write data into DB
    require("conndb.php");
    try{
        $sql = "SELECT MAX(`no`) FROM `info`";            
        $stmt = $dbLink->prepare($sql);
        $stmt->execute();
        if($data = $stmt->fetch(PDO::FETCH_ASSOC)){
            $last = $data['MAX(`no`)'];
            $logDate = substr($last, 0, 4); 
            $nowDate = date("ym");
            $newNo = ($nowDate == $logDate)? ++$last : $nowDate.'001';
            
        }else{
            $newNo = date("ym").'001';
        }


    }catch(PDOException $e){
        echo $e->getMessage();
    }

    foreach($_POST['test'] as $item){
        if($item == 'throughput'){
            $itemArray[] = 'TP';
        }elseif($item == 'latency'){
            $itemArray[] = 'LAT';
        }else{
            $itemArray[] = strtoupper($item);
        }
    }

    $db = array(
                'no' => $newNo,
                'stime' => $startTime,
                'type' => $_POST['spec'] == 0? 'enterprise':'client',
                'item' => implode(',', $itemArray),
                'uid' => $_SESSION['uID'],
                'ssd' => sprintf("<ul><li>Vendor:%s</li><li>Model:%s</li><li>Capacity:%s</li><li>SN:%s</li><li>FW ver:%s</li><li>Interface:%s</li><li>NAND type:%s</ul>", 
                                $_POST['mfgr'], $_POST['modelno'], $_POST['capacity'], $_POST['sn'], $_POST['fwv'], $_POST['interface'], $_POST['nandtype']),
                'report' => $new_folder,
                'command' => $cmdstr);

    try{
        
        $sql = "INSERT INTO `info`(`no`, `stime`, `etime`, `type`, `item`, `uID`, `ssd`, `report`, `command`) 
                VALUES ({$db['no']}, \"{$db['stime']}\", null, \"{$db['type']}\", \"{$db['item']}\", {$db['uid']}, 
                        \"{$db['ssd']}\", \"{$db['report']}\" , \"{$db['command']}\")";
        $stmt = $dbLink->prepare($sql);
        $stmt->execute();
        
    }
    catch(PDOException $e){
        echo $e->getMessage();
    }

    //ssd information for report
    $infoObj = new stdClass;
    $infoObj->device = $_POST['target'];
    $infoObj->mfgr = $_POST['mfgr'];
    $infoObj->modelno = $_POST['modelno'];
    $infoObj->sn = $_POST['sn'];
    $infoObj->fwv = $_POST['fwv'];
    $infoObj->interface = $_POST['interface'];
    $infoObj->nandtype = $_POST['nandtype'];
    $infoObj->testsn = $newNo;
    $infoJSON = json_encode($infoObj);

    $db = array(
                'time' => $startTime,
                'data' => $infoJSON,
                'para' => file_get_contents(dirname(__FILE__).'/parameter.json'));

    try{
        $sql = "INSERT INTO `status`(`time`, `data`, `parameter`) 
                VALUES (\"{$db['time']}\", '{$db['data']}', '{$db['para']}')";               
        $stmt = $dbLink->prepare($sql);
        $stmt->execute();
        
        $dbLink = null;        
    }
    catch(PDOException $e){
        echo $e->getMessage();
    }

    //Start the test procedure
    $cmd = 'sudo '.$cmdstr;

    //Print the test process
    while (@ ob_end_flush()); // end all output buffers if any
    
    $proc = popen($cmd, 'r');
    echo '<pre>';
    while (!feof($proc))
    {
        echo $str = fread($proc, 4096);

        //write log into file 
        if($_POST['verbose'] == 1){              
            file_put_contents($log, $str, FILE_APPEND);
        }

        @ flush();
    }
    echo '</pre>';
    pclose($proc);

    try{
        require("conndb.php");

        //update finish time
        $sql_update = sprintf("UPDATE `info` SET `etime` = \"%s\" WHERE `info`.`no` = ?", date("Y-m-d H:i:s"));
        $stmtUpdate = $dbLink->prepare($sql_update);            
        $stmtUpdate->execute(array($newNo));

        //delete check status
        $sql_delete =  sprintf("DELETE FROM `status` WHERE `status`.`time` = \"%s\"", $startTime);
        $stmtDelete = $dbLink->prepare($sql_delete);            
        $stmtDelete->execute();
        $dbLink = null;

    }catch(PDOException $e){
        echo $e->getMessage();
    }
    
}


function ErrorMsg($str)
{
echo<<<HTML
    <h1><span style="color: red">ERROR:</span></br>
    Invalid input $str.</h1>
    
    <form>
    <input type="button" value="Go back" onclick="history.back()"></input>
    </form>
HTML;

    exit;
}

readfile('_footer.html');
?>