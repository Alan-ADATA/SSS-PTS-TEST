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
$str = "";

if(!is_dir($report_folder)){
    if(!mkdir($report_folder, 0777, true)){
        die('Failed to create report folder. You need create it by yourself and set mode 777.');
    }
}

function GetSSDInfo($num)
{
    global $ssd;
    global $infoArray;
    global $file_tmp;

    for($i=0; $i< $num; $i++){
        $value = "/dev/".$ssd[$i];
        $valueArray = array();
        $valueArray['capacity'] = shell_exec(sprintf("lsblk -n -o size %s", $value));

        if(preg_match("/sd/", $value)){
            $cmd = sprintf("sudo smartctl -i %s > %s", $value, $file_tmp);
            shell_exec($cmd);
            $fp = fopen($file_tmp, "r") or die("Unable to open file!");
    
            while(!feof($fp)) {
                $txt = fgets($fp);
            
                if(preg_match("/Device Model/", $txt)){
                    $newtxt = explode(" ", chop($txt));
                    $valueArray['mfgr'] = $newtxt[6];
                    $valueArray['modelno']  = $newtxt[7];

                }
                elseif(preg_match("/Serial Number/",$txt)){      
                    $newtxt = explode(" ", chop($txt));
                    $valueArray['sn'] = $newtxt[5];
                }
                elseif(preg_match("/Firmware Version/", $txt)){
                    $newtxt = explode(" ",chop($txt));
                    $valueArray['fwv'] = $newtxt[2];
                }
                elseif(preg_match("/SATA Version/", $txt)){
                    $valueArray['interface'] = str_replace("SATA Version is:  ", "", chop($txt));
                }
            } 
            $valueArray['nandtype'] = '3D TLC';
            $infoArray[$value] = $valueArray;
            unset($valueArray);

        }else{  // PCIe            
            $cmd = sprintf("sudo nvme id-ctrl %s > %s", $value, $file_tmp);
            shell_exec($cmd);   
            
            $fp = fopen($file_tmp, "r") or die("Unable to open file!");
    
            while(!feof($fp)) {
                $txt = fgets($fp);
            
                if(preg_match("/mn /", $txt)){
                    $newtxt = explode(":", chop($txt));
                    $newtxt1 = explode(" ", trim($newtxt[1]));
                    if($newtxt1[1] == NULL){
                        $newtxt1 = explode("_", trim($newtxt[1])); 
                    }
                    $valueArray['mfgr'] = $newtxt1[0];
                    $valueArray['modelno'] = $newtxt1[1];
                }
                elseif(preg_match("/sn/",$txt)){      
                    $newtxt = explode(":", chop($txt));
                    $valueArray['sn'] = trim($newtxt[1]);
                }
                elseif(preg_match("/fr /", $txt)){
                    $newtxt = explode(":",chop($txt));
                    $valueArray['fwv'] = trim($newtxt[1]);
                }
            }

            $valueArray['interface'] = 'PCIe 3.0 X4';
            $valueArray['nandtype'] = '3D TLC';
            $infoArray[$value] = $valueArray;
            unset($valueArray);
        }
    }
    unlink($file_tmp);
}


    //get all devices list
    shell_exec("lsblk -d > $file_tmp");
    $fp = fopen($file_tmp, "r") or die("Unable to open file!");
    $i = 0;

    while(!feof($fp)) {
        $txt = fgets($fp);
        
        if(preg_match("/disk/", $txt) && !preg_match("/sda/", $txt)){
            $newtxt = explode(" ",chop($txt));
            $ssd[$i] = $newtxt[0];
            $i++;            
        }
    }

    fclose($fp);

    //get device name
    global $ssdName;
    for($i=0; $i<count($ssd); $i++){
        if(preg_match("/sd/", $ssd[$i])){       
            $cmd = sprintf("sudo smartctl -i /dev/%s > $file_tmp", $ssd[$i]); 
            shell_exec($cmd);
    
            $fp = fopen("$file_tmp", "r") or die("Unable to open file!");
            while(!feof($fp)) {
                $txt = fgets($fp);
                
                if(preg_match("/Device Model/", $txt)){
            
                    $newtxt = explode(":", chop($txt));
                    $ssdName[$i] = trim($newtxt[1]);
                    break;
                }
            } 
            fclose($fp);

        }else{
            $cmd = sprintf("sudo nvme id-ctrl /dev/%s > $file_tmp", $ssd[$i]);        
            shell_exec($cmd);
    
            $fp = fopen("$file_tmp", "r") or die("Unable to open file!");
            while(!feof($fp)) {
                $txt = fgets($fp);
                
                if(preg_match("/mn/", $txt)){
            
                    $newtxt = explode(":", chop($txt));
                    $ssdName[$i] = trim($newtxt[1]);
                    break;
                }
            }             

            fclose($fp);    
        }
        
    }

    unlink("$file_tmp");

    //show html
