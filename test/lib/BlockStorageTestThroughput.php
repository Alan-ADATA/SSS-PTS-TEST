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
 * Block storage test implementation for the Throughput test
 */
class BlockStorageTestThroughput extends BlockStorageTest {
  
  /**
   * the block size for this throughput test
   */
  private $bs = NULL;
  
  /**
   * whether or not this test should purge and precondition prior to testing
   */
  private $purgeAndPrecondition = FALSE;
  
  /**
   * Constructor is protected to implement the singleton pattern using 
   * the BlockStorageTest::getTestController static method
   * @param array $options the test options
   */
  protected function BlockStorageTestThroughput($options, $bs=NULL) {
    if ($bs === NULL) {
      foreach(array('1024k', '128k') as $bs) {
        if (!isset($options['skip_blocksize']) || !in_array($bs, $options['skip_blocksize'])) {
          $this->subtests[$bs] = new BlockStorageTestThroughput($options, $bs);
          $this->subtests[$bs]->purgeAndPrecondition = count($this->subtests[$bs]) > 1;
          $this->subtests[$bs]->test = 'throughput';
          $this->subtests[$bs]->verbose = isset($options['verbose']) && $options['verbose'];
          $this->subtests[$bs]->controller =& $this;
        }
      }
    }
    else {
      $this->bs = $bs;
      $this->options = $options;
      $this->test = 'throughput';
      foreach($options['target'] as $target) {
        $device = BlockStorageTest::getDevice($target);
        $device == $target ? $this->deviceTargets = TRUE : $this->volumeTargets = TRUE;
        break;
      }
    }
  }
  
