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
// This file is modified by ADATA Technology Co., Ltd. in 2018.

/**
 * Block storage test implementation for the Cross Stimulus Recovery test
 */
class BlockStorageTestXsr extends BlockStorageTest {
  const GROUP1 = '1024k ';
  const GROUP2 = '8k';
  const GROUP3 = '1024k';
  // const DATA_NUM = 60*2;  //2hr
  const BLOCK_STORAGE_TEST_XSR_ROUND_PRECISION = 6;
  const TEST_TIME = 60;   //sec
  /**
   * the number of test cycles that constitute a single interval
   */
  const BLOCK_STORAGE_TEST_XSR_SEQ_1024K_CYCLES = 60*8; //8hr
  const BLOCK_STORAGE_TEST_XSR_RND_8K_CYCLES = 60*6;    //6hr
  const TOTAL_CYCLES = self::BLOCK_STORAGE_TEST_XSR_SEQ_1024K_CYCLES *2 + self::BLOCK_STORAGE_TEST_XSR_RND_8K_CYCLES;

  /**
   * Constructor is protected to implement the singleton pattern using 
   * the BlockStorageTest::getTestController static method
   * @param array $options the test options
   */
  protected function BlockStorageTestXsr($options, $bs=NULL) {
    $this->skipWipc = TRUE;
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
    /*
    $content = NULL;
    $coords = array(); 
    
    switch($section){
      case 'tp-time-all-access-groups':

        $ylabel = 'Throughput (MB/s)';
        $settings = array('xMin' => 0, 'xMax' => self::TOTAL_CYCLES);
        
        foreach(array_keys($this->fio['wdpc']) as $i){
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
          if ($job && preg_match('/^x1/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {          
            $bw = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $coords[self::GROUP1][] = array($i+1, $bw);

          }elseif ($job && preg_match('/^x2/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {
            $bw = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $coords[self::GROUP2][] = array($i+1, $bw);

          }elseif ($job && preg_match('/^x3/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {
            $bw = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $coords[self::GROUP3][] = array($i+1, $bw);
          }
        }
        break;

      case 'tp-time-all-1-2':
        $ylabel = 'Throughput (MB/s)';

        foreach(array_keys($this->fio['wdpc']) as $i){
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
          if ($job && preg_match('/^x1/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {          
            $bw = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $coords[self::GROUP1][] = array($i+1, $bw);

          }elseif ($job && preg_match('/^x2/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {
            $bw = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $coords[self::GROUP2][] = array($i+1, $bw);

          }
        }

        //Take two hours each data display
        $coords[self::GROUP1] = array_slice($coords[self::GROUP1], -self::DATA_NUM);
        $coords[self::GROUP2] = array_slice($coords[self::GROUP2], 0, self::DATA_NUM);
        break;

      case 'tp-time-all-2-3':
        $ylabel = 'Throughput (MB/s)';        

        foreach(array_keys($this->fio['wdpc']) as $i){
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
          if ($job && preg_match('/^x2/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {
            $bw = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $coords[self::GROUP2][] = array($i+1, $bw);

          }elseif ($job && preg_match('/^x3/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {
            $bw = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $coords[self::GROUP3][] = array($i+1, $bw);
          }
        }

        //Take two hours each data display
        $coords[self::GROUP2] = array_slice($coords[self::GROUP2], -self::DATA_NUM);
        $coords[self::GROUP3] = array_slice($coords[self::GROUP3], 0, self::DATA_NUM);     
        break;

      case 'maximum-latency':
      case 'average-latency':
        $key = preg_match('/maximum/',$section)? 'max':'mean';
        $ylabel = 'Time (mS)';
        $settings = array('xMin' => 0, 'xMax' => self::TOTAL_CYCLES);        

        foreach(array_keys($this->fio['wdpc']) as $i){
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
          if ($job && preg_match('/^x1/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {          
            $latency = $this->getLatency($this->fio['wdpc'][$i]['jobs'][0], $key);
            $coords[self::GROUP1][] = array($i+1, $latency);

          }elseif ($job && preg_match('/^x2/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {
            $latency = $this->getLatency($this->fio['wdpc'][$i]['jobs'][0], $key);
            $coords[self::GROUP2][] = array($i+1, $latency);

          }elseif ($job && preg_match('/^x3/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {
            $latency = $this->getLatency($this->fio['wdpc'][$i]['jobs'][0], $key);
            $coords[self::GROUP3][] = array($i+1, $latency);
          }
        }       
        
        break;
    }
      
    //fix gunplot bug: data cannot be less then after(資料不能比後面的少)
    $num1 = count($coords[self::GROUP2]);
    $num2 = count($coords[self::GROUP3]);
    if($num1 < $num2){
      $size = $num2 - $num1;
      for($i=0; $i<$size; $i++){
        $coords[self::GROUP2][] = $coords[self::GROUP2][$num1-1];
      }
    }

    //set parameter
    $xlabel = 'Time (Minutes)';
    $settings['height'] = 600;
    $settings['nolinespoints'] = TRUE;
    $settings['yMin'] = 0;

    //Draw line chart
    if ($coords) $content = $this->generateLineChart($dir, $section, $coords, $xlabel, $ylabel, NULL, $settings);
    return $content;
    */

    $content = NULL;
    $coords = array(); 
    
    switch($section){
      case 'tp-time-all-access-groups':

        $ylabel = 'Throughput (MB/s)';
        $settings = array('xMin' => 0, 'xMax' => self::TOTAL_CYCLES);
        
        foreach(array_keys($this->fio['wdpc']) as $i){
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
          if ($job && preg_match('/^x1/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {          
            $bw = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $coords[self::GROUP1][] = array($i+1, $bw);

          }elseif ($job && preg_match('/^x2/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {
            $bw = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $coords[self::GROUP2][] = array($i+1, $bw);

          }elseif ($job && preg_match('/^x3/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {
            $bw = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $coords[self::GROUP3][] = array($i+1, $bw);
          }
        }

        //fix gunplot bug: data cannot be less then after(資料不能比後面的少)
        $num1 = count($coords[self::GROUP2]);
        $num2 = count($coords[self::GROUP3]);
        if($num1 < $num2){
          $size = $num2 - $num1;
          for($i=0; $i<$size; $i++){
            $coords[self::GROUP2][] = $coords[self::GROUP2][$num1-1];
          }
        }

        //set parameter
        $xlabel = 'Time (Minutes)';
        $settings['height'] = 600;
        $settings['nolinespoints'] = TRUE;
        $settings['yMin'] = 0;

        //Draw line chart
        if ($coords) $content = $this->generateLineChart($dir, $section, $coords, $xlabel, $ylabel, NULL, $settings);
        break;

      case 'tp-lat-time':
        foreach(array_keys($this->fio['wdpc']) as $i){
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
          if($job){
            $bw = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $coords['BW'][] = array($i+1, $bw);
            $art = $this->getLatency($this->fio['wdpc'][$i]['jobs'][0], 'mean');
            $coords['ART'][] = array($i+1, $art);
            $mrt = $this->getLatency($this->fio['wdpc'][$i]['jobs'][0], 'max');
            $coords['MRT'][] = array($i+1, $mrt);
          }
        }

        $bwArray = array();
        $artArray = array();
        $mrtArray = array();
        // find the max tp
        foreach($coords['BW'] as $tmpArray)   $bwArray[] = $tmpArray[1];
        $ymax = max($bwArray) + max($bwArray)*0.1;
        // find the max time
        foreach($coords['ART'] as $tmpArray)   $artArray[] = $tmpArray[1];
        foreach($coords['MRT'] as $tmpArray)   $mrtArray[] = $tmpArray[1];
        $y2Max = max($artArray)>max($mrtArray)? max($artArray):max($mrtArray);
        $y2Max = $y2Max + $y2Max*0.1;
        // find the y2 step
        $y2Step = $y2Max / 8;

        $xlabel = 'Time (Minutes)';
        $ylabel = 'Bandwidth (MB/s)';
        $y2label = 'Response Time (mS)';
        $settings = array('xMin' => 0, 'xMax' => self::TOTAL_CYCLES, 'yMin' => 0, 'yMax' => $ymax,
                          'height' => 600, 'nolinespoints' => TRUE, 
                          'y2' => array('ART','MRT','label'=>$y2label,'max'=>$y2Max,'min'=>0,'step'=>$y2Step));
        //Draw line chart
        if ($coords) $content = $this->generateLineChart($dir, $section, $coords, $xlabel, $ylabel, NULL, $settings);
        break;

      case 'xsr-table':
        foreach(array_keys($this->fio['wdpc']) as $i){
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
          if ($job && preg_match('/^x1/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {          
            $bw[self::GROUP1][] = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $art[self::GROUP1][] = $this->getLatency($this->fio['wdpc'][$i]['jobs'][0], 'mean');
            $mrt[self::GROUP1][] = $this->getLatency($this->fio['wdpc'][$i]['jobs'][0], 'max');

          }elseif ($job && preg_match('/^x2/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {
            $bw[self::GROUP2][] = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $art[self::GROUP2][] = $this->getLatency($this->fio['wdpc'][$i]['jobs'][0], 'mean');
            $mrt[self::GROUP2][] = $this->getLatency($this->fio['wdpc'][$i]['jobs'][0], 'max');

          }elseif ($job && preg_match('/^x3/', $job) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {
            $bw[self::GROUP3][] = round($this->fio['wdpc'][$i]['jobs'][0]['write']['bw']/1024, 2);
            $art[self::GROUP3][] = $this->getLatency($this->fio['wdpc'][$i]['jobs'][0], 'mean');
            $mrt[self::GROUP3][] = $this->getLatency($this->fio['wdpc'][$i]['jobs'][0], 'max');
          }
        }

        $tp1avg = array_sum($bw[self::GROUP1]) / count($bw[self::GROUP1]);
        for($recovery=1; $recovery<=count($bw[self::GROUP3]); $recovery++){
          if($bw[self::GROUP3][$recovery-1] >= $tp1avg ) {
            break;
          }
        }

        $content = '<table border="1" cellpadding="15" align="center"><tbody>';
        $content .= '<tr align="center"><td colspan="2"> </td>';
        $content .= sprintf("<td>PTS-%s</td>", $this->options['spec']=='enterprise'?'E':'C');
        $content .= sprintf("<td>Recovery(Minutes)</td><td>%s</td></tr>", $recovery>count($bw[self::GROUP3])?'not yet':$recovery);
        $content .= sprintf('<tr align="center"><td colspan="2">%s</td>',$this->options['spec']=='enterprise'?'WCD':'WCE');
        $content .= '<td rowspan="2">8 hr<br>SEQ 1024K</td>';
        $content .= '<td rowspan="2">6 hr<br>RND 8K</td>';
        $content .= '<td rowspan="2">8 hr<br>SEQ 1024K</td></tr>';
        $content .= '<tr align="center"><td colspan="2">T1Q32</td></tr>';
        $content .= '<tr><td colspan="2">The end of TP MB/s</td><td style="text-align: right;">'.end($bw[self::GROUP1]).'</td><td style="text-align: right;">'.end($bw[self::GROUP2]).'</td><td style="text-align: right;">'.end($bw[self::GROUP3]).'</td></tr>';
        $content .= '<tr><td colspan="2">ART mS (peak)</td><td style="text-align: right;">'.max($art[self::GROUP1]).'</td><td style="text-align: right;">'.max($art[self::GROUP2]).'</td><td style="text-align: right;">'.max($art[self::GROUP3]).'</td></tr>';
        $content .= '<tr><td colspan="2">MRT mS (peak)</td><td style="text-align: right;">'.max($mrt[self::GROUP1]).'</td><td style="text-align: right;">'.max($mrt[self::GROUP2]).'</td><td style="text-align: right;">'.max($mrt[self::GROUP3]).'</td></tr>';
        $content .= '</tbody></table>';
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
      'tp-time-all-access-groups' => 'TP vs. Time - All Access Groups',
      // 'tp-time-all-1-2' => 'TP vs. Time - Groups 1 & 2',
      // 'tp-time-all-2-3' => 'TP vs. Time - Groups 2 & 3',
      // 'maximum-latency' => 'Maximum Latency vs. Time, All Access Groups',
      // 'average-latency' => 'Average Latency vs. Time, All Access Groups',
      'tp-lat-time' => 'Throughput, ART & MRT v Time',
      'xsr-table' => 'TP, ART, MRT & BW Recovery Time'
    );
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
        'Pre Condition 1' => 'None',
        '&nbsp;&nbsp;TOIO - TC/QD' => '-',
        '&nbsp;&nbsp;Duration' => '-',
        'Pre Condition 2' => 'None',
        '&nbsp;&nbsp;TOIO - TC/QD ' => '-',
        '&nbsp;&nbsp;SS Rouds' => '-'
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
    return 'XSR - SEQ 1024KiB - RND 8KiB - SEQ 1024KiB';
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
      return array(
        'Test Stimulus 1' => 'SEQ 1024KiB', 
        '&nbsp;&nbsp;TOIO - TC/QD' => sprintf('TC %d/QD %d', count($this->options['target']), $this->options['oio_per_thread']),
        '&nbsp;&nbsp;Duration(Hr)' => '8',
        'Test Stimulus 2' => 'RND 8KiB',
        '&nbsp;&nbsp;TOIO - TC/QD ' => sprintf('TC %d/QD %d', count($this->options['target']), $this->options['oio_per_thread']),
        '&nbsp;&nbsp;Duration(Hr) ' => '6'
      );
    }
  }
  
  /**
   * This method should return job specific metrics as a single level hash of
   * key/value pairs
   * @return array
   */
  protected function jobMetrics(){
    $metrics = array();
    if ($jobs = $this->getSteadyStateJobs()) {
      
      $data = array();
      for($i=0; $i<9; $i++){
        $data[$i] = array();
      }

      foreach(array_keys($jobs) as $job) {
        if (preg_match('/^x1/', $job)) {
          $data[0][] = $jobs[$job]['write']['bw'];
          $data[3][] = $jobs[$job]['write']['lat']['max'];
          $data[6][] = $jobs[$job]['write']['lat']['mean'];
        }
        elseif(preg_match('/^x2/', $job)) {
          $data[1][] = $jobs[$job]['write']['bw'];
          $data[4][] = $jobs[$job]['write']['lat']['max'];
          $data[7][] = $jobs[$job]['write']['lat']['mean'];
        }
        elseif(preg_match('/^x3/', $job)) {
          $data[2][] = $jobs[$job]['write']['bw'];
          $data[5][] = $jobs[$job]['write']['lat']['max'];
          $data[8][] = $jobs[$job]['write']['lat']['mean'];
        }
      }
      
      if ($data[0]) $metrics['GROUP1:SEQ 1024 KiB TP'] = $data[0];
      if ($data[1]) $metrics['GROUP2:RND 8 KiB TP'] = $data[1];        
      if ($data[2]) $metrics['GROUP3:SEQ 1024 KiB TP'] = $data[2];
      if ($data[3]) $metrics['GROUP1:SEQ 1024 KiB LMAX'] = $data[3];
      if ($data[4]) $metrics['GROUP2:RND 8 KiB LMAX'] = $data[4];        
      if ($data[5]) $metrics['GROUP3:SEQ 1024 KiB LMAX'] = $data[5];
      if ($data[6]) $metrics['GROUP1:SEQ 1024 KiB LMEAN'] = $data[6];
      if ($data[7]) $metrics['GROUP2:RND 8 KiB LMEAN'] = $data[7];        
      if ($data[8]) $metrics['GROUP3:SEQ 1024 KiB LMEAN'] = $data[8];            
    }

    return $metrics;
  }
  
  
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
    print_msg(sprintf('Initiating workload dependent preconditioning and steady state for XSR test'), $this->verbose, __FILE__, __LINE__);
    $tc = 1;
    $qd = 32;
    //Group 1 sequential 1024k 8Hr
    $max = BlockStorageTestXsr::BLOCK_STORAGE_TEST_XSR_SEQ_1024K_CYCLES;
    $bs = '1024k';
    $rw = 'write';
    
    for($n=1; $n<=$max; $n++){
      $name = sprintf('x1-0_100-1024k-seq-n%d', $n);
      print_msg(sprintf('Executing XSR test iteration for round %d of %d, workload 0/100 and block size %s', $n, $max, $bs), $this->verbose, __FILE__, __LINE__);
      
      $options = array('blocksize' => $bs, 'name' => $name, 'runtime' => BlockStorageTestXsr::TEST_TIME, 'rw' => $rw, 'time_based' => FALSE,
                      'numjobs' => $tc, 'iodepth'=> $qd);
      if ($fio = $this->fio($options, 'wdpc')) {
        print_msg(sprintf('XSR test iteration for round %d of %d, workload %s and block size %s was successful', $n, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__);
        $results = $this->fio['wdpc'][count($this->fio['wdpc']) - 1];
      }
      else {
        print_msg(sprintf('XSR test iteration for round %d of %d, rw ratio %s and block size %s failed', $n, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__, TRUE);
        break;
      }
    }

    //Group 2 random 8k 6Hr
    $max = BlockStorageTestXsr::BLOCK_STORAGE_TEST_XSR_RND_8K_CYCLES;
    $bs = '8k';
    $rw = 'randwrite';

    for($n=1; $n<=$max; $n++){
      $name = sprintf('x2-0_100-8k-rnd-n%d', $n);
      print_msg(sprintf('Executing XSR test iteration for round %d of %d, workload 0/100 and block size %s', $n, $max, $bs), $this->verbose, __FILE__, __LINE__);
      
      $options = array('blocksize' => $bs, 'name' => $name, 'runtime' => BlockStorageTestXsr::TEST_TIME, 'rw' => $rw, 'time_based' => FALSE,
                      'numjobs' => $tc, 'iodepth'=> $qd);
      if ($fio = $this->fio($options, 'wdpc')) {
        print_msg(sprintf('XSR test iteration for round %d of %d, workload %s and block size %s was successful', $n, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__);
        $results = $this->fio['wdpc'][count($this->fio['wdpc']) - 1];
      }
      else {
        print_msg(sprintf('XSR test iteration for round %d of %d, rw ratio %s and block size %s failed', $n, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__, TRUE);
        break;
      }
    }

    //Group 3 sequential 1024k 8Hr
    $max = BlockStorageTestXsr::BLOCK_STORAGE_TEST_XSR_SEQ_1024K_CYCLES;
    $bs = '1024k';
    $rw = 'write';
    for($n=1; $n<=$max; $n++){
      $name = sprintf('x3-0_100-1024k-seq-n%d', $n);
      print_msg(sprintf('Executing XSR test iteration for round %d of %d, workload 0/100 and block size %s', $n, $max, $bs), $this->verbose, __FILE__, __LINE__);
      
      $options = array('blocksize' => $bs, 'name' => $name, 'runtime' => BlockStorageTestXsr::TEST_TIME, 'rw' => $rw, 'time_based' => FALSE,
                      'numjobs' => $tc, 'iodepth'=> $qd);
      if ($fio = $this->fio($options, 'wdpc')) {
        print_msg(sprintf('XSR test iteration for round %d of %d, workload %s and block size %s was successful', $n, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__);
        $results = $this->fio['wdpc'][count($this->fio['wdpc']) - 1];
      }
      else {
        print_msg(sprintf('XSR test iteration for round %d of %d, rw ratio %s and block size %s failed', $n, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__, TRUE);
        break;
      }
    }

    $status = TRUE;

    // set wdpc attributes      
    $this->wdpc = $status;
    $this->wdpcComplete = count($this->fio['wdpc']);
    $this->wdpcIntervals = 0;
    return $status;
  }

  /**
   * returns the latency metric in milliseconds from the $job specified
   * @param array $job the job to return latency for
   * @param string $type the type of latency to return (mean, min, max)
   * @return float
   */
  private function getLatency($job, $type='mean') {
    $latency = NULL;
    
    if (isset($job['write']['lat'][$type]) && $job['write']['lat'][$type] > 0)
        $latency = $job['write']['lat'][$type];
    
    // convert from microseconds to milliseconds
    if ($latency) $latency = round($latency/1000, self::BLOCK_STORAGE_TEST_XSR_ROUND_PRECISION);
    
    return $latency;
  }

}
?>
