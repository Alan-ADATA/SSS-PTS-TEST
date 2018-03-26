<?php
// Copyright 2014 CloudHarmony Inc.
// 
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
// 
//     http://www.apache.org/licenses/LICENSE-2.0
// 
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//
// This file is modified by ADATA Technology Co., Ltd. on 2018.

/**
 * Block storage test implementation for the Demand Intensity / Response Time 
 * Histogram test
 */
class BlockStorageTestDirth extends BlockStorageTest {

  const DIRTH_MAX_ROUND = 25;
  const BLOCK_STORAGE_TEST_DIRTH_PRECONDITION_INTERVALS = 30;
  const DURATION = 5;
  private $plotTitle = "<h1 style=\"text-align: center;\">%s</h1>"; 

  /**
   * Constructor is protected to implement the singleton pattern using 
   * the BlockStorageTest::getTestController static method
   * @param array $options the test options
   */
  protected function BlockStorageTestDirth($options, $rw=NULL) {

    if ($rw === NULL) {
      foreach(array('dirth1','dirth2','dirth3') as $rw) { 
          $this->subtests[$rw] = new BlockStorageTestDirth($options, $rw);
          $this->subtests[$rw]->test = 'dirth';
          $this->subtests[$rw]->verbose = isset($options['verbose']) && $options['verbose'];
          $this->subtests[$rw]->controller =& $this;
      }
    }
    else {
      $this->rw = $rw;
      $this->options = $options;
      $this->test = 'dirth';
      foreach($options['target'] as $target) {
        $device = BlockStorageTest::getDevice($target);
        $device == $target ? $this->deviceTargets = TRUE : $this->volumeTargets = TRUE;
        break;
      }
    }
  }

  /**
   * overrides the parent method in order to write jason files for 0/100 
   * and RW1 workloads separately
   */
  public function generateJson($dir=NULL, $suffix=NULL) {
    $generated = FALSE;
    if ($this->rw !== NULL) return parent::generateJson($dir, $this->rw);
    else foreach(array_keys($this->subtests) as $rw) $generated = $this->subtests[$rw]->generateJson($dir);
    return $generated;
  }

  /**
   * this sub-class method should return the content associated with $section 
   * using the $jobs given (or all jobs in $this->fio['wdpc']). Return value 
   * should be HTML that can be imbedded into the report. The HTML may include 
   * an image reference without any directory path (e.g. <img src="iops.png>")
   * returns NULL on error, FALSE if not content required
   * @param string $section the section identifier provided by 
   * $this->getReportSections()
   * @param array $jobs all fio job results occuring within the steady state 
   * measurement window. This is a hash indexed by job name
   * @param string $dir the directory where any images should be generated in
   * @return string
   */
  protected function getReportContent($section, $jobs, $dir) {
    $content = NULL;
    switch($section){
      case 'pre_iops'://P1
        if($this->rw == 'dirth1'){  
          
          $label = sprintf("Pre-Writes, BS=RND %s", $this->ShowBlockSize($this->options['dirth_bs']));
          foreach(array_keys($this->fio['wdpc']) as $i) {
            $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
            if ($job && preg_match('/^x([0-9]+)\-0_100\-rand\-n01/', $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0]['write']['iops'])) {
              $round = $m[1]*1;
              $iops = $this->fio['wdpc'][$i]['jobs'][0]['write']['iops'];
              if (!isset($coords[$label])) $coords[$label] = array();
              $coords[$label][] = array($round, $iops);
            }
          }

          $title = 'P1 TC32-QD32, IOPS vs Round';
          $content = sprintf($this->plotTitle, $title); 
          if ($coords) $content .= $this->generateLineChart($dir, $section, $coords, 'Round', 'IOPS', NULL, array('xMin' => 0, 'yMin' => 0));      
        }
        
        break;

      case 'pre_steady_state'://P2
      case 'dv_steady_state'://P5
        if($this->rw == 'dirth1' || $this->rw == 'dirth2'){  
          $iops = array();

          $str = ($section == 'pre_steady_state') ? '/^x([0-9]+)\-0_100\-rand\-n01/': '/^x([0-9]+)\-RW1\-rand\-TC32\-QD32/';          
          
          foreach(array_keys($jobs) as $job) {
            if (preg_match($str, $job, $m) && isset($jobs[$job]['write']['iops'])) {
              if (!isset($coords['IOPS'])) $coords['IOPS'] = array();
              $round = $m[1]*1;
              $coords['IOPS'][] = array($round, $jobs[$job]['write']['iops']);
              $iops[$round] = $jobs[$job]['write']['iops'];
            }
          }

          if (isset($coords['IOPS'])) {
            ksort($iops);
            $keys = array_keys($iops);
            $first = $keys[0];
            $last = $keys[count($keys) - 1];
            $avg = round(array_sum($iops)/count($iops));
            $coords['Average'] = array(array($first, $avg), array($last, $avg));
            $coords['110% Average'] = array(array($first, round($avg*1.1)), array($last, round($avg*1.1)));
            $coords['90% Average'] = array(array($first, round($avg*0.9)), array($last, round($avg*0.9)));
            $coords['Slope'] = array(array($first, $iops[$first]), array($last, $iops[$last]));
            
            $settings = array();
            $settings['lines'] = array(1 => "lt 1 lc rgb \"blue\" lw 3 pt 5",
                                      2 => "lt 1 lc rgb \"black\" lw 3 pt -1",
                                      3 => "lt 2 lc rgb \"green\" lw 3 pt -1",
                                      4 => "lt 2 lc rgb \"purple\" lw 3 pt -1",
                                      5 => "lt 4 lc rgb \"red\" lw 3 pt -1 dashtype 2");
            $settings['xMin'] = '10%';
            $settings['yMin'] = '20%';

            $title = ($section == 'pre_steady_state') ? 'P2 PC Steady State Check TC32-QD32' : 'P5 Demand Variation Steady State Check TC32-QD32';
            $content = sprintf($this->plotTitle, $title); 
            $content .= $this->generateLineChart($dir, $section, $coords, 'Round', 'IOPS', NULL, $settings);
          }
        }
        
        break;      
      
      case 'between_round'://P3
        if($this->rw == 'dirth2'){  
          
          $label = sprintf("Between Round Pre-Writes, BS=%s", $this->ShowBlockSize($this->options['dirth_bs']));
          foreach(array_keys($this->fio['wdpc']) as $i) {
            $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
            if ($job && preg_match('/^x([0-9]+)\-0_100\-rand\-prewrite/', $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0]['write']['iops'])) {
              $time = ($m[1]*1 - 1)*49 + self::DURATION;
              $iops = $this->fio['wdpc'][$i]['jobs'][0]['write']['iops'];
              if (!isset($coords[$label])) $coords[$label] = array();
              $coords[$label][] = array($time, $iops);
            }
          }
  
          $settings = array(
                            'xMin' => 0,
                            'yMin' => 0,
                            'pointColor' => 'blue');
          
          $title = 'P3 Between Round Pre-Writes';
          $content = sprintf($this->plotTitle, $title);                            
          $content .= $this->generatePointChart($dir, $section, $coords, 'Time (Minutes)', 'IOPS', NULL, $settings);          
        }
        
