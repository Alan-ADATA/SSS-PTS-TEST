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
 * Block storage test implementation for the IOPS test
 */
class BlockStorageTestIops extends BlockStorageTest {
  /**
   * Constructor is protected to implement the singleton pattern using 
   * the BlockStorageTest::getTestController static method
   * @param array $options the test options
   */
  protected function BlockStorageTestIops($options) {}
    
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
      // Steady State Convergence: from X=1 for 100% write jobs
      case 'ss-convergence':
        $coords = array();
        foreach(array_keys($this->fio['wdpc']) as $i) {
          $job = isset($this->fio['wdpc'][$i]['jobs'][0]['jobname']) ? $this->fio['wdpc'][$i]['jobs'][0]['jobname'] : NULL;
          if ($job && preg_match('/^x([0-9]+)\-0_100\-([0-9]+[mkb])\-/', $job, $m) && isset($this->fio['wdpc'][$i]['jobs'][0]['write']['iops'])) {
            $bs = $m[2];
            $round = $m[1]*1;
            $iops = $this->fio['wdpc'][$i]['jobs'][0]['write']['iops'];
            if (!isset($coords[$bs])) $coords[$bs] = array();
            $coords[$bs][] = array($round, $iops);
          }
        }
        if ($coords) $content = $this->generateLineChart($dir, $section, $coords, 'Round', 'IOPS', NULL, array('yMin' => 0));
        break;

      case 'ss-measurement':
      case 'ss-determination-4k-0_100':
      case 'ss-determination-64k-65_35':
      case 'ss-determination-1m-100_0':
        if(preg_match('/^ss-determination\-([0-9]+[km])\-([0-9]+_[0-9]+)/', $section, $m)){
          $bs = $m[1];
          $rw = $m[2];
        }
        else{
          $bs = '4k';
          $rw = '0_100';
        }
        
        $str = sprintf("/^x([0-9]+)\-%s\-%s\-/", $rw, $bs);

