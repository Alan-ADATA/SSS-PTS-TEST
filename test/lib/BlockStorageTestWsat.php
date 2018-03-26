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
 * Block storage test implementation for the Write Saturation test
 */
class BlockStorageTestWsat extends BlockStorageTest {
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
      case 'iops-log-time':
      case 'iops-linear-tgbw':
      case 'iops-log-tgbw':
        $log = preg_match('/log/', $section);
        $isTgbw = preg_match('/tgbw/', $section);
        $tgbw = 0;
        $coords = array();
        $label = 'RND4K IOPS';
        foreach(array_keys($this->fio['wdpc']) as $i) {
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
          if ($job && preg_match('/^x[0-9]+\-0_100\-4k\-rand\-([0-9]+)/', $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0]['write']['iops'])) {
            $x = $m[1]*1;
            $iops = $this->fio['wdpc'][$i]['jobs'][0]['write']['iops'];
            $tgbw += ($this->fio['wdpc'][$i]['jobs'][0]['write']['io_bytes']/1024)/1024;
            if (!isset($coords[$label])) $coords[$label] = array();
            $coords[$label][] = array($isTgbw ? round($tgbw, BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_ROUND_PRECISION) : $x, $iops);
          }
        }
        
        $xLabel = sprintf('Time (%s)', $this->options['wd_test_duration'] == '60' ? 'Minutes' : $this->options['wd_test_duration'] . ' secs');
        if ($isTgbw) {
          $xLabel = 'Total Gigabytes Written (TGW)';
          // change to MB if < 1GB written
          if ($tgbw < 1) {
            $xLabel .= ' - MB displayed';
            foreach(array_keys($coords[$label]) as $i) $coords[$label][$i][0] *= 1024;
          } 
        }
        
        $settings = array('nolinespoints' => TRUE, 'xMin' => 0, 'yMin' => $log ? 1 : 0);
        if ($log) $settings['yLogscale'] = TRUE;
        if ($isTgbw && $tgbw < 1) $settings['xFloatPrec'] = BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_ROUND_PRECISION;
        if ($coords) $content = $this->generateLineChart($dir, $section, $coords, $xLabel, 'IOPS', NULL, $settings);
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
      'iops-log-time' => 'WSAT IOPS (LOG) vs Time (Linear)',
      'iops-linear-tgbw' => 'WSAT IOPS (Linear) vs TGBW (Linear)',
      'iops-log-tgbw' => 'WSAT IOPS (Log) vs TGBW (Linear)'
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
    return 'WSAT - RND 4KiB 100% W';
  }
  
  /**
   * this sub-class method should return a hash of test parameters - these are
   * label/value pairs displayed in the bottom 8 rows of the Test Parameters 
   * columns in the report page headers
   * @return array
   */
  protected function getTestParameters() {
    return array(
      'Test Stimulus 1' => 'RND 4KiB',
      '&nbsp;&nbsp;TOIO - TC/QD' => sprintf('TC %d/QD %d', $this->options['threads_total'], $this->options['oio_per_thread']),
      '&nbsp;&nbsp;Steady State' => $this->wdpc !== NULL ? sprintf('%d - %d%s', $this->wdpcComplete - 4, $this->wdpcComplete, $this->wdpc ? '' : ' (NOT ACHIEVED)') : 'N/A',
      '&nbsp;&nbsp;Time' => 'N/A',
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
    $max = $this->options['ss_max_rounds'];
    $ssMetrics = array();
    $tgbw = 0;
    
    for($x=1; $x<=$max; $x++) {
      // perform 31 4k 100% rand write test intervals - use the last for steady
      // state confirmation
      for($n=1; $n<=BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_CYCLES; $n++) {
        $testNum = (($x-1)*BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_CYCLES)+$n;
        $name = sprintf('x%d-0_100-4k-rand-%d', $x, $testNum);
        print_msg(sprintf('Starting %dsec 4k rand write test %d of %d [%d] for WSAT test iteration %d of %d [name=%s]. TGBW=%s GB', $this->options['wd_test_duration'], $n, BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_CYCLES, $testNum, $x, $max, $name, round($tgbw, BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_ROUND_PRECISION)), $this->verbose, __FILE__, __LINE__);
        $params = array('blocksize' => '4k', 'name' => $name, 'runtime' => $this->options['wd_test_duration'], 'rw' => 'randwrite', 'time_based' => FALSE);
        
        if ($fio = $this->fio($params, 'wdpc')) {
          print_msg(sprintf('Test %s was successful', $name), $this->verbose, __FILE__, __LINE__);
          $results = $this->fio['wdpc'][count($this->fio['wdpc']) - 1];
          $tgbw += ($results['jobs'][0]['write']['io_bytes']/1024)/1024;
        }
        else {
          print_msg(sprintf('Test %s failed', $name), $this->verbose, __FILE__, __LINE__, TRUE);
          break;
        }
        
        // add steady state metric
        if ($n == BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_CYCLES) {
          $iops = $results['jobs'][0]['write']['iops'];
          print_msg(sprintf('Added IOPS metric %d for WSAT steady state verification', $iops), $this->verbose, __FILE__, __LINE__);
          $ssMetrics[$x] = $iops;
          
          // check for steady state at rounds 5+
          if ($x >= 5) {
            $metrics = array();
            for($i=4; $i>=0; $i--) $metrics[$x-$i] = $ssMetrics[$x-$i];
            print_msg(sprintf('WSAT test round %d of %d complete and >= 5 rounds finished - checking if steady state has been achieved using 4k write IOPS metrics [%s],[%s]', $x, $max, implode(',', array_keys($metrics)), implode(',', $metrics)), $this->verbose, __FILE__, __LINE__);
            if ($this->isSteadyState($metrics, $x)) {
              print_msg(sprintf('WSAT steady state achieved - testing will stop'), $this->verbose, __FILE__, __LINE__);
              $status = TRUE;
            }
            else print_msg(sprintf('WSAT steady state NOT achieved'), $this->verbose, __FILE__, __LINE__);
            
            // end of the line => last test round and steady state not achieved
            if ($x == $max && $status === NULL) $status = FALSE;   
          }
        }
        if (!$fio || $status !== NULL) break;
      }
      if (!$fio || $status !== NULL) break;
    }
    
    // set wdpc attributes
    $this->wdpc = $status;
    $this->wdpcComplete = $x;
    $this->wdpcIntervals = BlockStorageTestWsat::BLOCK_STORAGE_TEST_WSAT_CYCLES;
    
    return $status;
  }
  
}
?>