        break;

      case 'dv_iops'://P4
        if($this->rw == 'dirth2'){  

          foreach(array_keys($jobs) as $job) {
            $label = NULL;
  
            if (preg_match('/^x([0-9]+)\-RW1\-rand\-TC32\-QD32/', $job, $m) && isset($jobs[$job]['write']['iops'])) {            
              $label = 'TC=32,QD=32';            
            }
            elseif(preg_match('/^x([0-9]+)\-RW1\-rand\-TC32\-QD16/', $job, $m) && isset($jobs[$job]['write']['iops'])) {            
              $label = 'TC=32,QD=16';
            }
            elseif(preg_match('/^x([0-9]+)\-RW1\-rand\-TC32\-QD8/', $job, $m) && isset($jobs[$job]['write']['iops'])) {            
              $label = 'TC=32,QD=8';
            }
            elseif(preg_match('/^x([0-9]+)\-RW1\-rand\-TC32\-QD6/', $job, $m) && isset($jobs[$job]['write']['iops'])) {            
              $label = 'TC=32,QD=6';
            }
            elseif(preg_match('/^x([0-9]+)\-RW1\-rand\-TC32\-QD4/', $job, $m) && isset($jobs[$job]['write']['iops'])) {            
              $label = 'TC=32,QD=4';
            }
            elseif(preg_match('/^x([0-9]+)\-RW1\-rand\-TC32\-QD2/', $job, $m) && isset($jobs[$job]['write']['iops'])) {            
              $label = 'TC=32,QD=2';
            }
            elseif(preg_match('/^x([0-9]+)\-RW1\-rand\-TC32\-QD1/', $job, $m) && isset($jobs[$job]['write']['iops'])) {            
              $label = 'TC=32,QD=1';
            }
            
            if($label !== NULL){
              $round = $m[1]*1;
              if (!isset($coords[$label])) $coords[$label] = array();
              $coords[$label][] = array($round, $jobs[$job]['write']['iops']);
            }  
          }
  
          $settings['lines'] = array(1 => "lt 2 lc rgb \"#004B97\" lw 3 pt 5",
                                     2 => "lt 2 lc rgb \"#AE0000\" lw 3 pt 5",
                                     3 => "lt 2 lc rgb \"#009100\" lw 3 pt 5",
                                     4 => "lt 2 lc rgb \"#921AFF\" lw 3 pt 5",
                                     5 => "lt 2 lc rgb \"#0080FF\" lw 3 pt 5",
                                     6 => "lt 2 lc rgb \"#FF5809\" lw 3 pt 5",
                                     7 => "lt 2 lc rgb \"#9999CC\" lw 3 pt 5");

          $settings['xMin'] = $this->subtests['dirth2']->wdpcComplete - 5;
          $settings['yMin'] = 0;

          $title = 'P4 TC=32 IOPS vs Round, All QD';
          $content = sprintf($this->plotTitle, $title);
          $content .= $this->generateLineChart($dir, $section, $coords, 'Round', 'IOPS', NULL, $settings);          
        }
 
        break;