        $coords = array();
        $iops = array();
        foreach(array_keys($jobs) as $job) {
          if (preg_match($str, $job, $m)) {
            if (!isset($coords['IOPS'])) $coords['IOPS'] = array();
            $round = $m[1]*1;
            $coords['IOPS'][] = array($round, $jobs[$job]['write']['iops'] + $jobs[$job]['read']['iops']);
            $iops[$round] = $jobs[$job]['write']['iops'] + $jobs[$job]['read']['iops'];
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
          
          $settings['height'] = 450;
          $settings['lines'] = array(1 => "lt 1 lc rgb \"blue\" lw 3 pt 5",
                                    2 => "lt 1 lc rgb \"black\" lw 3 pt -1",
                                    3 => "lt 2 lc rgb \"green\" lw 3 pt -1",
                                    4 => "lt 2 lc rgb \"purple\" lw 3 pt -1",
                                    5 => "lt 4 lc rgb \"red\" lw 3 pt -1 dashtype 2");
          $settings['xMin'] = '10%';
          if(preg_match('/^ss-determination/', $section)){
            // smaller to make room for ss determination table
            $settings['yMin'] = '20%';
          }
          else $settings['yMin'] = 0;

          $content = $this->generateLineChart($dir, $section, $coords, 'Round', 'IOPS', NULL, $settings);

          // add ss determination table
          if(preg_match('/^ss-determination/', $section)){
            $content .= sprintf("\n<h3>Steady State Determination Data</h3><table class='meta ssDetermination'>\n<tr><td colspan='2'><label>Average IOPS:</label><span>%d</span></td></tr>", $this->ssData['average'][$bs]);
            $content .= sprintf("\n<tr><td><label>Allowed Maximum Data Excursion:</label><span>%s</span></td><td><label>Measured Maximum Data Excursion:</label><span>%s</span></td></tr>", $this->ssData['maxDataExcursion'][$bs], $this->ssData['largestDataExcursion'][$bs]);
            $content .= sprintf("\n<tr><td><label>Allowed Maximum Slope Excursion:</label><span>%s</span></td><td><label>Measured Maximum Slope Excursion:</label><span>%s</span></td></tr>", $this->ssData['maxSlopeExcursion'][$bs], $this->ssData['largestSlopeExcursion'][$bs]);
            $content .= sprintf("\n<tr><td colspan='2'><label>Least Squares Linear Fit Formula:</label><span>%s</span></td></tr>", sprintf('%s * R + %s', $this->ssData['slope'][$bs], $this->ssData['yIntercept'][$bs]));
            $content .= "\n</table>";
          }
        }
        break;

      case 'tabular':
      case '2d-plot':
      case '3d-plot':
        $workloads = array();
        $blockSizes = array();
        $table = array();
        foreach(array_keys($jobs) as $job) {
          if (preg_match('/^x[0-9]+\-([0-9]+)_([0-9]+)\-([0-9]+[mkb])\-/', $job, $m) && isset($jobs[$job]['write']['iops'])) {
            $rw = $m[1] . '/' . $m[2];
            $bs = $m[3];
            if (!in_array($rw, $workloads)) $workloads[] = $rw;
            if (!in_array($bs, $blockSizes)) $blockSizes[] = $bs;
            if (!isset($table[$bs])) $table[$bs] = array();
            if (!isset($table[$bs][$rw])) $table[$bs][$rw] = array();
            $table[$bs][$rw][] = $jobs[$job]['read']['iops'] + $jobs[$job]['write']['iops'];
          }
        }
        $workloads = array_reverse($workloads);
        $blockSizes = array_reverse($blockSizes);
        // tabular
        if ($table && $section == 'tabular') {
          $content = "<div style='text-align:center'><table class='meta tabular'>\n<thead>\n";
          $content .= '<tr><th rowspan="2" class="white">Block Size (KiB)</th><th colspan="' . count($workloads) . "\" class=\"white\">Read / Write Mix %</th></tr>\n<tr>";
          foreach($workloads as $rw) $content .= sprintf('<th>%s</th>', $rw);
          $content .= "</tr>\n</thead>\n<tbody>\n";
          foreach($blockSizes as $bs) {
            $content .= sprintf('<tr><th>%s</th>', $bs);
            foreach($workloads as $rw) {
              $iops = isset($table[$bs][$rw]) ? $table[$bs][$rw] : NULL;
              $content .= sprintf('<td>%s</td>', $iops ? round(array_sum($iops)/count($iops), 1) : '');
            }
            $content .= "</tr>\n";
          }
          $content .= "</tbody>\n</table></div>";

          //add data into db
          $tableName = 'IOPS';
          $dbLink = ConnectDB();
          $this->DBInsert($dbLink, $tableName);
          foreach($blockSizes as $bs) {
            foreach($workloads as $rw) {
              $field = sprintf("iops_%s_%s", $bs, str_replace('/', '_', $rw));

              $iops = isset($table[$bs][$rw]) ? $table[$bs][$rw] : NULL;
              $value = $iops ? round(array_sum($iops)/count($iops), 1) : '';

              $this->DBUpdate($dbLink, $tableName, $field, $value);
            }
          }
          $dbLink = NULL;
        }
        // 2d plot
        else if ($table && $section == '2d-plot') {
          $coords = array();
          $settings = array('yMin' => 1, 'yLogscale' => TRUE);
          $settings['xLogscale'] = TRUE;
          $settings['usrxTicLabel'] = 'set xtics ("0.5"0.5,"1"1,"2"2,"4"4,"8"8,"16"16, "32"32, "64"64, "128"128, "256"256, "512"512, "1024"1024)';
          $settings['lines'] = array(1 => "lc rgb '#F15854' lt 1 lw 3",
                                     2 => "lc rgb '#5DA5DA' lt 3 lw 3",
                                     3 => "lc rgb '#FAA43A' lt 4 lw 3",
                                     4 => "lc rgb '#60BD68' lt 5 lw 3",
                                     5 => "lc rgb '#F17CB0' lt 6 lw 3",
                                     6 => "lc rgb '#B2912F' lt 7 lw 3",
                                     7 => "lc rgb '#DECF3F' lt 8 lw 3");
          $maxIops = NULL;
          foreach($blockSizes as $bs) {
            foreach($workloads as $rw) {
              if ($iops = isset($table[$bs][$rw]) ? round(array_sum($table[$bs][$rw])/count($table[$bs][$rw]), 1) : NULL) {
                preg_match('/^([0-9]+)([bkm])$/', $bs, $m);
                $kb = $m[1];
                if ($m[2] == 'b') $kb /= 1024;
                else if ($m[2] == 'm') $kb *= 1024;
                if (!isset($coords[$rw])) $coords[$rw] = array();
                $coords[$rw][] = array($kb, $iops);
                if ($maxIops === NULL || $iops > $maxIops) $maxIops = $iops; 
              }
            }
          }
          if ($maxIops) {
            // y axis starts at 1 and uses multiples of 10
            $yMax = 1;
            while($yMax < $maxIops) $yMax *= 10;
            $settings['yMax'] = $yMax;
            $content = $this->generateLineChart($dir, $section, $coords, 'Block Size KiB', 'IOPS', NULL, $settings); 
          }
        }
        // 3d plot
        else if ($table && $section == '3d-plot') {
          $series = array();
          $settings = array('xAxis' => array('categories' => $blockSizes, 'title' => array('text' => 'Block Size (KiB)')),
                            'yAxis' => array('labels' => array('format' => '{value:,.0f}'), 'min' => 0, 'title' => array('text' => 'IOPS')));
          $stack = 0;
          foreach($blockSizes as $x => $bs) {
            foreach($workloads as $y => $rw) {
              if ($iops = isset($table[$bs][$rw]) ? round(array_sum($table[$bs][$rw])/count($table[$bs][$rw]), 1) : NULL) {
                if (!isset($series[$y])) $series[$y] = array('data' => array(), 'name' => $rw, 'stack' => $stack++);
                $series[$y]['data'][] = array('x' => $x, 'y' => $iops);
              }
            }
          }
          $content = $this->generate3dChart($section, $series, $settings, 'R/W Mix');
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
      'ss-convergence' => 'IOPS Steady State Convergence Plot - All Block Sizes',
      'ss-measurement' => 'IOPS Steady State Measurement Window',
      'ss-determination-4k-0_100' => 'IOPS Steady State Measurement Window - RND/4KiB RW0',
      'ss-determination-64k-65_35' => 'IOPS Steady State Measurement Window - RND/64KiB RW65',
      'ss-determination-1m-100_0' => 'IOPS Steady State Measurement Window - RND/1024KiB RW100',
      'tabular' => 'IOPS - All RW Mix &amp; BS - Tabular Data',
      '2d-plot' => 'IOPS - All RW Mix &amp; BS - 2D Plot',
      '3d-plot' => 'IOPS -All RW Mix &amp; BS - 3D Columns'
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
      'Pre Condition 2' => 'IOPS Loop',
      '&nbsp;&nbsp;TOIO - TC/QD ' => sprintf('TC %d/QD %d', $this->options['threads_total'], $this->options['oio_per_thread']),
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
    return 'IOPS - Block Size x RW Mix Matrix';
  }
  
  /**
   * this sub-class method should return a hash of test parameters - these are
   * label/value pairs displayed in the bottom 8 rows of the Test Parameters 
   * columns in the report page headers
   * @return array
   */
  protected function getTestParameters() {
    return array(
      'Test Stimulus 1' => 'IOPS Loop',
      '&nbsp;&nbsp;RW Mix' => 'Outer Loop',
      '&nbsp;&nbsp;Block Sizes' => 'Inner Loop',
      '&nbsp;&nbsp;TOIO - TC/QD' => sprintf('TC %d/QD %d', $this->options['threads_total'], $this->options['oio_per_thread']),
      '&nbsp;&nbsp;Steady State' => $this->wdpc !== NULL ? sprintf('%d - %d%s', $this->wdpcComplete - 4, $this->wdpcComplete, $this->wdpc ? '' : ' (NOT ACHIEVED)') : 'N/A',
      'Test Stimulus 2' => 'N/A',
      '&nbsp;&nbsp;TOIO - TC/QD ' => 'N/A',
      '&nbsp;&nbsp;Steady State ' => 'N/A'
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
      if (preg_match('/^x[0-9]+\-([0-9]+)_([0-9]+)\-([0-9]+[mkb])\-/', $job, $m) && isset($jobs[$job]['write']['iops'])) {
        $key = sprintf('%s_%s_%s', $m[3], $m[1], $m[2]);
        if (!isset($metrics[$key])) $metrics[$key] = array();
        $metrics[$key][] = $jobs[$job]['read']['iops'] + $jobs[$job]['write']['iops'];
      }
    }
    foreach($metrics as $key => $vals) {
      $metrics[$key] = round(array_sum($vals)/count($vals));
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
    print_msg(sprintf('Initiating workload dependent preconditioning and steady state for IOPS test'), $this->verbose, __FILE__, __LINE__);
    $max = $this->options['ss_max_rounds'];
    // (0/100,4k)(65/35,64k)(100/0,1024k) IOPS => used for steady state verification
    $ssMetrics['4k'] = array();
    $ssMetrics['64k'] = array();
    $ssMetrics['1m'] = array();
    $blockSizes = $this->filterBlocksizes(array('1m', '128k', '64k', '32k', '16k', '8k', '4k', '512b'));
    $workloads = $this->filterWorkloads(array('100/0', '95/5', '65/35', '50/50', '35/65', '5/95', '0/100'));
    $lastBlockSize = $blockSizes[count($blockSizes) - 1];

    for($x=1; $x<=$max; $x++) {
      $ss = array('4k'=>FALSE, '64k'=>FALSE, '1m'=>FALSE);
      foreach($workloads as $rw) {
        $pieces = explode('/', $rw);
        $read = $pieces[0]*1;
        $write = $pieces[1]*1;
        $rwmixread = 100 - $write;
        foreach($blockSizes as $bs) {
          $name = sprintf('x%d-%s-%s-rand', $x, str_replace('/', '_', $rw), $bs);
          print_msg(sprintf('Executing random IO test iteration for round %d of %d, rw ratio %s and block size %s', $x, $max, $rw, $bs), $this->verbose, __FILE__, __LINE__);
          $params = array('blocksize' => $bs, 'name' => $name, 'runtime' => $this->options['wd_test_duration'], 'time_based' => FALSE);
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
          if (($rw == '0/100' && $bs == '4k')||($rw == '65/35' && $bs == '64k')||($rw == '100/0' && $bs == '1m')) {
            $iops = $results['jobs'][0]['write']['iops'] + $results['jobs'][0]['read']['iops'];
            print_msg(sprintf('Added IOPS metric %d for steady state verification', $iops), $this->verbose, __FILE__, __LINE__);
            $ssMetrics[$bs][$x] = $iops;
          }
          // check for steady state
          if ($x >= 5 && $rw == '0/100' && $bs == $lastBlockSize){
            foreach(array('4k','64k','1m') as $checkbs){
              $metrics = array();
              for($i=4; $i>=0; $i--) $metrics[$x-$i] = $ssMetrics[$checkbs][$x-$i];
              print_msg(sprintf('Test round %d of %d complete and >= 5 rounds finished - checking if steady state has been achieved using %s write IOPS metrics [%s],[%s]', $x, $max, $checkbs, implode(',', array_keys($metrics)), implode(',', $metrics)), $this->verbose, __FILE__, __LINE__);
              if ($this->isSteadyState($metrics, $checkbs)) {
                print_msg(sprintf('%s steady state achieved',$checkbs), $this->verbose, __FILE__, __LINE__);
                $ss[$checkbs] = TRUE;
              }
              else{
                print_msg(sprintf('%s steady state NOT achieved',$checkbs), $this->verbose, __FILE__, __LINE__);
                break;  // no need to check others
              } 
            }
            // need all Steady State Tracking Variables meet the Steady State requirement
            if($ss['4k']===TRUE && $ss['64k']===TRUE && $ss['1m']===TRUE){
              $status = TRUE;
              print_msg(sprintf('All steady state achieved'), $this->verbose, __FILE__, __LINE__);
            }

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
}
?>