  /**
   * overrides the parent method in order to write javascript files for 128k 
   * and 1024k workloads separately
   */
  public function generateJson($dir=NULL, $suffix=NULL) {
    $generated = FALSE;
    if ($this->bs !== NULL) return parent::generateJson($dir, $this->bs);
    else foreach(array_keys($this->subtests) as $bs) $generated = $this->subtests[$bs]->generateJson($dir);
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
    $bs = preg_match('/128/', $section) ? '128k' : '1024k';
    if ($this->bs === NULL) return FALSE;
    else if ($this->bs != $bs) return FALSE;
    else {
      switch($section) {
        case 'ss-convergence-write-1024':
        case 'ss-convergence-write-128':
        case 'ss-convergence-read-1024':
        case 'ss-convergence-read-128':
          $key = preg_match('/read/', $section) ? 'read' : 'write';
          $workload = preg_match('/read/', $section) ? '100_0' : '0_100';
          $coords = array();
          foreach(array_keys($this->fio['wdpc']) as $i) {
            $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
            if ($job && preg_match("/^x([0-9]+)\-${workload}\-${bs}\-/", $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0][$key]['bw'])) {
              $round = $m[1]*1;
              $bw = round($this->fio['wdpc'][$i]['jobs'][0][$key]['bw']/1024, 2);
              $label = sprintf('BS=%s', $bs);
              if (!isset($coords[$label])) $coords[$label] = array();
              $coords[$label][] = array($round, $bw);
            }
          }
          if ($coords) $content = $this->generateLineChart($dir, $section, $coords, 'Round', 'Throughput (MB/s)', NULL, array('yMin' => 0));
          break;
        case 'ss-measurement-1024':
        case 'ss-measurement-128':
          $coords = array();
          $bandwidth = array();
          foreach(array_keys($jobs) as $job) {
            if (preg_match("/^x([0-9]+)\-0_100\-${bs}\-/", $job, $m) && isset($jobs[$job]['write']['bw'])) {
              if (!isset($coords['Throughput (MB/s)'])) $coords['Throughput (MB/s)'] = array();
              $round = $m[1]*1;
              $bw = round($jobs[$job]['write']['bw']/1024, 2);
              $coords['Throughput (MB/s)'][] = array($round, $bw);
              $bandwidth[$round] = $bw;
            }
          }
          if (isset($coords['Throughput (MB/s)'])) {
            ksort($bandwidth);
            $keys = array_keys($bandwidth);
            $first = $keys[0];
            $last = $keys[count($keys) - 1];
            $avg = round(array_sum($bandwidth)/count($bandwidth));
            $coords['Average'] = array(array($first, $avg), array($last, $avg));
            $coords['110% Average'] = array(array($first, round($avg*1.1)), array($last, round($avg*1.1)));
            $coords['90% Average'] = array(array($first, round($avg*0.9)), array($last, round($avg*0.9)));
            $coords['Slope'] = array(array($first, $bandwidth[$first]), array($last, $bandwidth[$last]));
            $settings = array();
          
            $settings['height'] = 450;
            $settings['lines'] = array(1 => "lt 1 lc rgb \"blue\" lw 3 pt 5",
                                       2 => "lt 1 lc rgb \"black\" lw 3 pt -1",
                                       3 => "lt 2 lc rgb \"green\" lw 3 pt -1",
                                       4 => "lt 2 lc rgb \"purple\" lw 3 pt -1",
                                       5 => "lt 4 lc rgb \"red\" lw 3 pt -1 dashtype 2");
            $settings['nogrid'] = TRUE;
            $settings['xMin'] = '10%';
            $settings['yMin'] = '20%';
            $content = $this->generateLineChart($dir, $section, $coords, 'Round', 'Throughput (MB/s)', NULL, $settings);
            $content .= sprintf("\n<h3>Steady State Determination Data</h3><table class='meta ssDetermination'>\n<tr><td colspan='2'><label>Average Throughput:</label><span>%d</span></td></tr>", $this->ssData['average']);
            $content .= sprintf("\n<tr><td><label>Allowed Maximum Data Excursion:</label><span>%s</span></td><td><label>Measured Maximum Data Excursion:</label><span>%s</span></td></tr>", $this->ssData['maxDataExcursion'], $this->ssData['largestDataExcursion']);
            $content .= sprintf("\n<tr><td><label>Allowed Maximum Slope Excursion:</label><span>%s</span></td><td><label>Measured Maximum Slope Excursion:</label><span>%s</span></td></tr>", $this->ssData['maxSlopeExcursion'], $this->ssData['largestSlopeExcursion']);
            $content .= sprintf("\n<tr><td colspan='2'><label>Least Squares Linear Fit Formula:</label><span>%s</span></td></tr>", sprintf('%s * R + %s', $this->ssData['slope'], $this->ssData['yIntercept']));
            $content .= "\n</table>";
          }
          break;
        case 'tabular-1024':
        case 'tabular-128':
        case '2d-plot-1024':
        case '2d-plot-128':
          $workloads = array();
          $table = array();
          foreach(array_keys($jobs) as $job) {
            if (preg_match("/^x[0-9]+\-([0-9]+)_([0-9]+)\-${bs}\-/", $job, $m) && (isset($jobs[$job]['read']['bw']) || isset($jobs[$job]['write']['bw']))) {
              $rw = $m[1] . '/' . $m[2];
              if (!in_array($rw, $workloads)) $workloads[] = $rw;
              if (!isset($table[$bs])) $table[$bs] = array();
              if (!isset($table[$bs][$rw])) $table[$bs][$rw] = array();
              $table[$bs][$rw][] = round(($jobs[$job]['read']['bw'] + $jobs[$job]['write']['bw'])/1024, 2);
            }
          }
          $workloads = array_reverse($workloads);
          // tabular
          if ($table && preg_match('/tabular/', $section)) {
            $content = "<div style='text-align:center'><table class='meta tabular'>\n<thead>\n";
            $content .= '<tr><th rowspan="2" class="white">Block Size (KiB)</th><th colspan="' . count($workloads) . "\" class=\"white\">Read / Write Mix %</th></tr>\n<tr>";
            foreach($workloads as $rw) $content .= sprintf('<th>%s</th>', $rw);
            $content .= "</tr>\n</thead>\n<tbody>\n";
            $content .= sprintf('<tr><th>%s</th>', $bs);
            foreach($workloads as $rw) {
              $bw = isset($table[$bs][$rw]) ? round(array_sum($table[$bs][$rw])/count($table[$bs][$rw]), 2) : '';
              $content .= sprintf('<td>%s</td>', $bw);
            }
            $content .= "</tr>\n";
            $content .= "</tbody>\n</table></div>";

            //add data into db
            $tableName = 'TP';
            $dbLink = ConnectDB();
            $this->DBInsert($dbLink, $tableName);         
            
            foreach($workloads as $rw) {
              $field = sprintf("throughput_%s_%s", $bs, str_replace('/', '_', $rw));
              $value = isset($table[$bs][$rw]) ? round(array_sum($table[$bs][$rw])/count($table[$bs][$rw]), 2) : '';            

              $this->DBUpdate($dbLink, $tableName, $field, $value);
            }
            
            $dbLink = NULL;
          }
          // 2d plot
          else if ($table && preg_match('/plot/', $section)) {
            $coords = array();
            $settings = array('yMin' => 0);
            foreach($workloads as $rw) {
              if ($bw = isset($table[$bs][$rw]) ? round(array_sum($table[$bs][$rw])/count($table[$bs][$rw]), 2) : NULL) {
                if (!isset($coords[$rw])) $coords[$rw] = array();
                $coords[$rw][] = $bw;
              }
            }
            $content = $this->generateLineChart($dir, $section, $coords, 'R/W Mix', 'Throughput (MB/s)', NULL, $settings, TRUE, TRUE); 
          }
          break;
      }
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
      'ss-convergence-write-1024' => 'Throughput Test - SS Convergence - Write 1024KiB',
      'ss-convergence-read-1024' => 'Throughput Test - SS Convergence - Read 1024 KiB',
      'ss-measurement-1024' => 'Steady State Measurement Window - SEQ/1024 KiB',
      'tabular-1024' => 'Throughput - All RW Mix &amp; BS - Tabular Data 1024KiB',
      '2d-plot-1024' => 'Throughput - All RW Mix &amp; BS - 2D Plot 1024KiB',
      'ss-convergence-write-128' => 'Throughput Test - SS Convergence - Write 128KiB',
      'ss-convergence-read-128' => 'Throughput Test - SS Convergence - Read 128KiB',
      'ss-measurement-128' => 'Steady State Measurement Window - SEQ/128 KiB',
      'tabular-128' => 'Throughput -All RW Mix &amp; BS - Tabular Data 128KiB',
      '2d-plot-128' => 'Throughput -All RW Mix &amp; BS - 2D Plot 128KiB'
    );
  }
  
  /**
   * this sub-class method should return a hash of setup parameters - these are
   * label/value pairs displayed in the bottom 8 rows of the Set Up Parameters 
   * columns in the report page headers
   * @return array
   */
  protected function getSetupParameters() {
    if (isset($this->controller)) return $this->controller->getSetupParameters();
    else {
      return array(
        'AR Segments' => 'N/A',
        'Pre Condition 1' => $this->wipc ? 'SEQ 128K W' : 'None',
        '&nbsp;&nbsp;TOIO - TC/QD' => $this->wipc ? sprintf('TC %d/QD %d', count($this->options['target']), $this->options['oio_per_thread']) : 'N/A',
        '&nbsp;&nbsp;Duration' => $this->wipc ? sprintf('%dX %s Capacity%s', $this->options['precondition_passes'], $this->deviceTargets ? 'Device' : 'Volume', $this->options['active_range'] < 100 ? ' (' . $this->options['active_range'] . '% AR)' : '') : 'N/A',
        'Pre Condition 2' => isset($this->subtests['128k']) ? 'SEQ 128K W' : 'None',
        '&nbsp;&nbsp;TOIO - TC/QD ' => isset($this->subtests['128k']) ? sprintf('TC %d/QD %d', count($this->options['target']), $this->options['oio_per_thread']) : '',
        '&nbsp;&nbsp;SS Rouds' => $this->subtests['128k']->wdpc !== NULL ? sprintf('%d - %d', $this->subtests['128k']->wdpcComplete - 4, $this->subtests['128k']->wdpcComplete) : 'N/A',
        'Notes' => $this->subtests['128k']->wdpc === FALSE ? sprintf('SS NOT ACHIEVED', $this->subtests['128k']->wdpcComplete) : ''
      );
    }
  }
  
  /**
   * this sub-class method should return the subtitle for a given test and 
   * section
   * @param string $section the section identifier to return the subtitle for
   * @return string
   */
  protected function getSubtitle($section) {
    $subtitle = NULL;
    switch($section) {
      case 'ss-convergence-write-1024':
        $subtitle = 'TP - SEQ 1024KiB &amp; 128KiB';
        break;
      case 'ss-convergence-read-1024':
      case 'ss-measurement-1024':
      case 'tabular-1024':
      case '2d-plot-1024':
        $subtitle = 'TP - SEQ 1024KiB';
        break;
      case 'ss-convergence-write-128':
        $subtitle = 'TP - SEQ 128KiB';
        break;
      case 'ss-convergence-read-128':
      case 'ss-measurement-128':
      case 'tabular-128':
      case '2d-plot-128':
        $subtitle = 'TP - SEQ 1024KiB / 128KiB';
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
    if (isset($this->controller)) return $this->controller->getTestParameters();
    else {
      return array(
        'Test Stimulus 1' => isset($this->subtests['1024k']) ? 'SEQ 1024KiB' : 'None',
        '&nbsp;&nbsp;RW Mix' => isset($this->subtests['1024k']) ? '100:0 / 0:100' : '',
        '&nbsp;&nbsp;Block Sizes' => isset($this->subtests['1024k']) ? 'SEQ 1024KiB' : '',
        '&nbsp;&nbsp;TOIO - TC/QD' => isset($this->subtests['1024k']) ? sprintf('TC %d/QD %d', count($this->options['target']), $this->options['oio_per_thread']) : '',
        '&nbsp;&nbsp;Steady State' => isset($this->subtests['1024k']) && $this->subtests['1024k']->wdpc !== NULL ? sprintf('%d - %d%s', $this->subtests['1024k']->wdpcComplete - 4, $this->subtests['1024k']->wdpcComplete, $this->subtests['1024k']->wdpc ? '' : ' (NOT ACHIEVED)') : 'N/A',
        'Test Stimulus 2' => isset($this->subtests['128k']) ? 'SEQ 128KiB' : 'None',
        '&nbsp;&nbsp;TOIO - TC/QD ' => isset($this->subtests['128k']) ? sprintf('TC %d/QD %d', count($this->options['target']), $this->options['oio_per_thread']) : '',
        '&nbsp;&nbsp;Steady State ' => isset($this->subtests['128k']) && $this->subtests['128k']->wdpc !== NULL ? sprintf('%d - %d%s', $this->subtests['128k']->wdpcComplete - 4, $this->subtests['128k']->wdpcComplete, $this->subtests['128k']->wdpc ? '' : ' (NOT ACHIEVED)') : 'N/A'
      );
    }
  }
  
  /**
   * This method should return job specific metrics as a single level hash of
   * key/value pairs
   * @return array
   */
  protected function jobMetrics() {
    $metrics = array();
    if ($this->bs !== NULL) {
      $bs = $this->bs;
      $jobs = $this->getSteadyStateJobs();
      foreach(array_keys($jobs) as $job) {
        if (preg_match("/^x[0-9]+\-([0-9]+)_([0-9]+)\-${bs}\-/", $job, $m) && (isset($jobs[$job]['read']['bw']) || isset($jobs[$job]['write']['bw']))) {
          $key = sprintf('%s_%s_%s', $bs, $m[1], $m[2]);
          if (!isset($metrics[$key])) $metrics[$key] = array();
          $metrics[$key][] = round(($jobs[$job]['read']['bw'] + $jobs[$job]['write']['bw'])/1024, 2);
        }
      }
      foreach($metrics as $key => $vals) {
        $metrics[$key] = round(array_sum($vals)/count($vals), 2);
      }
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
      
    if ($this->bs !== NULL) {
      $bs = $this->bs;
      $status = NULL;
      if ($this->purgeAndPrecondition) {
        print_msg(sprintf('Repeating purge and workload independent preconditioning'), $this->verbose, __FILE__, __LINE__);
        $this->purge();
        $this->wipc($bs);
      }
      print_msg(sprintf('Initiating %s workload dependent preconditioning and steady state for THROUGHPUT test', $this->bs), $this->verbose, __FILE__, __LINE__);
      $max = $this->options['ss_max_rounds'];
      $workloads = $this->filterWorkloads(array('100/0', '0/100'));
      $ssMetrics = array();
      for($x=1; $x<=$max; $x++) {
        foreach($workloads as $rw) {
          $name = sprintf('x%d-%s-%s-seq', $x, str_replace('/', '_', $rw), $bs);
          print_msg(sprintf('Executing sequential THROUGHPUT test iteration for round %d of %d, workload %s and block size %s', $x, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__);
          $options = array('blocksize' => $bs, 'name' => $name, 'runtime' => $this->options['wd_test_duration'], 'rw' => $rw == '100/0' ? 'read' : 'write', 'time_based' => FALSE);
          if ($fio = $this->fio($options, 'wdpc')) {
            print_msg(sprintf('Sequential THROUGHPUT test iteration for round %d of %d, workload %s and block size %s was successful', $x, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__);
            $results = $this->fio['wdpc'][count($this->fio['wdpc']) - 1];
          }
          else {
            print_msg(sprintf('Random IO test iteration for round %d of %d, rw ratio %s and block size %s failed', $x, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__, TRUE);
            break;
          }
          if ($rw == '0/100') {
            $bw = round($results['jobs'][0]['write']['bw']/1024, 2);
            print_msg(sprintf('Added BW metric %s MB/s for steady state verification', $bw), $this->verbose, __FILE__, __LINE__);
            $ssMetrics[$x] = $bw;
            // check for steady state
            if ($x >= 5) {
              $metrics = array();
              for($i=4; $i>=0; $i--) $metrics[$x-$i] = $ssMetrics[$x-$i];
              print_msg(sprintf('Test iteration for round %d of %d complete and >= 5 rounds finished - checking if steady state has been achieved using %s THROUGHPUT metrics [%s],[%s]', $x, $max, count($metrics), implode(',', array_keys($metrics)), implode(',', $metrics)), $this->verbose, __FILE__, __LINE__);
              if ($this->isSteadyState($metrics, $x)) {
                print_msg(sprintf('Steady state achieved - %s testing will stop', $bs), $this->verbose, __FILE__, __LINE__);
                $status = TRUE;
              }
              else print_msg(sprintf('Steady state NOT achieved'), $this->verbose, __FILE__, __LINE__);
              
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
      $this->wdpcIntervals = count($workloads);
    }
    // main test controller
    else {
      foreach(array_keys($this->subtests) as $i => $bs) {
        print_msg(sprintf('Starting workload dependent preconditioning for throughput block size %s (%d of %d)', $bs, $i+1, count($this->subtests)), $this->verbose, __FILE__, __LINE__);
        $status = $this->subtests[$bs]->wdpc();
        foreach(array_keys($this->subtests[$bs]->fio) as $step) {
          if (!isset($this->fio[$step])) $this->fio[$step] = array();
          foreach($this->subtests[$bs]->fio as $job) $this->fio[$step][] = $job;
        }
        if ($status === NULL) break;
      }
    }
    return $status;
  }
  
}
?>