      case 'demand_variation'://P6
        if($this->rw == 'dirth2'){
          $str = sprintf("/^x%d\-RW1\-rand\-TC([0-9]+)\-QD([0-9]+)/", $this->subtests['dirth2']->wdpcComplete);
          foreach(array_keys($jobs) as $job){
            $label = NULL;
            $xTic = NULL;
            if(preg_match($str, $job, $m) && isset($jobs[$job]['write']['iops'])){

              $tc = $m[1]*1;
              $qd = $m[2]*1;

              if($tc == 32) $label = 'TC=32';
              elseif($tc == 16) $label = 'TC=16';
              elseif($tc == 8) $label = 'TC=8';
              elseif($tc == 6) $label = 'TC=6';
              elseif($tc == 4) $label = 'TC=4';
              elseif($tc == 2) $label = 'TC=2';
              elseif($tc == 1) $label = 'TC=1';
              
              /* gunplot x軸刻度用 "1"0, "2"2, "4"4, "8"6, "16"8, "32"10 ("A"B => A:顯示標籤 B:實際值)
              *  QD對應x軸 1=>0 2=>2 4=>4 6=>5 8=>6 16=>8 32=>10
              */
              if($qd == 1)  $xTic = 0;
              elseif($qd == 2)  $xTic = 2;
              elseif($qd == 4)  $xTic = 4;
              elseif($qd == 6)  $xTic = 5;
              elseif($qd == 8)  $xTic = 6;
              elseif($qd == 16)  $xTic = 8;
              elseif($qd == 32)  $xTic = 10;

              if($label !== NULL && $xTic !== NULL){
                if (!isset($coords[$label])) $coords[$label] = array();
                $coords[$label][] = array($xTic, $jobs[$job]['write']['iops']);
  
                if(count($coords[$label]) == 7) {
                    $coords[$label] = array_reverse($coords[$label]);
                }
              }              
            }
          }

          $settings['lines'] = array(1 => "lt 2 lc rgb \"#0000C6\" lw 3 pt 1",
                                     2 => "lt 2 lc rgb \"#AE0000\" lw 3 pt 2",
                                     3 => "lt 2 lc rgb \"#009100\" lw 3 pt 3",
                                     4 => "lt 2 lc rgb \"#921AFF\" lw 3 pt 4",
                                     5 => "lt 2 lc rgb \"#46A3FF\" lw 3 pt 5",
                                     6 => "lt 2 lc rgb \"#FF5809\" lw 3 pt 6",
                                     7 => "lt 2 lc rgb \"#9999CC\" lw 3 pt 7");
          $settings['xMin'] = 0;
          $settings['xMax'] = 10;
          $settings['xTics'] = 5;
          $settings['yMin'] = 0;
          $settings['usrxTicLabel'] = 'set xtics ("1"0,"2"2,"4"4,"8"6,"16"8,"32"10)';

          $title = sprintf("P6 RND %s, RW=%d/%d Demand Variation", $this->ShowBlockSize($this->options['dirth_bs']), $this->options['dirth_rw'], 100-$this->options['dirth_rw']);
          $content = sprintf($this->plotTitle, $title);
          $content .= $this->generateLineChart($dir, $section, $coords, 'Queue Depth', 'IOPS', NULL, $settings);        
        }
 
        break;

      case 'demand_intensity'://P7
        if($this->rw == 'dirth2'){
          $str = sprintf("/^x%d\-RW1\-rand\-TC([0-9]+)\-QD([0-9]+)/", $this->subtests['dirth2']->wdpcComplete);
          foreach(array_keys($jobs) as $job){
            if(preg_match($str, $job, $m)){
              $tc = $m[1]*1;
              $qd = $m[2]*1;

              if($tc == 32) $label = 'TC=32';
              elseif($tc == 16) $label = 'TC=16';
              elseif($tc == 8) $label = 'TC=8';
              elseif($tc == 6) $label = 'TC=6';
              elseif($tc == 4) $label = 'TC=4';
              elseif($tc == 2) $label = 'TC=2';
              elseif($tc == 1) $label = 'TC=1';

              $iops = $jobs[$job]['write']['iops'];
              $art = $jobs[$job]['write']['lat']['mean'] / 1000; //Average Response Time (ms)

              if (!isset($coords[$label])){
                $coords[$label] = array();
              }                

              $coords[$label][] = array($iops, $art);

              if(count($coords[$label]) == 7) {
                $coords[$label] = array_reverse($coords[$label]);
              }
            }
          }

          $settings['lines'] = array(1 => "lt 2 lc rgb \"#0000C6\" lw 3 pt 1",
                                     2 => "lt 2 lc rgb \"#AE0000\" lw 3 pt 2",
                                     3 => "lt 2 lc rgb \"#009100\" lw 3 pt 3",
                                     4 => "lt 2 lc rgb \"#921AFF\" lw 3 pt 4",
                                     5 => "lt 2 lc rgb \"#46A3FF\" lw 3 pt 5",
                                     6 => "lt 2 lc rgb \"#FF5809\" lw 3 pt 6",
                                     7 => "lt 2 lc rgb \"#9999CC\" lw 3 pt 7");
          $settings['xMin'] = 0;
          $settings['yLogscale'] = TRUE;
          $settings['yFloatPrec'] = 2;

          $title = sprintf("P7 RND %s RW=%d/%d Demand Intensity", $this->ShowBlockSize($this->options['dirth_bs']), $this->options['dirth_rw'], 100-$this->options['dirth_rw']);
          $content = sprintf($this->plotTitle, $title);
          $content .= $this->generateLineChart($dir, $section, $coords, 'IOPS', 'Time (ms)', NULL, $settings); 
        }
        break;

