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
 * Block storage test implementation for the Write Saturation test
 */
class BlockStorageTestWsat extends BlockStorageTest {
  /**
   * Composite Block Size Access Probabilities
   * size    512    1    1.5    2    2.5    3    3.5    4    8    16    32    64  
   * %       4%     1%   1%     1%   1%     1%   1%     67%  10%  7%    3%    3%     
   */  
  const AP = '512/4:1k/1:1536/1:2k/1:2560/1:3k/1:3584/1:4k/67:8k/10:16k/7:32k/3:64k/3';
  
  /**
   * CBW Access Range Distribution Restrictions
   * 50%    First 5%         LBA Group A
   * 30%    Next 15%         LBA Group B
   * 20%    Remaining 80%    LBA Group C
   */
  const ZONE = 'zoned:50/5:30/15:20/80';   
  /**
   * the number of test cycles that constitute a single interval
   */
  const BLOCK_STORAGE_TEST_WSAT_CYCLES = 31;
  
  /**
   * rounding precision for TGBW numbers
   */
  const BLOCK_STORAGE_TEST_WSAT_ROUND_PRECISION = 6;
  
  /**
   * Constructor is protected to implement the singleton pattern using 
   * the BlockStorageTest::getTestController static method
   * @param array $options the test options
   */
  protected function BlockStorageTestWsat($options) {
    $this->skipWipc = TRUE;
  }
  
