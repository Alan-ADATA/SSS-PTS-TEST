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
 * Block storage test implementation for the Host Idle Recovery test
 */
class BlockStorageTestHir extends BlockStorageTest {
  
  /**
   * number of intervals during preconditioning
   */
  const BLOCK_STORAGE_TEST_HIR_PRECONDITION_INTERVALS = 30;
  
  /**
   * test duration (secs) in wait test segment loops
   */
  const BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_DURATION = 5;
  
  /**
   * size of wait test segment loops
   */
  const BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_SIZE = 360;
  
  /**
   * Constructor is protected to implement the singleton pattern using 
   * the BlockStorageTest::getTestController static method
   * @param array $options the test options
   */
  protected function BlockStorageTestHir($options) {
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
      case 'precondition-iops':
        $coords = array();
        $label = 'Pre-Writes, BS=4K';
        foreach(array_keys($this->fio['wdpc']) as $i) {
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
          if ($job && preg_match(sprintf('/^x([0-9]+)\-0_100\-4k\-rand-n%d/', BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_PRECONDITION_INTERVALS), $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0]['write']['iops'])) {
            $round = $m[1]*1;
            $iops = $this->fio['wdpc'][$i]['jobs'][0]['write']['iops'];
            if (!isset($coords[$label])) $coords[$label] = array();
            $coords[$label][] = array($round, $iops);
          }
        }
        if ($coords) $content = $this->generateLineChart($dir, $section, $coords, 'Round', 'IOPS', NULL, array('xMin' => 0, 'yMin' => 0));
        break;

