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
 * Block storage test implementation for the Latency test
 */
class BlockStorageTestLatency extends BlockStorageTest {
  
  const BLOCK_STORAGE_TEST_LATENCY_ROUND_PRECISION = 12;
  
  /**
   * Constructor is protected to implement the singleton pattern using 
   * the BlockStorageTest::getTestController static method
   * @param array $options the test options
   */
  protected function BlockStorageTestLatency($options) {}
    
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
    $key = preg_match('/max/', $section) ? 'max' : 'mean';
    switch($section) {
      case 'ss-convergence-avg':
      case 'ss-convergence-max':
        $coords = array();
        foreach(array_keys($this->fio['wdpc']) as $i) {
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
          if ($job && preg_match('/^x([0-9]+)\-0_100\-([0-9]+[mkb])\-/', $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0]['write'])) {
            $bs = $m[2];
            $label = sprintf('RW=0/100, BS=%s', $bs);
            $round = $m[1]*1;
            $latency = $this->getLatency($this->fio['wdpc'][$i]['jobs'][0], $key);
            if (!isset($coords[$label])) $coords[$label] = array();
            $coords[$label][] = array($round, $latency);
          }
        }
        if ($coords) $content = $this->generateLineChart($dir, $section, $coords, 'Round', 'Time (ms)', NULL, array('yFloatPrec' => 2, 'yMin' => 0));
        break;
      case 'ss-measurement':
        $coords = array();
        $latencies = array();
        foreach(array_keys($jobs) as $job) {
          if (preg_match('/^x([0-9]+)\-0_100\-4k\-/', $job, $m) && isset($jobs[$job]['write'])) {
            if (!isset($coords['Time (ms)'])) $coords['Time (ms)'] = array();
            $round = $m[1]*1;
            $coords['Time (ms)'][] = array($round, $this->getLatency($jobs[$job], $key));
            $latencies[$round] = $this->getLatency($jobs[$job], $key);
          }
        }
        if (isset($coords['Time (ms)'])) {
          ksort($latencies);
          $keys = array_keys($latencies);
          $first = $keys[0];
          $last = $keys[count($keys) - 1];
          $avg = round(array_sum($latencies)/count($latencies), self::BLOCK_STORAGE_TEST_LATENCY_ROUND_PRECISION);
          $coords['Average'] = array(array($first, $avg), array($last, $avg));
          $coords['110% Average'] = array(array($first, round($avg*1.1, self::BLOCK_STORAGE_TEST_LATENCY_ROUND_PRECISION)), array($last, round($avg*1.1, self::BLOCK_STORAGE_TEST_LATENCY_ROUND_PRECISION)));
          $coords['90% Average'] = array(array($first, round($avg*0.9, self::BLOCK_STORAGE_TEST_LATENCY_ROUND_PRECISION)), array($last, round($avg*0.9, self::BLOCK_STORAGE_TEST_LATENCY_ROUND_PRECISION)));
          $coords['Slope'] = array(array($first, $latencies[$first]), array($last, $latencies[$last]));
          $settings = array();
          // smaller to make room for ss determination table
          $settings['height'] = 450;
          $settings['lines'] = array(1 => "lt 1 lc rgb \"blue\" lw 3 pt 5",
                                     2 => "lt 1 lc rgb \"black\" lw 3 pt -1",
                                     3 => "lt 2 lc rgb \"green\" lw 3 pt -1",
                                     4 => "lt 2 lc rgb \"purple\" lw 3 pt -1",
                                     5 => "lt 4 lc rgb \"red\" lw 3 pt -1 dashtype 2");
          $settings['nogrid'] = TRUE;
          $settings['xMin'] = '10%';
          $settings['yMin'] = '20%';
          $settings['yFloatPrec'] = 2;
          $content = $this->generateLineChart($dir, $section, $coords, 'Round', 'Time (ms)', NULL, $settings);
          // add ss determination table
          if ($section == 'ss-determination') {
            $content .= sprintf("\n<h3>Steady State Determination Data</h3><table class='meta ssDetermination'>\n<tr><td colspan='2'><label>Average Latency (ms):</label><span>%d</span></td></tr>", $this->ssData['average']);
            $content .= sprintf("\n<tr><td><label>Allowed Maximum Data Excursion:</label><span>%s</span></td><td><label>Measured Maximum Data Excursion:</label><span>%s</span></td></tr>", $this->ssData['maxDataExcursion'], $this->ssData['largestDataExcursion']);
            $content .= sprintf("\n<tr><td><label>Allowed Maximum Slope Excursion:</label><span>%s</span></td><td><label>Measured Maximum Slope Excursion:</label><span>%s</span></td></tr>", $this->ssData['maxSlopeExcursion'], $this->ssData['largestSlopeExcursion']);
            $content .= sprintf("\n<tr><td colspan='2'><label>Least Squares Linear Fit Formula:</label><span>%s</span></td></tr>", sprintf('%s * R + %s', $this->ssData['slope'], $this->ssData['yIntercept']));
            $content .= "\n</table>";
          }
        }
        break;
      case 'tabular':
      case '3d-plot-avg':
      case '3d-plot-max':
        $workloads = array();
        $blockSizes = array();
        $table = array();
        foreach(array_keys($jobs) as $job) {
          if (preg_match('/^x[0-9]+\-([0-9]+)_([0-9]+)\-([0-9]+[mkb])\-/', $job, $m) && isset($jobs[$job]['write'])) {
            $rw = $m[1] . '/' . $m[2];
            $bs = $m[3];
            if (!in_array($rw, $workloads)) $workloads[] = $rw;
            if (!in_array($bs, $blockSizes)) $blockSizes[] = $bs;
            if (!isset($table[$bs])) $table[$bs] = array();
            if (!isset($table[$bs][$rw])) $table[$bs][$rw] = array();
            foreach(array('mean', 'max') as $type) {
              if (!isset($table[$bs][$rw][$type])) $table[$bs][$rw][$type] = array();
              $table[$bs][$rw][$type][] = $this->getLatency($jobs[$job], $type);
            }
          }
        }
        $workloads = array_reverse($workloads);
        $blockSizes = array_reverse($blockSizes);
        // tabular
        if ($table && $section == 'tabular') {
          $content = '';
          foreach(array('mean', 'max') as $i => $type) {
            $content .= ($i ? '<br><br>' : '') . "<div style='text-align:center'><table class='meta tabular'>\n<thead>\n";
            $content .= '<tr><th colspan="' . (count($workloads) + 1) . '">' . ($type == 'mean' ? 'Average' : 'Maximum') . " Response Time (ms)</th></tr>\n";
            $content .= '<tr><th rowspan="2" class="white">Block Size (KiB)</th><th colspan="' . count($workloads) . "\" class=\"white\">Read / Write Mix %</th></tr>\n<tr>";
            foreach($workloads as $rw) $content .= sprintf('<th>%s</th>', $rw);
            $content .= "</tr>\n</thead>\n<tbody>\n";
            foreach($blockSizes as $bs) {
              $content .= sprintf('<tr><th>%s</th>', $bs);
              foreach($workloads as $rw) {
                $latency = isset($table[$bs][$rw][$type]) ? $table[$bs][$rw][$type] : NULL;
                $content .= sprintf('<td>%s</td>', $latency ? round(array_sum($latency)/count($latency), self::BLOCK_STORAGE_TEST_LATENCY_ROUND_PRECISION) : '');
              }
              $content .= "</tr>\n";
            }
            $content .= "</tbody>\n</table></div>"; 
          }

          //add data into db
          $tableName = 'LAT';
          $dbLink = ConnectDB();
          $this->DBInsert($dbLink, $tableName);
          
          foreach($blockSizes as $bs) {
            foreach($workloads as $rw) {
                foreach(array('mean', 'max') as $i => $type) {
                  $latency = isset($table[$bs][$rw][$type]) ? $table[$bs][$rw][$type] : NULL;
                  $value = $latency ? round(array_sum($latency)/count($latency), self::BLOCK_STORAGE_TEST_LATENCY_ROUND_PRECISION) : '';
                  
                  $field = sprintf("latency_%s_%s_%s",$bs, str_replace('/', '_', $rw), $type);
                  $this->DBUpdate($dbLink, $tableName, $field, $value);
                }
            }
          }
          $dbLink = NULL;
        }
        // 3d plot
        else if ($table) {
          $workloads = array_reverse($workloads);
          $series = array();
          $settings = array('xAxis' => array('categories' => $blockSizes, 'title' => array('text' => 'Block Size (KiB)')),
                            'yAxis' => array('labels' => array('format' => '{value:,.2f}'), 'min' => 0, 'title' => array('text' => 'Time (ms)')));
          $stack = 0;
          foreach($blockSizes as $x => $bs) {
            foreach($workloads as $y => $rw) {
              if ($latency = isset($table[$bs][$rw][$key]) ? round(array_sum($table[$bs][$rw][$key])/count($table[$bs][$rw][$key]), self::BLOCK_STORAGE_TEST_LATENCY_ROUND_PRECISION) : NULL) {
                if (!isset($series[$y])) $series[$y] = array('data' => array(), 'name' => $rw, 'stack' => $stack++);
                $series[$y]['data'][] = array('x' => $x, 'y' => $latency);
              }
            }
          }
          $content = $this->generate3dChart($section, $series, $settings, 'R/W Mix', 2);
        }
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
      'ss-convergence-avg' => 'Steady State Convergence Plot - Average Latency - 100% Writes',
      'ss-convergence-max' => 'Steady State Convergence Plot - Maximum Latency - 100% Writes',
      'ss-measurement' => 'Steady State Measurement Window - RND/4KiB',
      'tabular' => 'Average and Maximum Response Time - All RW Mix &amp; BS - Tabular Data',
      '3d-plot-avg' => 'Average Latency vs. BS and R/W Mix - 3D Plot',
      '3d-plot-max' => 'Maximum Latency vs. BS and R/W Mix - 3D Plot'
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
      'Pre Condition 1' => $this->wipc ? 'SEQ 128K W' : 'None',
      '&nbsp;&nbsp;TOIO - TC/QD' => $this->wipc ? sprintf('TC %d/QD %d', count($this->options['target']), $this->options['oio_per_thread']) : 'N/A',
      '&nbsp;&nbsp;Duration' => $this->wipc ? sprintf('%dX %s Capacity%s', $this->options['precondition_passes'], $this->deviceTargets ? 'Device' : 'Volume', $this->options['active_range'] < 100 ? ' (' . $this->options['active_range'] . '% AR)' : '') : 'N/A',
      'Pre Condition 2' => 'LAT Loop',
      '&nbsp;&nbsp;TOIO - TC/QD ' => 'TC 1/QD 1',
      '&nbsp;&nbsp;SS Rouds' => $this->wdpc !== NULL ? sprintf('%d - %d', $this->wdpcComplete - 4, $this->wdpcComplete) : 'N/A',
      'Notes' => $this->wdpc === FALSE ? sprintf('SS NOT ACHIEVED', $this->wdpcComplete) : ''
    );
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
      case '3d-plot-max':
        $subtitle = 'LAT - 0.5, 4, 8KiB x R, 65:35, W';
        break;
      default:
        $subtitle = 'LATENCY - Response Time OIO=1';
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
    return array(
      'Test Stimulus 1' => 'LAT Loop',
      '&nbsp;&nbsp;RW Mix' => 'Outer Loop',
      '&nbsp;&nbsp;Block Sizes' => 'Inner Loop',
      '&nbsp;&nbsp;TOIO - TC/QD' => 'TC ' . count($this->options['target']) . '/QD 1',
      '&nbsp;&nbsp;Steady State' => $this->wdpc !== NULL ? sprintf('%d - %d%s', $this->wdpcComplete - 4, $this->wdpcComplete, $this->wdpc ? '' : ' (NOT ACHIEVED)') : 'N/A',
      'Histogram' => 'N/A',
      '&nbsp;&nbsp;TOIO - TC/QD ' => 'N/A',
      'Note ' => ''
    );
  }
  
  /**
   * This method should return job specific metrics as a single level hash of
   * key/value pairs
   * @return array
   */
  protected function jobMetrics() {
    $metrics = array();
    $jobs = $this->getSteadyStateJobs();
    foreach(array_keys($jobs) as $job) {
      if (preg_match('/^x[0-9]+\-([0-9]+)_([0-9]+)\-([0-9]+[mkb])\-/', $job, $m) && isset($jobs[$job]['write'])) {
        $key = sprintf('%s_%s_%s_', $m[3], $m[1], $m[2]);
        foreach(array('mean', 'max') as $type) {
          if (!isset($metrics[$key . $type])) $metrics[$key . $type] = array();
          $metrics[$key . $type][] = $this->getLatency($jobs[$job], $type);
        }
      }
    }
    foreach($metrics as $key => $vals) {
      $metrics[$key] = round(array_sum($vals)/count($vals), self::BLOCK_STORAGE_TEST_LATENCY_ROUND_PRECISION);
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
    print_msg(sprintf('Initiating workload dependent preconditioning and steady state for LATENCY test'), $this->verbose, __FILE__, __LINE__);
    $max = $this->options['ss_max_rounds'];
    $ssMetrics = array();
    $blockSizes = $this->filterBlocksizes(array('8k', '4k', '512b'));
    $lastBlockSize = $blockSizes[count($blockSizes) - 1];
    $workloads = $this->filterWorkloads(array('100/0', '65/35', '0/100'));
    
    for($x=1; $x<=$max; $x++) {
      foreach($workloads as $rw) {
        $pieces = explode('/', $rw);
        $read = $pieces[0]*1;
        $write = $pieces[1]*1;
        $rwmixread = 100 - $write;
        foreach($blockSizes as $bs) {
          $name = sprintf('x%d-%s-%s-rand', $x, str_replace('/', '_', $rw), $bs);
          print_msg(sprintf('Executing random IO test iteration for round %d of %d, rw ratio %s and block size %s', $x, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__);
          $params = array('blocksize' => $bs, 'name' => $name, 'runtime' => $this->options['wd_test_duration'], 'time_based' => FALSE, 'numjobs' => 1, 'iodepth' => 1, 'lockfile' => 'exclusive');
          if ($read == 100) $params['rw'] = 'randread';
          else if ($write == 100) $params['rw'] = 'randwrite';
          else {
            $params['rw'] = 'randrw';
            $params['rwmixread'] = $rwmixread;
          }
          if ($fio = $this->fio($params, 'wdpc')) {
            print_msg(sprintf('Random IO test iteration for round %d of %d, rw ratio %s and block size %s was successful', $x, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__);
            $results = $this->fio['wdpc'][count($this->fio['wdpc']) - 1];
          }
          else {
            print_msg(sprintf('Random IO test iteration for round %d of %d, rw ratio %s and block size %s failed', $x, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__, TRUE);
            break;
          }
          if ($rw == '0/100' && $bs == '4k') {
            $latency = $this->getLatency($results['jobs'][0]);
            print_msg(sprintf('Added Latency metric %s ms for steady state verification', $latency), $this->verbose, __FILE__, __LINE__);
            $ssMetrics[$x] = $latency;
          }
          // check for steady state
          if ($x >= 5 && $rw == '0/100' && $bs == $lastBlockSize) {
            $metrics = array();
            for($i=4; $i>=0; $i--) $metrics[$x-$i] = $ssMetrics[$x-$i];
            print_msg(sprintf('Test round %d of %d complete and >= 5 rounds finished - checking if steady state has been achieved using 4k write latency metrics [%s],[%s]', $x, $max, implode(',', array_keys($metrics)), implode(',', $metrics)), $this->verbose, __FILE__, __LINE__);
            if ($this->isSteadyState($metrics, $x)) {
              print_msg(sprintf('Steady state achieved - testing will stop'), $this->verbose, __FILE__, __LINE__);
              $status = TRUE;
            }
            else print_msg(sprintf('Steady state NOT achieved'), $this->verbose, __FILE__, __LINE__);
            
            // end of the line => last test round and steady state not achieved
            if ($x == $max && $status === NULL) $status = FALSE;
          }
          if (!$fio || $status !== NULL) break;
        }
        if (!$fio || $status !== NULL) break;
      }
      if (!$fio || $status !== NULL) break;
    }
    // set wdpc attributes
    $this->wdpc = $status;
    $this->wdpcComplete = $x;
    $this->wdpcIntervals = count($workloads)*count($blockSizes);
    
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
    if (isset($job['write']) || isset($job['read'])) {
      // both read and write - return mean of the two
      if (isset($job['write']['lat'][$type]) && $job['write']['lat'][$type] > 0 && isset($job['read']['lat'][$type]) && $job['read']['lat'][$type] > 0) $latency = ($job['write']['lat'][$type] + $job['read']['lat'][$type])/2;
      else if (isset($job['write']['lat'][$type]) && $job['write']['lat'][$type] > 0) $latency = $job['write']['lat'][$type];
      else if (isset($job['read']['lat'][$type]) && $job['read']['lat'][$type] > 0) $latency = $job['read']['lat'][$type];
      // convert from microseconds to milliseconds
      if ($latency) $latency = round($latency/1000, self::BLOCK_STORAGE_TEST_LATENCY_ROUND_PRECISION);
    }
    return $latency;
  }
  
}
?>