echo<<<HTML
    
<div class="main">
    <h2>Choose your action</h2></p>
    <form method='post' action='t_testing.php'>
    Target :
HTML;

    if(count($ssdName) == 1){
        $value = "/dev/".$ssd[0];
echo<<<HTML
        <input type="radio" name="target" onclick=if(this.checked){ShowInfo("$value")} value="$value" required />
        <label>$ssdName[0]($value)</label>
HTML;

   }else{
        for($i=0; $i< count($ssdName); $i++){
            $value = "/dev/".$ssd[$i];            
echo<<<HTML
            <input type="radio" name="target" onclick=if(this.checked){ShowInfo("$value")} value="$value" required />
            <label>$ssdName[$i]($value)</label>
HTML;
        }
    }
    
    GetSSDInfo(count($ssdName));

?>
    
    <table>
        <tr> <h3>Confirm information</h3> </tr>
        <tr>
            <td>Vendor</td>
            <td><input type='text' size="50" id='mfgr' name='mfgr' value='' ></td>
        </tr>
        <tr>
            <td>Model No.</td>
            <td><input type='text' size="50" id='modelno' name='modelno' value=''></td>
        </tr>
        <tr>
            <td>Capacity</td>
            <td><input type='text' size="50" id='capacity' name='capacity' value='' ></td>
        </tr>        
        <tr>
            <td>S/N</td>
            <td><input type='text' size="50" id='sn' name='sn' value='' ></td>
        </tr>        
        <tr>
            <td>Firmware version</td>
            <td><input type='text' size="50" id='fwv' name='fwv' value='' ></td>
        </tr>
        <tr>
            <td>Interface</td>
            <td><input type='text' size="50" id='interface' name='interface' value='' ></td>
            <td ><div id="msg1" style="visibility:hidden; color:#FF0000">PCIe type is entered by operator</div></td>
        </tr>
        <tr>
            <td>NAND Type</td>
            <td><input type='text' size="50" id='nandtype' name='nandtype' value='' ></td>
            <td><div id="msg2" style="visibility:hidden; color:#FF0000">This field is entered by operator</div></td>
        </tr>
    </table>    
    </p>

    Specification type:
    <input type='radio' name='spec' value=0 onclick=if(this.checked){SetTCQD(value)} checked><label>Enterprise</label>
    <input type='radio' name='spec' value=1 onclick=if(this.checked){SetTCQD(value)}><label>Client</label>    
    </p>

    Test item :
    <input type='checkbox' name='test[]' value='iops'><label>iops</label>
    <input type='checkbox' name='test[]' value='throughput'><label>throughput</label>
    <input type='checkbox' name='test[]' value='latency'><label>latency</label>
    <input type='checkbox' name='test[]' value='wsat'><label>wsat</label>
    <input type='checkbox' name='test[]' value='hir'><label>hir</label>
    <input type='checkbox' name='test[]' value='xsr'><label>xsr</label>
    <input type='checkbox' name='test[]' value='cbw'><label>cbw</label>
    <input type='checkbox' name='test[]' value='dirth'><label>dirth</label>
    </p>

    Purge mode:
    <input type='radio' name='purge' value=0><label>No purge</label>
    <input id='sata' type='radio' name='purge' value=1><label>ATA secure erase</label>
    <input id='nvme' type='radio' name='purge' value=4><label>NVMe format namespace</label>
    <input type='radio' name='purge' value=2><label>TRIM</label>
    <input type='radio' name='purge' value=3><label>zero fill</label>
    </p>

    Max test rounds:
    <select name='max_round'>
<?php

    for($i=5; $i<=100; $i++){
        echo $showValue = sprintf("<option %s value='%d'>%d</option>", ($i==25)?'selected=\'true\'':'', $i, $i);     
    }
