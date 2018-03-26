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

$adminlink = '
<nav>
    <div class="navbar">
        <a href="t_test.php">Test</a>
        <div class="dropdown">
            <button class="dropbtn">Log 
            <i class="fa fa-caret-down"></i>
            </button>
            <div class="dropdown-content">
                <a href="f_report.php">Report</a>
                <a href="f_search.php">Search</a>
            </div>
        </div> 
        <div class="admin">
            <a href="admin.php">Administer</a>
        </div>
    </div>
</nav>';

$userlink = '
<nav>
    <div class="navbar">
        <a href="t_test.php">Test</a>
        <div class="dropdown">
            <button class="dropbtn">Log 
            <i class="fa fa-caret-down"></i>
            </button>
            <div class="dropdown-content">
                <a href="f_report.php">Report</a>
                <a href="f_search.php">Search</a>
            </div>
        </div> 
    </div>
</nav>';


session_start();
if(isset($_SESSION['uName'])){
    echo "<p align='right'>Signed in as {$_SESSION['uName']}</p>";
}

if(isset($_SESSION['uPermissions'])){
    if(preg_match('/A/',$_SESSION['uPermissions']))
        echo $adminlink; 
    else 
        echo $userlink;
}else{
    header("Location: index.php");
}


?>