      case 'system_cpu'://P8 3D
        if($this->rw == 'dirth2'){  
          $str = sprintf("/^x%d\-RW1\-rand\-TC([0-9]+)\-QD([0-9]+)/", $this->subtests['dirth2']->wdpcComplete);
          foreach(array_keys($jobs) as $job){
            if(preg_match($str, $job, $m) && isset($jobs[$job]['sys_cpu'])){
              $tc = $m[1]*1;
              $qd = $m[2]*1;              
              $data[$tc][$qd] = $jobs[$job]['sys_cpu'];
  
              if(count($data[$tc]) == 7)
                ksort($data[$tc]);
                         
            }
          }
          ksort($data);
  
          $qdArray = array('1','2','4','6','8','16','32');
          $series = array();
          $settings = array('xAxis' => array('categories' => $qdArray, 'title' => array('text' => 'Queue Depth')),
                            'yAxis' => array('labels' => array('format' => '{value:,.1f}'), 'min' => 0, 'title' => array('text' => 'System CPU Utilization (%)')));
          $stack = 0;
          foreach($data as $tc => $value){
            $x = 0;
            foreach($data[$tc] as $qd => $cpu){              
              if (!isset($series[$stack])) $series[$stack] = array('data' => array(), 'name' => sprintf("TC=%d",$tc), 'stack' => $stack);
              $series[$stack]['data'][] = array('x' => $x++, 'y' => $cpu);
            }
            $stack++;
          }
  
          $title = 'P8 System CPU Utilization During Demand Variation Test';
          $content = sprintf($this->plotTitle, $title);
          $content .= $this->generate3dChart($section, $series, $settings, 'Thread Count');          
        }

        break;

      case 'max_iops_pre_writes'://P9
      case 'mid_iops_pre_writes'://P11
      case 'min_iops_pre_writes'://P13
        if($this->rw == 'dirth3'){
          $type = strtoupper(substr($section,0,3));
          $str = sprintf("/^x([0-9]+)\-0_100\-rand\-n([0-9]+)\-TC[0-9]+\-QD[0-9]+\-%s/",$type);
          $label = 'IOPS';
          
          foreach(array_keys($this->fio['wdpc']) as $i) {
            $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
            if ($job && preg_match($str, $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0]['write']['iops'])) {
              $time = $m[2]*1;
              
              $iops = $this->fio['wdpc'][$i]['jobs'][0]['write']['iops'];
              if (!isset($coords[$label])) $coords[$label] = array();
              $coords[$label][] = array($time, $iops);
            }
          }