      case 'precondition-ss-measurement':
        $coords = array();
        $iops = array();
        foreach(array_keys($jobs) as $job) {
          if (preg_match(sprintf('/^x([0-9]+)\-0_100\-4k\-rand-n%d/', BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_PRECONDITION_INTERVALS), $job, $m) && isset($jobs[$job]['write']['iops'])) {
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

          // smaller to make room for ss determination table
          $settings['height'] = 450;
          $settings['lines'] = array(1 => "lt 1 lc rgb \"blue\" lw 3 pt 5",
                                      2 => "lt 1 lc rgb \"black\" lw 3 pt -1",
                                      3 => "lt 2 lc rgb \"green\" lw 3 pt -1",
                                      4 => "lt 2 lc rgb \"purple\" lw 3 pt -1",
                                      5 => "lt 4 lc rgb \"red\" lw 3 pt -1 dashtype 2");

          $settings['xMin'] = '10%';
          $settings['yMin'] = '20%';

          $content = $this->generateLineChart($dir, $section, $coords, 'Round', 'IOPS', NULL, $settings);
        }
        break;

      case 'iops-vs-time':
        $coords = array();
        $secs = 0;        
        $baselineNum = 1;
        foreach(array_keys($this->fio['wdpc']) as $i) {
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;

          if ($job && preg_match('/^w([0-9]+)\-0_100\-4k\-rand\-([0-9]+)/', $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0]['write']['iops'])) {
            $x = $m[1]*1;
            $wait = $m[2]*1;
            
            $secs += self::BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_DURATION;
            $label = sprintf('Wait State %d 5/%d', $wait/self::BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_DURATION, $wait);
            $iops = $this->fio['wdpc'][$i]['jobs'][0]['write']['iops'];
            if (!isset($coords[$label])) $coords[$label] = array();
            $coords[$label][] = array(round($secs/60, 2), $iops);

            // add wait time
            $secs += $wait;

          }elseif($job && preg_match('/^x([0-9]+)\-baseline\-0_100\-4k\-rand\-([0-9]+)/', $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0]['write']['iops'])) {
            $x = $m[1];
            $secs += self::BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_DURATION;
            $label = sprintf('Baseline %d 5/0', $baselineNum);
            $iops = $this->fio['wdpc'][$i]['jobs'][0]['write']['iops'];
            $coords[$label][] = array(round($secs/60, 2), $iops);

            if($x == self::BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_SIZE) $baselineNum++;
          }
        }

        $settings = array(
          'xMin' => 0,
          'xMax' => 940, 
          'yMin' => 0,
          'height' => 600,
          'lines' => array(1 => "lt 1 lc rgb \"blue\" lw 1 pt -1",
                           2 => "lt 1 lc rgb \"black\" lw 1 pt -1",
                           3 => "lt 1 lc rgb \"green\" lw 1 pt -1",
                           4 => "lt 1 lc rgb \"black\" lw 1 pt -1",
                           5 => "lt 1 lc rgb \"orange\" lw 1 pt -1",
                           6 => "lt 1 lc rgb \"black\" lw 1 pt -1",
                           7 => "lt 1 lc rgb \"purple\" lw 1 pt -1",
                           8 => "lt 1 lc rgb \"black\" lw 1 pt -1",
                           9 => "lt 1 lc rgb \"red\" lw 1 pt -1",
                           10 => "lt 1 lc rgb \"black\" lw 1 pt -1")
        );
        if ($coords) $content = $this->generateLineChart($dir, $section, $coords, 'Time (Minutes)', 'IOPS', NULL, $settings);
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
      'precondition-iops' => 'Pre Conditioning IOPS Plot',
      'precondition-ss-measurement' => 'Pre Conditioning Steady State Measurement Plot',
      'iops-vs-time' => 'IOPS v Time - All Wait States'
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
      'Pre Condition 1' => 'RND/4KiB',
      '&nbsp;&nbsp;TOIO - TC/QD' => sprintf('TC %d/QD %d', $this->options['threads_total'], $this->options['oio_per_thread']),
      '&nbsp;&nbsp;SS Rounds' => $this->wdpc !== NULL ? sprintf('%d - %d', $this->wdpcComplete - 4, $this->wdpcComplete) : 'N/A',
      'Pre Condition 2' => 'None',
      '&nbsp;&nbsp;TOIO - TC/QD ' => '',
      '&nbsp;&nbsp;SS Rouds' => ''
    );
  }
  
  /**
   * this sub-class method should return the subtitle for a given test and 
   * section
   * @param string $section the section identifier to return the subtitle for
   * @return string
   */
  protected function getSubtitle($section) {
    return sprintf('RND 4KiB %ds Ws / Variable Wait States', BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_DURATION);
  }
  
  /**
   * this sub-class method should return a hash of test parameters - these are
   * label/value pairs displayed in the bottom 8 rows of the Test Parameters 
   * columns in the report page headers
   * @return array
   */
  protected function getTestParameters() {
    return array(
      'Write Stimulus' => 'RND/4KiB',
      '&nbsp;&nbsp;TOIO - TC/QD' => sprintf('TC %d/QD %d', $this->options['threads_total'], $this->options['oio_per_thread']),
      '&nbsp;&nbsp;Durations' => BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_DURATION,
      'Idle State' => 'Host Idle',
      '&nbsp;&nbsp;TOIO - TC/QD ' => '',
      '&nbsp;&nbsp;Durations ' => '5,10,15,25,50',
      '&nbsp;&nbsp;Wait States' => '1,2,3,5,10'
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
        if (preg_match(sprintf('/^x([0-9]+)\-0_100\-4k\-rand-n%d/', BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_PRECONDITION_INTERVALS), $job, $m) && isset($jobs[$job]['write']['iops'])) {
          $iops[] = $jobs[$job]['write']['iops'];
        }
      }
      if ($iops) $metrics['steady_state_iops'] = round(array_sum($iops)/count($iops));
    }
    $iops = array();
    foreach(array_keys($this->fio['wdpc']) as $i) {
      $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
      if ($job && preg_match('/^w([0-9]+)\-0_100\-4k\-rand\-([0-9]+)/', $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0]['write']['iops'])) {
        $wait = $m[2]*1;   
        if (!isset($iops[$wait])) $iops[$wait] = array();
        $iops[$wait][] = $this->fio['wdpc'][$i]['jobs'][0]['write']['iops'];
      }
    }
    if ($iops) {
      foreach($iops as $wait => $vals) {
        if ($vals) $metrics[sprintf('wait_%ds_iops', $wait)] = round(array_sum($vals)/count($vals));
      }
    }

    //add data into db
    $tableName = 'HIR';
    $dbLink = ConnectDB();
    $this->DBInsert($dbLink, $tableName);

    foreach($metrics as $key => $value){
      $field = sprintf("hir_%s", $key);
      $this->DBUpdate($dbLink, $tableName, $field, $value);
    }

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
    print_msg(sprintf('Initiating workload dependent preconditioning and steady state for HIR test'), $this->verbose, __FILE__, __LINE__);
    $max = $this->options['ss_max_rounds'];
    $ssMetrics = array();
    $tgbw = 0;
    
    for($x=1; $x<=$max; $x++) {
      for($n=1; $n<=BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_PRECONDITION_INTERVALS; $n++) {
        $name = sprintf('x%d-0_100-4k-rand-n%d', $x, $n);
        print_msg(sprintf('Starting %d sec HIR 4k rand write preconditioning round %d of %d, test %d of %d [name=%s]', $this->options['wd_test_duration'], $x, $max, $n, BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_PRECONDITION_INTERVALS, $name), $this->verbose, __FILE__, __LINE__);
        $params = array('blocksize' => '4k', 'name' => $name, 'runtime' => $this->options['wd_test_duration'], 'rw' => 'randwrite', 'time_based' => FALSE);

        if ($fio = $this->fio($params, 'wdpc')) {
          print_msg(sprintf('Test %s was successful', $name), $this->verbose, __FILE__, __LINE__);
          $results = $this->fio['wdpc'][count($this->fio['wdpc']) - 1];
        }
        else {
          print_msg(sprintf('Test %s failed', $name), $this->verbose, __FILE__, __LINE__, TRUE);
          break;
        } 
      }
      
      // add steady state metric
      if ($results) {
        $iops = $results['jobs'][0]['write']['iops'];
        print_msg(sprintf('Added IOPS metric %d from preconditioning round %d of %d for HIR steady state verification', $iops, $x, $max), $this->verbose, __FILE__, __LINE__);
        $ssMetrics[$x] = $iops;

        // check for steady state at rounds 5+
        if ($x >= 5) {
          $metrics = array();
          for($i=4; $i>=0; $i--) $metrics[$x-$i] = $ssMetrics[$x-$i];
          print_msg(sprintf('HIR preconditioning test %d of %d complete and >= 5 rounds finished - checking if steady state has been achieved using 4k write IOPS metrics [%s],[%s]', $x, $max, implode(',', array_keys($metrics)), implode(',', $metrics)), $this->verbose, __FILE__, __LINE__);
          if ($this->isSteadyState($metrics, $x)) {
            print_msg(sprintf('HIR steady state achieved - testing will stop'), $this->verbose, __FILE__, __LINE__);
            $status = TRUE;
          }
          else print_msg(sprintf('HIR steady state NOT achieved'), $this->verbose, __FILE__, __LINE__);

          // end of the line => last test round and steady state not achieved
          if ($x == $max && $status === NULL) $status = FALSE;
        }
      }
      if (!$results || $status !== NULL) break;
    }    
    $this->wdpcComplete = $x;
    $this->wdpcIntervals = BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_PRECONDITION_INTERVALS;
    
    // wait state segments
    if ($status !== NULL) {
      print_msg(sprintf('HIR preconditioning complete - beginning wait state test segments'), $this->verbose, __FILE__, __LINE__);
      foreach(array(5, 10, 15, 25, 50) as $wait) {
        for($x=1; $x<=BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_SIZE; $x++) {
          $name = sprintf('w%d-0_100-4k-rand-%d', $x, $wait);
          print_msg(sprintf('Starting %d sec HIR 4k rand write wait segment %d of %d [name=%s; wait=%dsec]', BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_DURATION, $x, BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_SIZE, $name, $wait), $this->verbose, __FILE__, __LINE__);
          $params = array('blocksize' => '4k', 'name' => $name, 'runtime' => BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_DURATION, 'rw' => 'randwrite', 'time_based' => FALSE);
          if ($fio = $this->fio($params, 'wdpc')) {
            print_msg(sprintf('Test %s was successful - sleeping for %d seconds', $name, $wait), $this->verbose, __FILE__, __LINE__);
            sleep($wait);
          }
          else {
            print_msg(sprintf('Test %s failed', $name), $this->verbose, __FILE__, __LINE__, TRUE);
            break;
          }
        }
        if (!$fio) break;
        
        // return to baseline using 1800 seconds of continuous 4k writes
        for($x=1; $x<=BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_SIZE; $x++) {
          $name = sprintf('x%d-baseline-0_100-4k-rand-%d', $x, $wait);
          $params = array('blocksize' => '4k', 'name' => $name, 'runtime' => BlockStorageTestHir::BLOCK_STORAGE_TEST_HIR_WAIT_LOOP_DURATION, 'rw' => 'randwrite', 'time_based' => FALSE);
          if ($fio = $this->fio($params, 'wdpc')) {
            print_msg(sprintf('Test %s was successful', $name), $this->verbose, __FILE__, __LINE__);
            
          }else {
            print_msg(sprintf('Test %s failed', $name), $this->verbose, __FILE__, __LINE__, TRUE);
            break;
          }
        }
      }
    }
    
    // set wdpc attributes
    $this->wdpc = $status;
    
    return $status;
  }
  
}
?>
