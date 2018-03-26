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

    try{
        require("conndb.php");

        $sql_query = "SELECT `time` FROM `status`";            
        $stmt = $dbLink->prepare($sql_query);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        //delete check status
        $sql_delete =  sprintf("DELETE FROM `status` WHERE `status`.`time` = \"%s\"", $data['time']);
        $stmtDelete = $dbLink->prepare($sql_delete);            
        $stmtDelete->execute();
        $dbLink = null;

    }catch(PDOException $e){
        echo $e->getMessage();
    }


    echo "<h2>COMPLETE</h2>";
    
    readfile('_footer.html');
?>