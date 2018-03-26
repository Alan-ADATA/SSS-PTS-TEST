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
        $sql_query = "SELECT * FROM `user`";            
        $stmt = $dbLink->prepare($sql_query);
        $stmt->execute();
        $total = $stmt->rowCount();

    }catch(PDOException $e){
        echo $e->getMessage();
    }

    readfile('_header.html');
    include_once("nav.php");
?>

<div class="main" align='center' cellpadding='10'>    
    <form metdod='post' action='f_delfile.php'>
        <table>
            <tr>
                <td><h2>If testing is not normal completed, delete the check file.</h2></td>
                <td><input type='submit' value='Delete' class='button'></td>
            </tr>
        </table>
    </form>
    
</div>
</p>
<h1 align='center'>USER MANAGEMENT</h1>
<p align='center'>Total&nbsp;<?php echo $total;?>&nbsp;datas&nbsp;&nbsp;<a href='u_addmember.php'>Add a new user</a></p>

<table border='2' cellpadding='10' align='center'>
    <tr>
        <th>ID</th> <th>NAME</th> <th>PERMISSION</th> <th>LAST LOGIN</th> <th>STATUS</th> <th>EDIT</th>
    </tr>

<?php
    try{
        while($data = $stmt->fetch(PDO::FETCH_ASSOC)){
            $data['uEnable'] = ($data['uEnable']==1)?'Enable':'Disable';
            $permission = explode(',', $data['uPermissions']);
            $pstr = '';
            foreach($permission as $value){                
                if($value=='R') $pstr.= 'Read ';
                elseif($value=='W') $pstr.= 'Write ';
                elseif($value=='D') $pstr.= 'Delete ';
                elseif($value=='A') $pstr.= 'Administer';
            }
            // show data
            echo "
            <tr>
                <td>{$data['uID']}</td> <td>{$data['uName']}</td> <td>{$pstr}</td> <td>{$data['uLogin']}</td> <td>{$data['uEnable']}</td>
                <td><a href='u_updatemember.php?id={$data['uID']}'>Update</a>&nbsp;
                    <a href='u_delmember.php?id={$data['uID']}'>Delete</a></td>
            </tr>
            ";
        }

        echo "</table>";

        $dbLink = NULL;

    }
    catch(PDOException $e){
        echo $e->getMessage();
    }

?>
<?php readfile('_footer.html');?>