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

    $emptyLink = '
    <nav>
        <div class="navbar">
            <a href="#">Test</a>
            <div class="dropdown">
                <button class="dropbtn">Log 
                <i class="fa fa-caret-down"></i>
                </button>
                <div class="dropdown-content">
                    <a href="#">Report</a>
                    <a href="#">Search</a>
                </div>
            </div> 
        </div>
    </nav>';

    require_once("conndb.php");

    if(isset($_POST['uID'])){
        try{            
            $sql_query = "SELECT `uName`,`uPermissions` FROM `user` WHERE `uID`=?";            
            $stmt = $dbLink->prepare($sql_query);
    
            $stmt->execute(array($_POST['uID']));
            if($stmt->rowCount() > 0){
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<p align='right'>Signed in as {$data['uName']}</p>";
                //show nav
                if(preg_match('/A/',$data['uPermissions']))
                    echo $adminlink; 
                else 
                    echo $userlink;
              
                $permission = explode(',', $data['uPermissions']);
                foreach($permission as $value){
                    if($value=='R') $str.= 'Read ';
                    elseif($value=='W') $str.= 'Write ';
                    elseif($value=='D') $str.= 'Delete ';
                    elseif($value=='A') $str.= 'Administer';
                }

                echo "<p><table border='2' cellpadding='10' align='center'><caption>LOGIN</caption>";
                echo "<tr><td>Name</td><td>{$data['uName']}</td></tr>";
                echo "<tr><td>Permissions</td><td>$str</td></tr></table>";
                
                $sql_update = sprintf("UPDATE `user` SET `uLogin`=\"%s\" WHERE `uID`=?", date("Y-m-d H:i:s"));
                $stmtUpdate = $dbLink->prepare($sql_update);            
                $stmtUpdate->execute(array($_POST['uID']));

                $_SESSION['uID'] = $_POST['uID'];
                $_SESSION['uName'] = $data['uName'];
                $_SESSION['uPermissions'] = $data['uPermissions'];

            }else{
                echo "<p align='right'>Sign in</p>";
                echo $emptyLink;
                echo "<h1 class=error>No permission or no this user</h1>";
                session_destroy();
            }
            
        }catch(PDOException $e){
            echo $e->getMessage();
        }

        $dbLink = NULL;
    }else{
        session_destroy();
        echo "<p align='right'>Sign in</p>";
        echo $emptyLink;
        echo<<<HTML
            <h2 align="center"> SIGN IN</h2>
            <form method='post' action=''>
            <table border='0' cellpadding='10' align='center'>
                <tr>
                    <th>ID</th>
                    <td>
                        <input type='text' id='uID' name='uID' value='' minlengh="8" maxlengh="8" pattern="[0-9]{8}" title="Enter 8 digits" required>
                        <input type='submit' value='Sign in' class='button'>
                    </td>
                </tr>    
            </table>                
            </form>
HTML;
    }

readfile('_footer.html');
?>