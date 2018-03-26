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
    require_once("conndb.php");
    try{
        $sql = "SELECT `report`
                FROM `info` 
                WHERE info.no=?";            
        $stmt = $dbLink->prepare($sql);
        $stmt->execute(array($_GET['no']));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $dbLink = null;
    }catch(PDOException $e){
        echo $e->getMessage();
    }

    $ext = ($_GET['type'] == 'pdf')? 'pdf':(($_GET['type'] == 'zip')?'zip':'txt');
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Cache-Control: private', false);
    header('Content-Type: application/octet-stream');
    header('Content-Transfer-Encoding: Binary');
    if($ext == 'txt')
        header('Content-Disposition:attachment;filename=log_'.$_GET['no'].'.txt');
    else
        header('Content-Disposition:attachment;filename=report_'.$_GET['no'].'.'.$ext);

    ob_clean();
    flush();
    if($_GET['type'] == 'pdf')
        readfile(sprintf("%s/report.pdf",$data['report']));
    elseif($_GET['type'] == 'zip')
        readfile(sprintf("%s/report.zip",$data['report']));
    elseif ($_GET['type'] == 'txt') {
        readfile(sprintf("%s/log.txt",$data['report']));
    }

?>