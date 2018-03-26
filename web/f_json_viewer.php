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
    $sql_select = "SELECT info.report FROM `info` WHERE info.no=?";
    $stmt = $dbLink->prepare($sql_select);
    $stmt->execute(array($_GET['no']));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $file = sprintf("%s/%s.json", $data['report'], $_GET['file']);
    $dbLink = NULL;
}catch(PDOException $e){
    echo $e->getMessage(); die();
}
?>

    <script>
        $(function() {
        $('#btn-json-viewer').click(function() {
            try {
            var input = eval('(' + $('#json-input').val() + ')');
            }
            catch (error) {
            return alert("Cannot eval JSON: " + error);
            }
            var options = {
            collapsed: $('#collapsed').is(':checked')
            };
            $('#json-renderer').jsonViewer(input, options);
        });

        // Display JSON on load
        $('#btn-json-viewer').click();
        });
    </script>
    <h2 id="file"><?php echo basename($_GET['file']);?></h2>
    <textarea id="json-input" autocomplete="off"><?php echo file_get_contents($file);?></textarea><p>
    <label id="lab-json-viewer"><input type="checkbox" id="collapsed" checked/>Collapse nodes</label>
    <button id="btn-json-viewer" title="run jsonViewer()">Transform to HTML</button>
    <pre id="json-renderer"></pre>

<?php readfile('_footer.html');?>