?>
    </select></p>

    TOIO:&nbsp; &nbsp; <font color='blue'>iops. throughput. wsat. hir. xsr by test operator choice</font></br>
    Thread Count:
    <select name='tc' id='tc'>
<?php
    $tc = array(1,2,4,6,8,16,32);
    foreach($tc as $value){
        echo $showValue = sprintf("<option %s value='%d'>%d</option>", ($value==4)?'selected=\'true\'':'', $value, $value);
    }
?>
    </select>&nbsp; &nbsp; 
    Queue Depth:
    <select name='qd' id='qd'>
<?php
    $qd = array(1,2,4,6,8,16,32);
    foreach($qd as $value){
        echo $showValue = sprintf("<option %s value='%d'>%d</option>", ($value==32)?'selected=\'true\'':'', $value, $value);
    }
?>
    </select></p>
    WSAT test optional workload:
    <select name='wsat_wl'>
<?php
    $wl = array('Write Intensive (RND 4KiB RW0)','Mixed or OLTP (RND 8KiB RW65)','Video On Demand (SEQ 128KiB RW90)',
                'Meta Data (SEQ 0.5KiB RW50)','Composite Block Size Workload (mixed/composite BS/RW)');
    var_dump($wl);            
    foreach($wl as $key=>$value){
        echo sprintf("<option %s value='%d'>%s</option>",($key==0)?'selected=\'true\'':'', $key, $value);
    }
?>
    </select>&nbsp; &nbsp; 
    WSAT test optional time period:
    <select name='wsat_time'>
<?php
    for($i=6; $i<=24; $i++){
        echo $showValue = sprintf("<option %s value='%d'>%d</option>", ($i==6)?'selected=\'true\'':'', $i, $i);     
    }
?>
    </select>Hr</p>
    DIRTH test parameter&nbsp; &nbsp; <font color='blue'>dirth test read write percentage and block size by test operator choice</font></br>
    RW: Read
    <select name='dirth_rw'>
<?php

    for($i=0; $i<=100; $i++){
        echo $showValue = sprintf("<option %s value='%d'>%d</option>", ($i==65)?'selected=\'true\'':'', $i, $i);     
    }
?>
    </select> % &nbsp; &nbsp; 
    Block Size:
    <select name='dirth_bs'>
<?php
    $bs = array(512,1024,1536,2048,2560,3072,3584,4096,8192,16384,32768,65536);
    foreach($bs as $value){

        if($value%1024 == 0){
            echo $showValue = sprintf("<option %s value='%d'>%d (%d KiB)</option>", ($value==8192)?'selected=\'true\'':'', $value, $value, round($value/1024));
        }else{
            echo $showValue = sprintf("<option value='%d'>%d (%.1f KiB)</option>", $value, $value, round($value/1024, 1));
        }
        
    }
?>
    </select></p>

    Show test log:
    <input type='radio' name='verbose' value=1 checked><label>ON</label>
    <input type='radio' name='verbose' value=0><label>OFF</label>
    </p><input type='submit' value='GO' class='button'>
    </form>

    

    <script>
    function ShowInfo(device) {
        var info = <?php echo json_encode($infoArray) ?>;
        
        document.getElementById("mfgr").value = info[device].mfgr;
        document.getElementById("modelno").value = info[device].modelno;
        document.getElementById("sn").value = info[device].sn;
        document.getElementById("fwv").value = info[device].fwv;
        document.getElementById("interface").value = info[device].interface;
        document.getElementById("nandtype").value = info[device].nandtype;
        document.getElementById("capacity").value = info[device].capacity;
        
        if(device.indexOf("/dev/sd")){
            //nvme device
            document.getElementById("msg1").style.visibility = "visible"; 
            document.getElementById("sata").disabled = true;
            document.getElementById("nvme").disabled = false;
            document.getElementById("nvme").checked = true;
        }else{
            //sata device
            document.getElementById("msg1").style.visibility = "hidden";
            document.getElementById("sata").disabled = false;
            document.getElementById("nvme").disabled = true;
            document.getElementById("sata").checked = true;
        }

        document.getElementById("msg2").style.visibility = "visible";
    }

    function SetTCQD(value){
        if(value == 0){
            document.getElementById("tc").value = 4;
            document.getElementById("qd").value = 32;
        }else{
            document.getElementById("tc").value = 2;
            document.getElementById("qd").value = 16;
        }
    }
    </script>

</div>

<?php
readfile('_footer.html');
?>