          $title = preg_match('/^max/',$section)? 'P9 MaxIOPS Pre-Writes':(preg_match('/^mid/',$section)?'P11 MidIOPS Pre-Writes':'P13 MinIOPS Pre-Writes');
          $content = sprintf($this->plotTitle, $title);
          if ($coords) $content .= $this->generateLineChart($dir, $section, $coords, 'Time (Minutes)', 'IOPS', NULL, array('xMin' => 0, 'yMin' => 0));      
        }
        break;

      case 'max_iops_histogram'://P10
      case 'mid_iops_histogram'://P12
      case 'min_iops_histogram'://P14
        if($this->rw == 'dirth3'){          
          $type = substr($section,0,3);

          global $tcqdArray;
          if($type == 'max'){
            if(isset($tcqdArray[$this->test]['MAX'])){
              $data = explode('_', $tcqdArray[$this->test]['MAX']);
              $num = $data[1];
            }
          }elseif($type == 'mid'){
            if(isset($tcqdArray[$this->test]['MID'])){
              $data = explode('_', $tcqdArray[$this->test]['MID']);
              $num = $data[1];
            }
          }else{
            if(isset($tcqdArray[$this->test]['MIN'])){
              $data = explode('_', $tcqdArray[$this->test]['MIN']);
              $num = $data[1];
            }
          }

          $fdir = $this->options['output'];     
          $maxTime = 0;

          for($n=1; $n<=10; $n++){  //10 mins
            for($x=1; $x<=$num; $x++){  //QD number will decide how many logs
              $fileName = sprintf("%s/dirth-fio-lat-%s-%d_lat.%d.log", $fdir, $type, $n, $x);

              if(file_exists($fileName)){
                $fp = file($fileName);            
                foreach($fp as $str){
                    $data = explode(",", $str);
                    $time = round((trim($data[1]) /1000), 0);
                    $maxTime = (round((trim($data[1]) /1000), 1) > $maxTime)? round((trim($data[1]) /1000), 1) : $maxTime;

                    $count[$time] = (isset($count[$time]))? ++$count[$time] : 1;        
                }
              }
            }
          }
          ksort($count);

          //delete log files
          $fileName = sprintf("%s/dirth-fio-lat-%s*.log", $fdir, $type);                
          exec("rm -f $fileName");

          //write job metrics to output file
          $key = sprintf("%s IOPS Response Time", $type);
          $metrics[$key] = $count;
          $file = sprintf('%s/%s.json', $fdir, $this->test);
          // output file already exists - merge results
          if (file_exists($file) && ($existing = json_decode(file_get_contents($file), TRUE))) $metrics = array_merge($existing, $metrics);
          if ($fp = fopen($file, 'w')) {
            fwrite($fp, json_encode($metrics));
            fclose($fp);
            print_msg(sprintf('Successfully wrote job metrics to output file %s for test %s', $file, $this->test), $this->verbose, __FILE__, __LINE__);
          }
          else print_msg(sprintf('Unable to write job metrics to output file %s for test %s', $file, $this->test), $this->verbose, __FILE__, __LINE__, TRUE);

          //Each point on the x-axis must have a value to draw the correct histogram
          for($time=0; $time<=max(array_keys($count)); $time++){
            $coords[$time] = (array_key_exists($time, $count)) ? $count[$time] : 0;
          }
          
          $settings['xMax'] = $time + 20;

          if(preg_match('/^max/',$section)){
            $title = sprintf("P10 Max IOPS Response Time Histogram, MRT=%.1f ms",$maxTime);
          }elseif(preg_match('/^mid/',$section)){
            $title = sprintf("P12 Mid IOPS Response Time Histogram, MRT=%.1f ms",$maxTime);
          }else{
            $title = sprintf("P14 Min IOPS Response Time Histogram, MRT=%.1f ms",$maxTime);
          }

          $content = sprintf($this->plotTitle, $title);
          if ($coords) $content .= $this->generateHistogram($dir, $section, $coords, NULL, $settings);        
          
        }
        break;
    }

    return $content;
  }

  /**
   * this sub-class method should return a hash identifiying the sections 
   * associated with the test report. The key in the hash should be the 
   * section identifier, and the value the section title
   * @return array
   */
  protected function getReportSections(){
    return array(
      'pre_iops' => 'Pre Conditioning IOPS Plot',
      'pre_steady_state' => 'Pre Conditioning Steady State Plot',
      'between_round' => 'Between Round Pre Writes',
      'dv_iops' => 'DV IOPS Plot, TC=Tracking',
      'dv_steady_state' => 'DV Steady State Plot, Tracking Variable',
      'demand_variation' => 'Demand Variation Plot',
      'demand_intensity' => 'Demand Intensity Plot',
      'system_cpu' => 'System CPU Utilization Plot',
      'max_iops_pre_writes' => 'Max IOPS Pre Writes',
      'max_iops_histogram' => 'Max IOPS Histogram',
      'mid_iops_pre_writes' => 'Mid IOPS Pre Writes',
      'mid_iops_histogram' => 'Mid IOPS Histogram',
      'min_iops_pre_writes' => 'Min IOPS Pre Writes',
      'min_iops_histogram' => 'Min IOPS Histogram');
  }

  /**
   * this sub-class method should return a hash of setup parameters - these are
   * label/value pairs displayed in the bottom 8 rows of the Set Up Parameters 
   * columns in the report page headers
   * @return array
   */
  protected function getSetupParameters(){
    if (isset($this->controller)) return $this->controller->getSetupParameters();
    else {
      return array(        
        'Pre Condition 1' => 'RND',
        '&nbsp;&nbsp;R/W %' => '0/100',
        '&nbsp;&nbsp;TOIO - TC/QD' => 'TC 32 / QD 32',
        '&nbsp;&nbsp;SS Rounds' => sprintf('%d - %d', $this->subtests['dirth1']->wdpcComplete - 4, $this->subtests['dirth1']->wdpcComplete),
        'Inter-Round Pre W' => 'Inter Rnd Pre Write',
        '&nbsp;&nbsp;R/W % ' => '0/100',
        '&nbsp;&nbsp;TOIO - TC/QD ' => 'TC 32 / QD 32',
        '&nbsp;&nbsp;Duration' => sprintf("%d Minutes", self::DURATION)
      );
    }
  }
  
  /**
   * this sub-class method should return the subtitle for a given test and 
   * section
   * @param string $section the section identifier to return the subtitle for
   * @return string
   */
  protected function getSubtitle($section){
    return sprintf("DIRTH - OLTP - RND %s RW = %d:%d ", $this->ShowBlockSize($this->options['dirth_bs']), $this->options['dirth_rw'], 100-$this->options['dirth_rw']);
  }
  
  /**
   * this sub-class method should return a hash of test parameters - these are
   * label/value pairs displayed in the bottom 8 rows of the Test Parameters 
   * columns in the report page headers
   * @return array
   */
  protected function getTestParameters(){
    if (isset($this->controller)) return $this->controller->getTestParameters();
    else {
      global $tcqdArray;

      if(isset($tcqdArray[$this->test]['MAX'])){
        $data = explode('_', $tcqdArray[$this->test]['MAX']);
        $max = sprintf("TC %s/QD %s", $data[0], $data[1]);
      }

      if(isset($tcqdArray[$this->test]['MID'])){
        $data = explode('_', $tcqdArray[$this->test]['MID']);
        $mid = sprintf("TC %s/QD %s", $data[0], $data[1]);
      }

      return array(
        'Test Stimulus 1' => sprintf("RND %s", $this->ShowBlockSize($this->options['dirth_bs'])),
        '&nbsp;&nbsp;R/W %' => sprintf("%d / %d", $this->options['dirth_rw'], 100-$this->options['dirth_rw']),
        '&nbsp;&nbsp;TC/QD' => 'TC/QD from 1-32',
        '&nbsp;&nbsp;TC & QD Loops' => 'High to Low TOIO',
        'Min IOPS Point' => 'TC 1/QD 1',
        'Mid IOPS Point' => isset($mid)? $mid:'',
        'Max IOPS Point' => isset($max)? $max:''
      );
    }
  }
  
  /**
   * This method should return job specific metrics as a single level hash of
   * key/value pairs
   * @return array
   */
  protected function jobMetrics(){}
  
      
  /**
   * Performs workload dependent preconditioning - this method must be 
   * implemented by sub-classes. It should return one of the following 
   * values:
   *   TRUE:  preconditioning successful and steady state achieved
   *   FALSE: preconditioning successful but steady state not achieved
   *   NULL:  preconditioning failed
   * @return boolean
   */
  public function wdpc() {
    $status = NULL;

    if ($this->rw !== NULL) {
      $rw = $this->rw;
      $max = self::DIRTH_MAX_ROUND;

      if ($rw == 'dirth1') {  
        /**
         * Test Flow 2.2 use R/W = 0/100
         * step 1: TC32/QD32 max 25 rounds, every round 30 minutes
         * use 1. 31 . 61. 91...to get steady state
         */

        print_msg(sprintf('Initiating workload dependent preconditioning and steady state for DIRTH test'), $this->verbose, __FILE__, __LINE__);
        
        $ssMetrics = array();
    
        for($x=1; $x<=$max; $x++) {
    
          for($n=1; $n<=self::BLOCK_STORAGE_TEST_DIRTH_PRECONDITION_INTERVALS; $n++) {
            $name = sprintf('x%d-0_100-rand-n%02d', $x, $n);
            print_msg(sprintf('Starting %d sec DIRTH rand write preconditioning round %d of %d, test %d of %d [name=%s]', $this->options['wd_test_duration'], $x, $max, $n, self::BLOCK_STORAGE_TEST_DIRTH_PRECONDITION_INTERVALS, $name), $this->verbose, __FILE__, __LINE__);
            
            $params = array('rw' => 'randwrite', 'name' => $name, 
                            'runtime' => $this->options['wd_test_duration'], 
                            'time_based' => FALSE, 'iodepth'=> 32, 'numjobs'=> 32,
                            'blocksize' => $this->options['dirth_bs']);

            if ($fio = $this->fio($params, 'wdpc')) {
              print_msg(sprintf('Test %s was successful', $name), $this->verbose, __FILE__, __LINE__);
              $results = $this->fio['wdpc'][count($this->fio['wdpc']) - 1];
            }else {
              print_msg(sprintf('Test %s failed', $name), $this->verbose, __FILE__, __LINE__, TRUE);
              break;
            }
            
            // add steady state metric
            if ($results && $n==1) {
              $iops = $results['jobs'][0]['write']['iops'];
              print_msg(sprintf('Added IOPS metric %d from preconditioning round %d of %d for DIRTH steady state verification', $iops, $x, $max), $this->verbose, __FILE__, __LINE__);
              $ssMetrics[$x] = $iops;

              // check for steady state at rounds 5+
              if ($x >= 5) {
                $metrics = array();
                for($i=4; $i>=0; $i--){
                  $metrics[$x-$i] = $ssMetrics[$x-$i];
                } 
                print_msg(sprintf('DIRTH preconditioning test %d of %d complete and >= 5 rounds finished - checking if steady state has been achieved using IOPS metrics [%s],[%s]', $x, $max, implode(',', array_keys($metrics)), implode(',', $metrics)), $this->verbose, __FILE__, __LINE__);
                
                if ($this->isSteadyState($metrics, $x)) {
                  print_msg(sprintf('DIRTH steady state achieved - testing will stop'), $this->verbose, __FILE__, __LINE__);
                  $status = TRUE;
                  break;
                } else{ 
                  print_msg(sprintf('DIRTH steady state NOT achieved'), $this->verbose, __FILE__, __LINE__);
                }
                // end of the line => last test round and steady state not achieved
                if ($x == $max && $status === NULL) $status = FALSE;
              }
            }   
            if (!$results || $status !== NULL) break;
          }   
          if (!$results || $status !== NULL) break;
        }

        $this->wdpcComplete = $x;
        $this->subtests['dirth1']->wdpcComplete = $x;
        $this->wdpcIntervals = self::BLOCK_STORAGE_TEST_DIRTH_PRECONDITION_INTERVALS;
        $this->wdpc = $status;

      }elseif($rw == 'dirth2'){  
        /**
         * Test Flow 3. 
         * step 1: TC32/QD32 pre-write for 5 minutes use R/W = 0/100
         * step 2: composite TC/QD (49 composites) use R/W = $this->options['dirth_rw']
         */

        print_msg(sprintf('DIRTH preconditioning complete - beginning wait state test segments'), $this->verbose, __FILE__, __LINE__);

        $tcArray = array(32,16,8,6,4,2,1);
        $qdArray = array(32,16,8,6,4,2,1);
        $composite = count($tcArray) * count($qdArray);
        $ssMetrics = array();
        $preWriteTime = $this->options['wd_test_duration'] * self::DURATION;

        for($x=1; $x<=$max; $x++){

          // TC32/QD32 pre-write for 5 minutes
          $name = sprintf('x%d-0_100-rand-prewrite', $x);
          print_msg(sprintf('Starting %d sec DIRTH rand write preconditioning round %d of %d [name=%s]', $preWriteTime, $x, $max, $name), $this->verbose, __FILE__, __LINE__);
          
          $params = array('rw' => 'randwrite', 'name' => $name,
                          'runtime' => $preWriteTime, 
                          'time_based' => FALSE, 'iodepth'=> 32, 'numjobs'=> 32,
                          'blocksize' => $this->options['dirth_bs']);
                          
          if ($fio = $this->fio($params, 'wdpc')) {
            print_msg(sprintf('Test %s was successful', $name), $this->verbose, __FILE__, __LINE__);
            $results = $this->fio['wdpc'][count($this->fio['wdpc']) - 1];
          }else {
            print_msg(sprintf('Test %s failed', $name), $this->verbose, __FILE__, __LINE__, TRUE);
            break;
          }

          // composite TC/QD   
          $n = 0;       
          foreach($tcArray as $tc){            
            
            foreach($qdArray as $qd){
              $name = sprintf('x%d-RW1-rand-TC%d-QD%d-n%d', $x, $tc, $qd, ++$n);
              print_msg(sprintf('Starting %d sec DIRTH rand write round %d of %d, test %d of %d [name=%s]', $this->options['wd_test_duration'], $x, $max, $n, $composite, $name), $this->verbose, __FILE__, __LINE__);
              
              $params = array('rw' => 'randrw', 'rwmixread' => $this->options['dirth_rw'], 'name' => $name, 
                              'runtime' => $this->options['wd_test_duration'], 
                              'time_based' => FALSE, 'iodepth'=> $tc, 'numjobs'=> $qd,
                              'blocksize' => $this->options['dirth_bs']);
                              
              if ($fio = $this->fio($params, 'wdpc')) {
                print_msg(sprintf('Test %s was successful', $name), $this->verbose, __FILE__, __LINE__);
                $results = $this->fio['wdpc'][count($this->fio['wdpc']) - 1];

              }else {
                print_msg(sprintf('Test %s failed', $name), $this->verbose, __FILE__, __LINE__, TRUE);
                break;
              }

              // add steady state metric
              if ($results && $tc==32 && $qd==32) {
                $iops = $results['jobs'][0]['write']['iops'];
                print_msg(sprintf('Added IOPS metric %d from round %d of %d for DIRTH steady state verification', $iops, $x, $max), $this->verbose, __FILE__, __LINE__);
                $ssMetrics[$x] = $iops;
              }

              if (!$results) break;
            }// end foreach($qdArray as $qp)
            if (!$results) break;
          }// end foreach($tcArray as $tc)

          // check for steady state at rounds 5+
          if ($x >= 5) {
            $metrics = array();
            for($i=4; $i>=0; $i--) $metrics[$x-$i] = $ssMetrics[$x-$i];
            print_msg(sprintf('DIRTH test %d of %d complete and >= 5 rounds finished - checking if steady state has been achieved using IOPS metrics [%s],[%s]', $x, $max, implode(',', array_keys($metrics)), implode(',', $metrics)), $this->verbose, __FILE__, __LINE__);
            
            if ($this->isSteadyState($metrics, $x)) {
              print_msg(sprintf('DIRTH steady state achieved - testing will stop'), $this->verbose, __FILE__, __LINE__);
              $status = TRUE;


              // *** Test Flow 5 find the Max and Mid IOPS ***

              foreach(array_keys($this->fio['wdpc']) as $i){
                $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
                $str = sprintf("/^x%d\-RW1\-rand\-TC([0-9]+)\-QD([0-9]+)/", $x);

                if ($job && preg_match($str, $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0]['write']['iops'])){                  
                  $art = $this->fio['wdpc'][$i]['jobs'][0]['write']['lat']['mean'] /1000; //ms
                  
                  //shall be below 5 ms
                  if($art < 5){
                    $tq = sprintf("%d_%d", $m[1]*1, $m[2]*1);
                    $tcqd[$tq] = $this->fio['wdpc'][$i]['jobs'][0]['write']['iops'];
          
                  }                  
                }
              }
              // find max IOPS             
              $maxTcqd = array_search(max($tcqd), $tcqd);

              // find mid IOPS              
              $meanIOPS = (max($tcqd) + min($tcqd)) / 2;
              $diff = $mindiff = abs($meanIOPS - min($tcqd)); //init value
              $midTcqd = array_search(min($tcqd), $tcqd); //init value

              foreach($tcqd as $key=>$value){
                $diff = abs($meanIOPS - $value);
                if($mindiff > $diff){
                  $mindiff = $diff;
                  $midTcqd = $key;
                }
              }

              global $tcqdArray;
              $tcqdArray[$this->test]['MAX'] = isset($maxTcqd)? $maxTcqd : NULL;
              $tcqdArray[$this->test]['MID'] = isset($midTcqd)? $midTcqd : NULL;
              $tcqdArray[$this->test]['MIN'] = '1_1';

              // *** Test Flow 5 find the Max and Mid IOPS ***

              break;
            } else{ 
              print_msg(sprintf('DIRTH steady state NOT achieved'), $this->verbose, __FILE__, __LINE__);
            }
            
            // end of the line => last test round and steady state not achieved
            if ($x == $max && $status === NULL) $status = FALSE;
          }

          if (!$results || $status !== NULL) break;
        }// end for($x=1; $x<=$max; $x++)

        $this->wdpcComplete = $x;
        $this->subtests['dirth2']->wdpcComplete = $x;
        $this->wdpcIntervals = $composite;
        $this->wdpc = $status;

      }//end elseif($rw == 'dirth2')
      else{
          /**
           *  Test Flow 6
           *  three types: MAX. MID. MIN.
           *  two steps in each type
           *  1. pre-write R/W 0/100 60 minutes
           *  2. R/W RW1 10 minutes
           */ 
          
          $tests = array('pre-write', 'rw1');
          $x = 1;
        
          global $tcqdArray;

          foreach($tcqdArray[$this->test] as $type => $value){

            if($value == NULL) {
              $x++;
              continue;
            }

            $data = explode('_', $value);
            $tc = $data[0];
            $qd = $data[1];
            
            foreach($tests as $test){
              if($test == 'pre-write'){
                // pre-write 0/100 60 minutes
                $rw = '0_100';
                $max = 60;
                $params = array('rw' => 'randwrite', 
                                'runtime' => $this->options['wd_test_duration'], 
                                'time_based' => FALSE, 'iodepth'=>$tc, 'numjobs'=>$qd,
                                'blocksize' => $this->options['dirth_bs']);
                $writeLog = FALSE; 
              }else{
                // RW1 10 minutes    
                $rw = sprintf("%d_%d", $this->options['dirth_rw'], 100-$this->options['dirth_rw']);           
                $max = 10;
                $params = array('rw' => 'randrw', 'rwmixread' => $this->options['dirth_rw'], 
                                'runtime' => $this->options['wd_test_duration'], 
                                'time_based' => FALSE, 'iodepth'=>$tc, 'numjobs'=>$qd,
                                'blocksize' => $this->options['dirth_bs']);
                $writeLog = TRUE;                                
              }
                
              for($n=1; $n<=$max; $n++){
                $name = sprintf('x%d-%s-rand-n%d-TC%d-QD%d-%s', $x, $rw, $n, $tc, $qd, $type);
                print_msg(sprintf('Starting %d sec DIRTH rand write , test %d of %d [name=%s]', $this->options['wd_test_duration'], $n, $max, $name), $this->verbose, __FILE__, __LINE__);
                if($writeLog == TRUE) {
                  $params['write_lat_log'] = sprintf("dirth-fio-lat-%s-%d",strtolower($type), $n);
                }
                $params['name'] = $name;
                if ($fio = $this->fio($params, 'wdpc')) {
                  print_msg(sprintf('Test %s was successful', $name), $this->verbose, __FILE__, __LINE__);
                  $results = $this->fio['wdpc'][count($this->fio['wdpc']) - 1];
    
                  $str = sprintf("/x3\-%d_%d\-rand\-n10\-TC1\-QD1\-MIN/",$this->options['dirth_rw'], 100-$this->options['dirth_rw']);
                  if(preg_match($str, $name)) $status = TRUE;
                }else {
                  print_msg(sprintf('Test %s failed', $name), $this->verbose, __FILE__, __LINE__, TRUE);
                  break;
                }
              }
            }// end foreach($tests as $test)

            $x = ($x == count($tcqdArray[$this->test]))? $x : ++$x;            
          }//end foreach($tcqdArray[$this->test] as $type => $value)
          
          $this->wdpcComplete = $x;
          $this->wdpcIntervals = 60;
          $this->wdpc = $status;
      }// end else
    }
    // main test controller
    else {
      foreach(array_keys($this->subtests) as $i => $rw) {
        print_msg(sprintf('Starting workload dependent preconditioning for DIRTH R/W %s (%d of %d)', $rw, $i+1, count($this->subtests)), $this->verbose, __FILE__, __LINE__);
        $status = $this->subtests[$rw]->wdpc();
        foreach(array_keys($this->subtests[$rw]->fio) as $step) {
          if (!isset($this->fio[$step])) $this->fio[$step] = array();
          foreach($this->subtests[$rw]->fio as $job) $this->fio[$step][] = $job;
        }
        if ($status === NULL) break;
      }
    }

    return $status;    
  }
  
  /**
   * transform block size display method
   * @param int $bs block size
   * @return string
   */
  function ShowBlockSize($bs){

    if($bs%1024 == 0){
      return sprintf("%d KiB", round($bs/1024, 0));
    }else{
      return sprintf("%.1f KiB", round($bs/1024, 1));
    }
  }
}
?>
