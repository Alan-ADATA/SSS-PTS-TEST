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

GetParaInfo();

$report_folder = $GLOBALS[para][report];
$run = $GLOBALS[para][run];
$file_tmp = $GLOBALS[para][tmp];
$log = $GLOBALS[para][log];

function GetParaInfo()
{
    global $para;
    $infoFile = 'parameter.json';  
    if(file_exists($infoFile)){
        $info = json_decode(file_get_contents($infoFile));
        $para['test'] = $info->test;
        $para['report'] = $info->report;
        $para['tmp'] = $para['report'].$info->tmp;
        $para['run'] = $para['test'].$info->run;
        $para['log'] = $info->log;
    }
}
?>