  /**
   * this sub-class method should return the content associated with $section 
   * using the $jobs given (or all jobs in $this->fio['wdpc']). Return value 
   * should be HTML that can be imbedded into the report. The HTML may include 
   * an image reference without any directory path (e.g. <img src="iops.png>")
   * return NULL on error
   * @param string $section the section identifier provided by 
   * $this->getReportSections()
   * @param array $jobs all fio job results occuring within the steady state 
   * measurement window. This is a hash indexed by job name
   * @param string $dir the directory where any images should be generated in
   * @return string
   */
  protected function getReportContent($section, $jobs, $dir) {
    $content = NULL;
    switch($section) {
      case 'iops-linear-time':
      case 'iops-linear-tgbw':
      case 'iops-linear-capacity':
        $isTgbw = preg_match('/tgbw/', $section);
        $isTime = preg_match('/time/', $section);
        $tgbw = 0;
        $coords = array();
        $label = NULL;
        foreach(array_keys($this->fio['wdpc']) as $i) {
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
          if ($job && preg_match('/^x([0-9])+\-.*\-(.*)\-(.*)\-n([0-9]+)/', $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0]['write']['iops'])) {
            if($label === NULL) $label = $m[3].' '.$m[2].' IOPS';
            $time = ($m[1]-1) * self::BLOCK_STORAGE_TEST_WSAT_CYCLES + $m[4];
            $iops = $this->fio['wdpc'][$i]['jobs'][0]['write']['iops'];
            $tgbw += (int)$this->fio['wdpc'][$i]['jobs'][0]['write']['io_bytes']/pow(1024,2); // KB/pow(1024,2)=GB
            if (!isset($coords[$label])) $coords[$label] = array();
            $coords[$label][] = array($isTime ? $time : round($tgbw, BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_ROUND_PRECISION), $iops);
          }
        }

        $settings = array('nolinespoints' => TRUE, 'xMin' => 0, 'yMin' => 0);

        if($isTime){
          $xLabel = sprintf('Time (%s)', $this->options['wd_test_duration'] == '60' ? 'Minutes' : $this->options['wd_test_duration'] . ' secs');
        }elseif ($isTgbw) {
          // change to MB if < 1GB written
          if ($tgbw < 1) {
            $xLabel .= 'Total Gigabytes Written (MB)';
            foreach(array_keys($coords[$label]) as $i) $coords[$label][$i][0] *= 1024;
          }else
            $xLabel = 'Total Gigabytes Written (GB)';

        }else{          
          $xLabel = 'Normalized Capacity';
          $capacity = (int)(shell_exec(sprintf("lsblk -n -o size -b %s",$this->options['target'][0]))) / pow(1024,3); //GB

          $max = (int)($tgbw / $capacity) + 1;
          $str = 'set xtics (';
          if($max > 1){
            for($n=1; $n<=$max; $n++){
              $str.= sprintf('"%d"%s,', $n, round($capacity*$n, 1));
            }

          }else{
            $str.= sprintf('"0.5"%s,"1"%s', round($capacity/2, 1), round($capacity, 1));
          }
          $str.=')';

          $settings['usrxTicLabel'] = $str;
          $settings['xMin'] = 0;
          $settings['xMax'] = round($capacity * $max, 1);
        }        
      
        if ($isTgbw && $tgbw < 1) $settings['xFloatPrec'] = BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_ROUND_PRECISION;
        if ($coords) $content = $this->generateLineChart($dir, $section, $coords, $xLabel, 'IOPS', NULL, $settings);
        break;

      case 'histogram':
        foreach(array_keys($jobs) as $job){
          if(preg_match('/^20min-0_100-4k-rand/', $job)){
            $iops = $jobs[$job]['write']['iops'];
            $bw = round($jobs[$job]['write']['bw']/1024, 2);
            $percentArray = $jobs[$job]['write']['clat']['percentile'];
            $art = round($jobs[$job]['write']['clat']['mean'] / 1000, 5);
          }
        }

        // base on tc number
        for($n=1; $n<=$this->options['threads']; $n++){
          $fileName = sprintf("%s/wsat-fio-lat_clat.%d.log", dirname($dir), $n);
          $fp = fopen($fileName, 'r');
          if($fp){
            while($line = fgets($fp)){
              $data = explode(",", $line);
              $time = trim($data[1]);
              $count[$time] = (isset($count[$time]))? ++$count[$time] : 1;
            }
            fclose($fp);
          } 
        }
                
        ksort($count);
        $maxTime = max(array_keys($count)) / 1000;

        $fileName = sprintf("%s/wsat-fio-lat_*.log", dirname($dir));
        exec("rm -f $fileName");

        // count vs. time
        foreach($count as $time=>$number){
          $coords['count'][] = array($time/1000, $number);
        }
        
        //confidence level data, 39s, 49s, 59s vs. time
        foreach($percentArray as $percent=>$value){
          $msec = $value/1000;
          $coords['confidence'][] = array($msec, $percent);            
          
          if($percent == 99.9 || $percent == 99.99 || $percent == 99.999){
            $coords[$percent][] = array($msec, 100);
            $settings[$percent] = $msec;
          }        
        }

        // ART vs. time
        $coords['ART'][] = array($art, 100);
        $settings['ART'] = $art;

        // max x-axis value
        $range = $settings['99.999000'] * 0.1;

        $settings['xMax'] = $settings['99.999000'] + $range;
        $settings['RTCLP'] = TRUE;
        $title = sprintf("CLP. RND4K RW0, T1Q1, IOPS=%s, %s MB/s, MRT=%s ms", $iops, $bw, $maxTime);
        $content = sprintf("<h2 style=\"text-align: center;\">%s</h2>",$title);
        if ($coords) $content .= $this->generateHistogram($dir, $section, $coords, NULL, $settings);
        break;
    }
    return $content;
  }
    
  /**
   * this sub-class method should return a hash identifiying the sections 
   * associated with the test report. The key in the hash should be the 
   * section identifier, and the value, the section title
   * @return array
   */
  protected function getReportSections() {
    return array(
      'iops-linear-time' => 'WSAT IOPS (Linear) vs Time (Linear)',
      'iops-linear-tgbw' => 'WSAT IOPS (Linear) vs TGBW (Linear)',
      'iops-linear-capacity' => 'WSAT IOPS (Linear) vs Normalized Capacity (Linear)',
      'histogram' => 'WSAT Response Time Histogram – Confidence Level Plots'
    );
  }
  
  /**
   * this sub-class method should return a hash of setup parameters - these are
   * label/value pairs displayed in the bottom 8 rows of the Set Up Parameters 
   * columns in the report page headers
   * @return array
   */
  protected function getSetupParameters() {
    return array(
      'AR Segments' => 'N/A',
      'Pre Condition 1' => 'None',
      '&nbsp;&nbsp;TOIO - TC/QD' => '',
      '&nbsp;&nbsp;SS Rounds' => '',
      'Pre Condition 2' => 'None',
      '&nbsp;&nbsp;TOIO - TC/QD ' => '',
      '&nbsp;&nbsp;SS Rouds ' => '',
      'Notes' => ''
    );
  }
  
  /**
   * this sub-class method should return the subtitle for a given test and 
   * section
   * @param string $section the section identifier to return the subtitle for
   * @return string
   */
  protected function getSubtitle($section) {
    switch($this->options['wsat_wl']){
      case 1:
        $workload = 'Mixed or OLTP (RND 8KiB RW65)';
        break;
      case 2:
        $workload = 'Video On Demand (SEQ 128KiB RW90)';
        break;
      case 3:
        $workload = 'Meta Data (SEQ 0.5KiB RW50)';
        break;
      case 4:
        $workload = 'Composite Block Size Workload (mixed/composite BS/RW)';
        break;
      default:
        $workload = 'Write Intensive (RND 4KiB RW0)';
        break;
    }

    switch($section) {
      case 'histogram':
        $subtitle = 'WSAT - RND 4KiB 100% W';
        break;
      default:
        $subtitle = 'WSAT - '.$workload;
        break;
    }

    return $subtitle;
  }
  
  /**
   * this sub-class method should return a hash of test parameters - these are
   * label/value pairs displayed in the bottom 8 rows of the Test Parameters 
   * columns in the report page headers
   * @return array
   */
  protected function getTestParameters() {
    switch($this->options['wsat_wl']){
      case 1:
        $workload = 'RND 8KiB RW65';
        break;
      case 2:
        $workload = 'SEQ 128KiB RW90';
        break;
      case 3:
        $workload = 'SEQ 0.5KiB RW50';
        break;
      case 4:
        $workload = 'mixed/composite BS/RW';
        break;
      default:
        $workload = 'RND 4KiB RW0';
        break;
    }
    return array(
      'Test Stimulus 1' => $workload,
      '&nbsp;&nbsp;TOIO - TC/QD' => sprintf('TC %d/QD %d', $this->options['threads_total'], $this->options['oio_per_thread']),
      '&nbsp;&nbsp;Steady State' => $this->wdpc !== NULL ? sprintf('%d - %d%s', $this->wdpcComplete - 4, $this->wdpcComplete, $this->wdpc ? '' : ' (NOT ACHIEVED)') : 'N/A',
      '&nbsp;&nbsp;Time' => $this->totalTime. 'min',
      'Test Stimulus 2' => 'N/A',
      '&nbsp;&nbsp;TOIO - TC/QD ' => 'N/A',
      '&nbsp;&nbsp;Steady State ' => 'N/A',
      '&nbsp;&nbsp;Time ' => 'N/A'
    );
  }
  
  /**
   * This method should return job specific metrics as a single level hash of
   * key/value pairs
   * @return array
   */
  protected function jobMetrics() {
    $metrics = array();
    if ($jobs = $this->getSteadyStateJobs()) {
      $iops = array();
      foreach(array_keys($jobs) as $job) {
        if (isset($jobs[$job]['write']['iops'])) $iops[] = $jobs[$job]['write']['iops'];
      }
      if ($iops) $metrics['iops'] = round(array_sum($iops)/count($iops));
    }

    //add data into db
    $tableName = 'WSAT';
    $dbLink = ConnectDB();
    $this->DBInsert($dbLink, $tableName);

    $field = 'wsat_iops';
    $value = $metrics['iops'];
    $this->DBUpdate($dbLink, $tableName, $field, $value);

    $dbLink = NULL;

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
    print_msg(sprintf('Initiating workload dependent preconditioning and steady state for WSAT test'), $this->verbose, __FILE__, __LINE__);

    $ssMetrics = array();
    $tgbw = 0;
    $time = 0;
    $capacity_4x = (int)(shell_exec(sprintf("lsblk -n -o size -b %s",$this->options['target'][0]))) / pow(1024,3) *4; //GB
    $maxTime = 60 * $this->options['wsat_time'];

    // prepare workload parameters 
    switch($this->options['wsat_wl']){
      case 1:  //Mixed or OLTP (RND 8KiB RW65)
        $bs = '8k';
        $rw = '65_35';
        $randOrSeq = 'rand';
        $fiorw = 'randrw';
        $readwrite = 'read write';
        $rwmixread = 65;
        break;
              
      case 2:  //Video On Demand (SEQ 128KiB RW90)
        $bs = '128k';
        $rw = '90_10';
        $randOrSeq = 'seq';
        $fiorw = 'rw';
        $readwrite = 'read write';
        $rwmixread = 90;
        break;

      case 3:  //Meta Data (SEQ 0.5KiB RW50)
        $bs = '512b';
        $rw = '50_50';
        $randOrSeq = 'seq';
        $fiorw = 'rw';
        $readwrite = 'read write';
        $rwmixread = 50;
        break;
      
      case 4:  //Composite Block Size Workload (mixed/composite BS/RW)
        $bs = 'Composite';
        $rw = '0_100';
        $randOrSeq = 'rand';
        $readwrite = 'write';
        break;

      default:  //Write Intensive (RND 4KiB RW0)
        $bs = '4k';
        $rw = '0_100';
        $randOrSeq = 'rand';
        $fiorw = 'randwrite';
        $readwrite = 'write';
        break;          
    }

    do{
        $x = (int)($time/31) + 1;
        $n = $time % 31 + 1;
        $name = sprintf('x%d-%s-%s-%s-n%d', $x, $rw, $bs, $randOrSeq, $n);
        print_msg(sprintf('Starting %dsec %s %s %s test %d of %d [%d] for WSAT test iteration %d [name=%s]. TGBW=%s GB', 
            $this->options['wd_test_duration'], $bs, $randOrSeq, $readwrite ,$n, BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_CYCLES, 
            $time+1, $x, $name, round($tgbw, BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_ROUND_PRECISION)), $this->verbose, __FILE__, __LINE__);
        
        if($this->options['wsat_wl'] == 4){
          $params = array('rw' => 'randwrite', 'name' => $name, 
                          'runtime' => $this->options['wd_test_duration'], 
                          'time_based' => FALSE, 'bssplit' => self::AP,
                          'random_distribution' => self::ZONE);

        }else{
          $params = array('blocksize' => $bs, 'name' => $name, 
                          'runtime' => $this->options['wd_test_duration'], 
                          'rw' => $fiorw, 'time_based' => FALSE);

          if(isset($rwmixread)) $params['rwmixread'] = $rwmixread;
        }
        
        if ($fio = $this->fio($params, 'wdpc')) {
          print_msg(sprintf('Test %s was successful', $name), $this->verbose, __FILE__, __LINE__);
          $results = $this->fio['wdpc'][count($this->fio['wdpc']) - 1];
          $tgbw += (int)$results['jobs'][0]['write']['io_bytes'] / pow(1024,2); // KB/pow(1024,2)=GB
          $time++;

          // add steady state metric
          if ($n == BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_CYCLES) {
            $iops = $results['jobs'][0]['write']['iops'] + $results['jobs'][0]['read']['iops'];
            print_msg(sprintf('Added IOPS metric %d for WSAT steady state verification', $iops), $this->verbose, __FILE__, __LINE__);
            $ssMetrics[$x] = $iops;
          }
        }
        else {
          print_msg(sprintf('Test %s failed', $name), $this->verbose, __FILE__, __LINE__, TRUE);
          break;
        }
        
        // 4X user Capacity is written, 6 hours or five round steady state        
        if($tgbw >= $capacity_4x || $time >= $maxTime){
          $status = FALSE;
          
        }elseif($x >= 5 && $n == self::BLOCK_STORAGE_TEST_WSAT_CYCLES){
            $metrics = array();
            for($i=4; $i>=0; $i--) $metrics[$x-$i] = $ssMetrics[$x-$i];
            print_msg(sprintf('WSAT test round %d of %d complete and >= 5 rounds finished - checking if steady state has been achieved using write IOPS metrics [%s],[%s]', $x, $max, implode(',', array_keys($metrics)), implode(',', $metrics)), $this->verbose, __FILE__, __LINE__);
            if ($this->isSteadyState($metrics, $x)) {
              print_msg(sprintf('WSAT steady state achieved - testing will stop'), $this->verbose, __FILE__, __LINE__);
              $status = TRUE;
            }
            else print_msg(sprintf('WSAT steady state NOT achieved'), $this->verbose, __FILE__, __LINE__);
        }
    }while($status === NULL);
   
    // set wdpc attributes
    $this->wdpc = $status;
    $this->wdpcComplete = $x;
    $this->wdpcIntervals = BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_CYCLES;
    $this->totalTime = $time;
    
    // Execute R/W% = 0/100 4KiB RND IO for 20 minutes
    print_msg('Executing random IO test iteration rw ratio 0/100 and block size 4k for 20 minutes', $this->verbose, __FILE__, __LINE__);
    unset($params);
    $params = array('blocksize' => '4k', 'rw' => 'randwrite', 'name' => '20min-0_100-4k-rand', 
                    'runtime' => 60*20, 'time_based' => FALSE, 'write_lat_log' => 'wsat-fio-lat',
                    'percentile_list'=> '1:5:10:20:30:40:50:60:70:80:90:95:99:99.9:99.99:99.999:99.9999');

    if ($this->fio($params, 'wdpc')) {
      print_msg('Random IO test iteration for rw ratio 0/100 and block size 4k for 20 minutes was successful', $this->verbose, __FILE__, __LINE__);
    }else {
      print_msg('Random IO test iteration for rw ratio 0/100 and block size 4k for 20 minutes failed', $this->verbose, __FILE__, __LINE__, TRUE);
    }

    return $status;
  }
  
}
?>
