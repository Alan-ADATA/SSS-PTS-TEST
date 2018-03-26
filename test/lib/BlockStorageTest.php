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
 * Base abstract class for block storage testing. This class implements base  
 * testing logic and is extended by each of the 8 specific block storage 
 * tests. It also contains some static utility methods
 */
require_once(dirname(__FILE__) . '/util.php');
ini_set('memory_limit', '512m');
$block_storage_test_start_time = time();
date_default_timezone_set('UTC');

abstract class BlockStorageTest {
  
  /**
   * fio test file name for volume based test targets
   */
  const BLOCK_STORAGE_TEST_FILE_NAME = 'fio-test';
  
  /**
   * name of the file where serializes options should be written to for given 
   * test iteration
   */
  const BLOCK_STORAGE_TEST_OPTIONS_FILE_NAME = '.options';
  
  /**
   * free space buffer to use for volume type test targets
   */
  const BLOCK_STORAGE_TEST_FREE_SPACE_BUFFER = 100;
  
  /**
   * formula to use for --wd_sleep_between efs
   */
  const BLOCK_STORAGE_TEST_WD_SLEEP_BETWEEN_EFS = '{duration}*({size} >= 4096 ? 0 : ({size} >= 1024 ? 1 : ({size} >= 512 ? 4 : ({size} >= 256 ? 8 : 200))))';
  
  /**
   * formula to use for --wd_sleep_between gp2
   */
  const BLOCK_STORAGE_TEST_WD_SLEEP_BETWEEN_GP2 = '{duration}*({size} >= 1000 ? 0 : ({size} >= 750 ? 0.33 : ({size} >= 500 ? 1 : ({size} >= 250 ? 3 : ({size} >= 214 ? 3.6734 : ({size} >= 100 ? 9 : 29))))))';
  
  /**
   * formula to use for --wd_sleep_between sc1
   */
  const BLOCK_STORAGE_TEST_WD_SLEEP_BETWEEN_SC1 = '{duration}*({size} >= 20833 ? 0 : (({size} >= 3125 ? 250 : ({size} >= 3000 ? 240 : ({size} >= 2000 ? 160 : ({size} >= 1000 ? 80 : 40)))) - (({size}/1000)*12))/(({size}/1000)*12))';
  
  /**
   * formula to use for --wd_sleep_between st1
   */
  const BLOCK_STORAGE_TEST_WD_SLEEP_BETWEEN_ST1 = '{duration}*({size} >= 12500 ? 0 : (({size} >= 2000 ? 500 : ({size} >= 1000 ? 250 : 125)) - (({size}/1000)*40))/(({size}/1000)*40))';
  
  /**
   * true if targets are devices
   */
  protected $deviceTargets = FALSE;
  
  /**
   * graph colors array
   */
  protected $graphColors = array();
  
  /**
   * used to record fio test results. the key in this array is the test 
   * sequence name and the value is an ordered array of fio results
   */
  protected $fio = array();
  
  /**
   * run options for test implementations
   */
  protected $options = NULL;
  
  /**
   * stores purge methods used during testing. indexed by target with values
   * secureerase, trim or zero (if target not present, not purged)
   */
  private $purgeMethods = array();
  
  /**
   * sub-classes may override this attribute value to eliminate the workload
   * independent pre-conditioning step
   */
  protected $skipWipc = FALSE;
  
  /**
   * used to store steady state data
   */
  protected $ssData = array();
  
  /**
   * used to store sub-tests used for report generation
   */
  protected $subtests = array();
  
  /**
   * used to store reference to the super test for subtests
   */
  protected $controller;
  
  /**
   * the test identifier for the instantiated controller
   */
  protected $test;
  
  /**
   * used for determining whether or not to render verbose output
   */
  protected $verbose = FALSE;
  
  /**
   * true if targets are volumes
   */
  protected $volumeTargets = FALSE;
  
  /**
   * the workload dependent preconditioning status. one of the following:
   *   TRUE:  successful
   *   FALSE: successful, but steady state not achieved
   *   NULL:  not successful
   */
  protected $wdpc = NULL;
  
  /**
   * the termination interval for workload dependent preconditioning (i.e. the 
   * X value when steady state was achieved)
   */
  public $wdpcComplete;
  
  /**
   * the number of tests per X intervals during workload dependent 
   * preconditioning
   */
  public $wdpcIntervals;
  
  /**
   * set to TRUE by the wipc method if workload independent preconditioning is
   * successful
   */
  protected $wipc = FALSE;
  
  
  /**
   * adjusts a value to the best matching log scale for use on a graph
   * @param float $val the value to adjust
   * @param boolean $min adjust to minimum value?
   * @return float
   */
  private static function adjustLogScale($val, $min=FALSE) {
    $adjusted = NULL;
    if (is_numeric($val) && $val >= 0) {
      $adjusted = 1;
      if ($min) {
        while($val > $adjusted) $adjusted *= 10;
        if ($adjusted > 1) $adjusted /= 10;
      }
      else {
        while($val < $adjusted) $adjusted *= 10;
      }
    }
    return $adjusted;
  }
  
  /**
   * Invokes df for the $target specified. Returns a hash with the following
   * keys: 
   *   source => file system source
   *   fstype => file system type
   *   size => size (KB)
   *   used => used (KB)
   *   avail => available (KB)
   *   pcent => percentage used
   *   target => mount point
   * @param string $target the path to check
   * @param array $args optional df command line arguments. A hash containing 
   * argument name/values. Do not preceed names with - or --. For flags, set 
   * the value to NULL
   * @return array
   */
  public static function df($target, $args=NULL) {
    $df = NULL;
    $cargs = '-T';
    if (is_array($args)) {
      foreach($args as $a => $v) $cargs .= sprintf(' -%s%s%s%s', strlen($a) > 1 ? '-' : '', $a, $v !== NULL ? (strlen($a) > 1 ? '=' : ' ') : '', $v !== NULL ? $v : '');
    }
    if (($buffer = trim(shell_exec(sprintf('df %s %s', $cargs, $target)))) && preg_match('/ilesystem/', $buffer)) {
      $pieces = explode("\n", $buffer);
      if (preg_match('/^([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s*$/', $pieces[1], $m)) {
        $df = array();
        $df['source'] = $m[1];
        $df['fstype'] = $m[2];
        $df['size'] = $m[3];
        $df['used'] = $m[4];
        $df['avail'] = $m[5];
        $df['pcent'] = $m[6];
        $df['target'] = $m[7];
      }
    }
    return $df;
  }
  
  /**
   * removes any skip_blocksize arguments from $blocksizes
   * @param array $blocksizes the blocksizes to filter
   * @return array
   */
  protected function filterBlocksizes($blocksizes) {
    $nblocksizes = array();
    foreach($blocksizes as $bs) {
      if (!isset($this->options['skip_blocksize']) || !in_array($bs, $this->options['skip_blocksize'])) $nblocksizes[] = $bs;
    }
    return $nblocksizes;
  }
  
  
  /**
   * removes any skip_workload arguments from $workloads
   * @param array $workloads the workloads to filter
   * @return array
   */
  protected function filterWorkloads($workloads) {
    $nworkloads = array();
    foreach($workloads as $rw) {
      if (!isset($this->options['skip_workload']) || !in_array($rw, $this->options['skip_workload'])) $nworkloads[] = $rw;
    }
    return $nworkloads;
  }
  
  /**
   * Runs fio based on the runtime parameters and $options specified. If 
   * successful, returns TRUE on success, FALSE otherwise. fio execution 
   * results will be stored in the $fio instance variable (indexed by $step)
   * @param array $options additional fio options (used in addition to the 
   * default options)
   * @param string $step the identifier of the current test step (e.g. 
   * precondition)
   * @param string $target specific target to use (otherwise all targets will
   * be assumed)
   * @param boolean $concurrent whether or not to invoke fio concurrently 
   * on all targets or sequentially, one at a time
   * @param boolean $offsetThreads if TRUE, threads will be offset such that
   * individual threads do not read/write the same sections of the targets
   * @return boolean
   */
  protected function fio($options, $step, $target=NULL, $concurrent=TRUE, $offsetThreads=FALSE) {
    $success = FALSE;
    $targets = $target ? array($target) : $this->options['target'];
    
    // sequential execution
    if (!$concurrent && count($targets) > 1) {
      $success = TRUE;
      print_msg(sprintf('Starting sequential fio execution for %d targets and step %s', count($targets), $step), $this->verbose, __FILE__, __LINE__);
      foreach($targets as $target) {
        if (!$this->fio($options, $step, $target, FALSE)) {
          $success = FALSE;
          break;
        }
      }
    }
    else if ($targets) {
      $cmd = $this->options['fio'];
      $options = array_merge($this->options['fio_options'], $options);
      if (!isset($options['numjobs'])) {
        $options['numjobs'] = $this->options['threads'];
        if ($options['numjobs'] <= 0) $options['numjobs'] = 1;
      }
      if (!isset($options['iodepth'])) $options['iodepth'] = $this->options['oio_per_thread'];
      if (!isset($options['filename'])) {
        $filename = '';
        foreach($targets as $target) $filename .= ($filename ? ':' : '') . (self::getDevice($target) == $target ? $target : $target . '/'. self::BLOCK_STORAGE_TEST_FILE_NAME);
        $options['filename'] = $filename; 
      }
      $options['group_reporting'] = FALSE;
      $options['output-format'] =   'json';
      if (!isset($options['name'])) $options['name'] = sprintf('%s-%d', $step, isset($this->fio[$step]) ? count($this->fio[$step]) + 1 : 1);
      // determine size
      if (!isset($options['size'])) {
        // for devices use relative size
        if ($this->deviceTargets && (!$offsetThreads || $options['numjobs'] == 1)) $options['size'] = $this->options['active_range'] . '%';
        // for volumes use fixed size (total free space - )
        else {
          $size = NULL;
          foreach($targets as $target) {
            $free = self::getFreeSpace($target);
            if ($size === NULL || $free < $size) $size = $free;
          }
          // reduce size according to active range (if < 100%) or free space buffer
          if ($this->options['active_range'] < 100) $size *= ($this->options['active_range'] * 0.01);
          else if (!$this->deviceTargets) $size -= self::BLOCK_STORAGE_TEST_FREE_SPACE_BUFFER;
          $size = round($size);
          // not enough free space to continue
          if ($size < 1) {
            print_msg(sprintf('Unable to initiate fio testing for volume targets because there is insufficient free space'), $this->verbose, __FILE__, __LINE__, TRUE);
            return FALSE;
          }
          else {
            if ($offsetThreads && $options['numjobs'] > 1) {
              $numjobs = $options['numjobs'];
              $threadSize = round($size/$numjobs);
              print_msg(sprintf('Testing %s type targets using offset threads. Total test size is %d MB. Size per thread/offset_increment is %d MB', $this->deviceTargets ? 'device' : 'volume', $size, $threadSize), $this->verbose, __FILE__, __LINE__);
              $options['size'] = $threadSize . 'm';
              $options['offset_increment'] = $threadSize . 'm';
            }
            else {
              print_msg(sprintf('Testing volume type targets using size %d MB', $size), $this->verbose, __FILE__, __LINE__);
              $options['size'] = $size . 'm';
            }
            // register shutdown method so test files are deleted
            if (!$this->deviceTargets) {
              foreach($targets as $target) {
                if ($this->volumeTargets && !file_exists($file = sprintf('%s/%s', $target, self::BLOCK_STORAGE_TEST_FILE_NAME))) register_shutdown_function('unlink', $file);
              } 
            }
          }
        }
      }
      // use random map?
      if (!isset($this->options['randommap']) && isset($options['rw']) && preg_match('/rand/', $options['rw'])) {
        $options['norandommap'] = FALSE;
        $options['randrepeat'] = 0;
      }
      // use sequential IO only
      if (isset($this->options['sequential_only']) && isset($options['rw']) && preg_match('/rand/', $options['rw'])) $options['rw'] = str_replace('rand', '', $options['rw']);
      
      $jname = $options['name'];
      unset($options['name']);
      $jtargets = explode(':', $options['filename']);
      unset($options['filename']);
      $cmd .= ' --name=global';
      foreach($options as $opt => $val) $cmd .= sprintf(' --%s%s', $opt, $val !== FALSE && $val !== NULL ? '=' . $val : '');
      foreach($jtargets as $i => $jtarget) $cmd .= sprintf(' --name=%s-%d --filename=%s', $jname, $i+1, $jtarget);
      print_msg(sprintf('Starting fio using command: %s', $cmd), $this->verbose, __FILE__, __LINE__);
      $start = time();
      $started = date('Y-m-d H:i:s');
      if ($result = json_decode(trim(shell_exec($cmd . ' 2>/dev/null')), TRUE)) {
        $iops = NULL;
        if ($success = isset($result['jobs'][0]['error']) && !$result['jobs'][0]['error']) {
          $iops = (isset($result['jobs'][0]['read']['iops']) ? $result['jobs'][0]['read']['iops'] : 0) + (isset($result['jobs'][0]['write']['iops']) ? $result['jobs'][0]['write']['iops'] : 0);
          $mbps = (isset($result['jobs'][0]['read']['bw']) ? $result['jobs'][0]['read']['bw'] : 0) + (isset($result['jobs'][0]['write']['bw']) ? $result['jobs'][0]['write']['bw'] : 0);
          $mbps = round($mbps/1024, 2);
          if (!isset($this->fio[$step])) $this->fio[$step] = array();
          $result['jobs'][0]['fio_command'] = $cmd;
          $result['jobs'][0]['started'] = $started;
          $result['jobs'][0]['stopped'] = date('Y-m-d H:i:s');
          $this->fio[$step][] = $result;
          print_msg(sprintf('fio execution successful for step %s with %d IOPS (%s MB/s). There are now %d results for this step', $step, $iops, $mbps, count($this->fio[$step])), $this->verbose, __FILE__, __LINE__);
          // wd_sleep_between parameter
          if ($step == 'wdpc' && isset($this->options['wd_sleep_between'])) {
            $duration = time() - $start;
            $volumes = 0;
            $sizes = array();
            foreach($this->options['target'] as $target) {
              $sizes[] = self::getFreeSpace($target)/1024;
              $volumes++;
            }
            $size = round(array_sum($sizes)/count($sizes));
            if (isset($this->options['wd_sleep_between_size']) && file_exists($this->options['wd_sleep_between_size'])) {
              $nsize = ((filesize($this->options['wd_sleep_between_size'])/1024)/1024)/1024;
              print_msg(sprintf('Changed {size} value from %s GB to %s GB due to parameter --wd_sleep_between_size %s', $size, $nsize, $this->options['wd_sleep_between_size']), $this->verbose, __FILE__, __LINE__);
              $size = $nsize;
            }
            $formula = str_replace(' ', '', $this->options['wd_sleep_between'] == 'gp2' ? self::BLOCK_STORAGE_TEST_WD_SLEEP_BETWEEN_GP2 : ($this->options['wd_sleep_between'] == 'sc1' ? self::BLOCK_STORAGE_TEST_WD_SLEEP_BETWEEN_SC1 : ($this->options['wd_sleep_between'] == 'st1' ? self::BLOCK_STORAGE_TEST_WD_SLEEP_BETWEEN_ST1 : ($this->options['wd_sleep_between'] == 'efs' ? self::BLOCK_STORAGE_TEST_WD_SLEEP_BETWEEN_EFS : $this->options['wd_sleep_between']))));
            $formula = str_replace('{duration}', $duration, $formula);
            $formula = str_replace('{size}', $size, $formula);
            $formula = str_replace('{volumes}', $volumes, $formula);
            eval(sprintf('$value=round(%s);', $formula));
            $value *= 1;
            // add 10% buffer for EFS
            if ($this->options['wd_sleep_between'] == 'efs') $value *= 1.1;
            print_msg(sprintf('--wd_sleep_between "%s" evaluated to %d using formula "%s" and parameters duration=%d; volumes=%d; size=%d', $this->options['wd_sleep_between'], $value, $formula, $duration, $volumes, $size), $this->verbose, __FILE__, __LINE__);
            if ($value > 0) {
              print_msg(sprintf('sleeping for %d seconds for --wd_sleep_between', $value), $this->verbose, __FILE__, __LINE__);
              sleep($value);
            }
          }
        }
        else print_msg(sprintf('fio execution failed with an error for step %s', $step), $this->verbose, __FILE__, __LINE__, TRUE);
      }
      else print_msg(sprintf('fio execution failed for step %s', $step), $this->verbose, __FILE__, __LINE__, TRUE);
    }
    return $success;
  }
  
  /**
   * generates a 3d chart based on the parameters provided. return value is 
   * javascript and html that will render the chart. returns NULL on error
   * @param string $section the section identifier for the chart
   * @param array $series highcharts compatible array of data series
   * @param array $settings optional array containing Highcharts.Chart settings
   * @param string $zAxisTitle optional title for the z axis (displayed above 
   * the legend on the right side)
   * @return string
   */
  protected final function generate3dChart($section, $series, $settings=array(), $zAxisTitle=NULL) {
    $chart = NULL;
    if (isset($this->options['no3dcharts']) && $this->options['no3dcharts']) $chart = '3D charts disabled - see preceding tabular data';
    else if ($section && is_array($series) && count($series) && is_array($settings)) {
      // assign series colors
      if (!isset($settings['colors'])) $settings['colors'] = $this->getGraphColors();
      if (!isset($settings['series'])) $settings['series'] = $series;
      // assign other highchart graph settings
      $stacks = array();
      foreach(array_keys($settings['series']) as $i) {
        if (isset($settings['series'][$i]['stack']) && !in_array($settings['series'][$i]['stack'], $stacks)) $stacks[] = $settings['series'][$i]['stack'];
      }
      if (!isset($settings['chart'])) $settings['chart'] = array();
      $settings['chart']['type'] = 'column';
      if (!isset($settings['chart']['options3d'])) $settings['chart']['options3d'] = array();
      $settings['chart']['options3d']['enabled'] = TRUE;
      if (!isset($settings['plotOptions'])) $settings['plotOptions'] = array();
      if (!isset($settings['plotOptions']['column'])) $settings['plotOptions']['column'] = array();
      if (!isset($settings['plotOptions']['column']['depth'])) $settings['plotOptions']['column']['depth'] = 35;
      if (!isset($settings['plotOptions']['column']['pointWidth'])) $settings['plotOptions']['column']['pointWidth'] = $settings['plotOptions']['column']['depth'];
      $settings['plotOptions']['column']['stacking'] = TRUE;
      $settings['plotOptions']['column']['grouping'] = FALSE;
      if (!isset($settings['plotOptions']['series'])) $settings['plotOptions']['series'] = array();
      $settings['plotOptions']['series']['animation'] = FALSE;
      if (!isset($settings['plotOptions']['column']['groupZPadding'])) $settings['plotOptions']['column']['groupZPadding'] = 15;
      if (!isset($settings['chart']['options3d']['alpha'])) $settings['chart']['options3d']['alpha'] = 20;
      if (!isset($settings['chart']['options3d']['beta'])) $settings['chart']['options3d']['beta'] = 20;
      if (!isset($settings['chart']['options3d']['depth'])) $settings['chart']['options3d']['depth'] = ($settings['plotOptions']['column']['depth'] + $settings['plotOptions']['column']['groupZPadding'])*count($stacks);
      $settings['chart']['marginTop'] = ($settings['chart']['options3d']['alpha']*count($stacks) - 10);
      if (!isset($settings['title'])) $settings['title'] = array();
      if (!isset($settings['title']['text'])) $settings['title']['text'] = '';
      if (!isset($settings['legend'])) $settings['legend'] = array();
      if (!isset($settings['legend']['align'])) $settings['legend']['align'] = 'right';
      if (!isset($settings['legend']['layout'])) $settings['legend']['layout'] = 'vertical';
      if (!isset($settings['legend']['verticalAlign'])) $settings['legend']['verticalAlign'] = 'top';
      if ($zAxisTitle && !isset($settings['legend']['title'])) $settings['legend']['title'] = array('text' => $zAxisTitle);
      if (!isset($settings['legend']['itemMarginTop'])) $settings['legend']['itemMarginTop'] = 5;
      if (!isset($settings['legend']['reversed'])) $settings['legend']['reversed'] = TRUE;
      if (!isset($settings['credits'])) $settings['credits'] = array();
      if (!isset($settings['credits']['enabled'])) $settings['credits']['enabled'] = FALSE; 
      $chart = sprintf("<figure id=\"%s-%s\"></figure>\n", $this->test, $section);
      $chart .= sprintf('<script>$("#%s-%s").highcharts(%s);</script>', $this->test, $section, json_encode($settings));      
    }
    return $chart;
  }
  
  /**
   * generates and zips fio JSON output files. Returns TRUE on success, FALSE 
   * otherwise
   * @param string $dir optional directory where archive should be generated 
   * in. If not specified, --output will be used
   * @param string $suffix optional file name suffix
   * @return boolean
   */
  public function generateJson($dir=NULL, $suffix=NULL) {
    if (isset($this->options['nojson']) && $this->options['nojson']) return FALSE;
    
    $generated = FALSE;
    if (!$dir) $dir = $this->options['output'];
    
    // serialize options
    $this->options['test_stopped'] = date('Y-m-d H:i:s');
    // add steady state data
    if (isset($this->ssData['metrics'])) {
      foreach($this->ssData as $key => $val) {
        if ($key == 'metrics') continue;
        if (preg_match_all('/[A-Z]/', $key, $m)) {
          foreach(array_unique($m[0]) as $char) $key = str_replace($char, '_' . strtolower($char), $key);
        }
        if (is_numeric($val)) $val = round($val, 4);
        $this->options['ss_' . $key] = $val;
      }
      $this->options['ss_start'] = $this->wdpcComplete - 4;
      $this->options['ss_stop'] = $this->wdpcComplete;
      $this->options['ss_rounds'] = sprintf('%d-%d', $this->wdpcComplete - 4, $this->wdpcComplete);
    }
    // add purge methods
    $purgeMethods = array();
    print_msg(sprintf('Determining purge methods for targets %s from purge methods [%s][%s]', implode(', ', $this->options['target']), implode(', ', array_keys($this->purgeMethods)), implode(', ', $this->purgeMethods)), $this->verbose, __FILE__, __LINE__);
    if ($this->subtests) {
      foreach(array_keys($this->subtests) as $i) {
        print_msg(sprintf('Sub-test purge methods [%s][%s]', implode(', ', array_keys($this->subtests[$i]->purgeMethods)), implode(', ', $this->subtests[$i]->purgeMethods)), $this->verbose, __FILE__, __LINE__);
      }
    }
    if ($this->controller) print_msg(sprintf('Super-test purge methods [%s][%s]', implode(', ', array_keys($this->controller->purgeMethods)), implode(', ', $this->controller->purgeMethods)), $this->verbose, __FILE__, __LINE__);
    foreach($this->options['target'] as $target) {
      $purgeMethod = isset($this->purgeMethods[$target]) ? $this->purgeMethods[$target] : NULL;
      if (!$purgeMethod && $this->controller && isset($this->controller->purgeMethods[$target])) $purgeMethod = $this->controller->purgeMethods[$target];
      if (!$purgeMethod && $this->subtests) {
        foreach(array_keys($this->subtests) as $i) {
          if (isset($this->subtests[$i]->purgeMethods[$target])) {
            $purgeMethod = $this->subtests[$i]->purgeMethods[$target];
            break;
          }
        }
      }
      $purgeMethods[] = sprintf('%s: %s', $target, $purgeMethod ? $purgeMethod : 'none');
    }
    $this->options['purge_methods'] = $purgeMethods;
    $this->options['purge'] = str_replace($this->options['target'][0].": ", "",$this->options['purge_methods'][0]);
    
    // add fio version
    if (preg_match('/([0-9][0-9\.]+[0-9])/', trim(shell_exec('fio --version')), $m)) $this->options['fio_version'] = $m[1];
    $ofile = sprintf('%s/%s', $dir, self::BLOCK_STORAGE_TEST_OPTIONS_FILE_NAME);
    if (is_dir($dir) && is_writable($dir)) {
      $fp = fopen($ofile, 'w');
      fwrite($fp, serialize($this->options));
      fclose($fp); 
    }
    
    if (is_dir($dir) && is_writable($dir) && count($this->fio) && $this->wdpcComplete /*&& $this->wdpcIntervals*/) {
      $ssStart = isset($this->fio['wdpc']) ? ($this->wdpcComplete - 5)*$this->wdpcIntervals : NULL;
      print_msg(sprintf('Generating %s JSON output files in directory %s using steady state start index %d', $this->test, $dir, $ssStart), $this->verbose, __FILE__, __LINE__);
      
      $json = array();
      foreach($this->fio as $step => $jobs) {
        foreach($jobs as $i => $job) {
          if (isset($job['jobs'][0]['jobname'])) {
            $ssmw = $step == 'wdpc' && $i >= $ssStart;
            $name = sprintf('%s%s', $job['jobs'][0]['jobname'], $ssmw ? '-ssmw' : '');
            print_msg(sprintf('Added %s job %s to JSON output', $this->test, $name), $this->verbose, __FILE__, __LINE__);
            $job['jobs'][0]['jobname'] = $name;
            if (!$json) $json = $job;
            else $json['jobs'][] = $job['jobs'][0];
          }
        }
      }
      if ($json) {
        $file = sprintf('%s/fio-%s%s.json', $dir, $this->test, $suffix ? '-' . $suffix : '');
        if ($fp = fopen($file, 'w')) {
          fwrite($fp, json_encode($json));
          fclose($fp);
          print_msg(sprintf('Successfully wrote fio metrics to output file %s for test %s', $file, $this->test), $this->verbose, __FILE__, __LINE__);
        }
        else print_msg(sprintf('Unable to generate %s JSON output - cannot open file %s', $this->test, $file), $this->verbose, __FILE__, __LINE__, TRUE);
      }
      else print_msg(sprintf('Unable to generate %s JSON output - no jobs', $this->test), $this->verbose, __FILE__, __LINE__, TRUE);
    }
    else print_msg(sprintf('Unable to generate %s JSON output in directory %s. fio steps: %d; wdpcComplete=%d; wdpcIntervals=%d', $this->test, $dir, count($this->fio), $this->wdpcComplete, $this->wdpcIntervals), $this->verbose, __FILE__, __LINE__, TRUE);
    
    if (is_dir($dir) && is_writable($dir) && ($metrics = $this->jobMetrics())) {
      foreach($metrics as $key => $val) {
        unset($metrics[$key]);
        $metrics[$this->test . '_' . $key] = $val;
      }
      $file = sprintf('%s/%s.json', $dir, $this->test);
      // output file already exists - merge results
      if (file_exists($file) && ($existing = json_decode(file_get_contents($file), TRUE))) $metrics = array_merge($existing, $metrics);
      if ($fp = fopen($file, 'w')) {
        fwrite($fp, json_encode($metrics));
        fclose($fp);
        print_msg(sprintf('Successfully wrote job metrics to output file %s for test %s', $file, $this->test), $this->verbose, __FILE__, __LINE__);
      }
      else print_msg(sprintf('Unable to write job metrics to output file %s for test %s', $file, $this->test), $this->verbose, __FILE__, __LINE__, TRUE);
    }
    else if (is_dir($dir) && is_writable($dir)) print_msg(sprintf('Unable to retrieve job metrics for test %s', $this->test), $this->verbose, __FILE__, __LINE__, TRUE);
    
    return $generated;
  }
  
  /**
   * generates a line chart based on the parameters provided. return value is 
   * the name of the image which may in turn be used in an image element for 
   * a content section. returns NULL on error
   * @param string $dir the directory where the line chart should be generated
   * @param string $section the section identifier for the line chart
   * @param array $coords either a single array of tuples representing the x/y
   * values, or a hash or tuple arrays indexed by the name of each set of data
   * points. coordinates should have the same 
   * @param string $xlabel optional x label
   * @param string $ylabel optional y label
   * @param string $title optional graph title
   * @param array $settings optional array of custom gnuplot settings. the 
   * following special settings are supported:
   *   height: the graph height
   *   lines:     optional line styles (indexed by line #)
   *   nogrid:    don't add y axis grid lines
   *   nokey:     don't show the plot key/legend
   *   nolinespoints: don't use linespoints
   *   xFloatPrec: x float precision
   *   xLogscale: use logscale for the x axis
   *   xMin:      min value for the x axis tics - may be a percentage relative to 
   *              the lowest value
   *   xMax:      max value for the x axis tics - may be a percentage relative to 
   *              the highest value
   *   xTics:     the number of x tics to show (default 8)
   *   yFloatPrec: y float precision
   *   yLogscale: use logscale for the y axis
   *   yMin:      min value for the x axis tics - may be a percentage relative to 
   *              the lowest value
   *   yMax:      max value for the y axis tics - may be a percentage relative to 
   *              the highest value
   *   yTics:     the number of y tics to show (default 8)
   * 
   * xMin, xMax, yMin and yMax all default to the same value as the other for 
   * percentages and 15% otherwise if only 1 is set for a given 
   * axis. If neither are specified, gnuplot will auto assign the tics. If xMin
   * or xMax are specified, but not xTics, xTics defaults to 8
   * @param boolean $html whether or not to return the html <img element or just
   * the name of the file
   * @param boolean $histogram whether or not to render the chart using a 
   * histogram. If TRUE, $coords should represent all of the y values for a 
   * given X. The $coords hash key will be used as the X label and the value(s) 
   * rendered using a clustered histogram (grouped column chart)
   * @return string
   */
  protected final function generateLineChart($dir, $section, $coords, $xlabel=NULL, $ylabel=NULL, $title=NULL, $settings=NULL, $html=TRUE, $histogram=FALSE) {
    print_msg(sprintf('Generating line chart in %s for test %s and section %s with %d coords', $dir, $this->test, $section, count($coords)), $this->verbose, __FILE__, __LINE__);
    
    $chart = NULL;
    $script = sprintf('%s/%s-%s.pg', $dir, $this->test, $section);
    $dfile = sprintf('%s/%s-%s.dat', $dir, $this->test, $section);
    if (is_array($coords) && ($fp = fopen($script, 'w')) && ($df = fopen($dfile, 'w'))) {
      $colors = $this->getGraphColors();
      $xFloatPrec = isset($settings['xFloatPrec']) && is_numeric($settings['xFloatPrec']) ? $settings['xFloatPrec'] : 0;
      $yFloatPrec = isset($settings['yFloatPrec']) && is_numeric($settings['yFloatPrec']) ? $settings['yFloatPrec'] : 0;
      
      // just one array of tuples
      if (isset($coords[0])) $coords[''] = array($coords);
      
      // determine max points/write data file header
      $maxPoints = NULL;
      foreach(array_keys($coords) as $i => $key) {
        if ($maxPoints === NULL || count($coords[$key]) > $maxPoints) $maxPoints = count($coords[$key]);
        if (!$histogram) fwrite($df, sprintf("%s%s%s\t%s%s", $i > 0 ? "\t" : '', $key ? $key . ' ' : '', $xlabel ? $xlabel : 'X', $key ? $key . ' ' : '', $ylabel ? $ylabel : 'Y'));
      }
      if (!$histogram) fwrite($df, "\n");
      
      // determine value ranges and generate data file
      $minX = NULL;
      $maxX = NULL;
      $minY = NULL;
      $maxY = NULL;
      if ($histogram) {
        foreach($coords as $x => $points) {
          if (is_numeric($x) && ($minX === NULL || $x < $minX)) $minX = $x;
          if (is_numeric($x) && $x > $maxX) $maxX = $x;
          fwrite($df, $x);
          for($n=0; $n<$maxPoints; $n++) {
            $y = isset($points[$n]) ? $points[$n]*1 : '';
            if (is_numeric($y) && ($minY === NULL || $y < $minY)) $minY = $y;
            if (is_numeric($y) && $y > $maxY) $maxY = $y;
            fwrite($df, sprintf("\t%s", $y));
          }
          fwrite($df, "\n");
        }
      }
      else {
        for($n=0; $n<$maxPoints; $n++) {
          foreach(array_keys($coords) as $i => $key) {
            $x = isset($coords[$key][$n][0]) ? $coords[$key][$n][0] : '';
            if (is_numeric($x) && ($minX === NULL || $x < $minX)) $minX = $x;
            if (is_numeric($x) && $x > $maxX) $maxX = $x;
            $y = isset($coords[$key][$n][1]) ? $coords[$key][$n][1] : '';
            if (is_numeric($y) && ($minY === NULL || $y < $minY)) $minY = $y;
            if (is_numeric($y) && $y > $maxY) $maxY = $y;
            fwrite($df, sprintf("%s%s\t%s", $i > 0 ? "\t" : '', $x, $y));
          }
          fwrite($df, "\n");
        } 
      }
      fclose($df);
      
      // Coordinates are all 0 - cannot generate graphs
      if (!$maxY) {
        print_msg('Failed to generate line chart - Y coordinates are all 0', $this->verbose, __FILE__, __LINE__, TRUE);
        return NULL;
      }
      
      // determine x tic settings
      $xMin = isset($settings['xMin']) ? $settings['xMin'] : NULL;
      $xMax = isset($settings['xMax']) ? $settings['xMax'] : NULL;
      $xTics = isset($settings['xTics']) ? $settings['xTics'] : NULL;
      if (!isset($xMin) && (isset($xMax) || $xTics)) $xMin = isset($xMax) && preg_match('/%/', $xMax) ? $xMax : '15%';
      if (!isset($xMax) && (isset($xMin) || $xTics)) $xMax = isset($xMin) && preg_match('/%/', $xMin) ? $xMin : '15%';
      if (!isset($xMin)) $xMin = $minX;
      if (!isset($xMax)) $xMax = $maxX;
      if (preg_match('/^([0-9\.]+)%$/', $xMin, $m)) {
        $xMin = floor($minX - ($minX*($m[1]*0.01)));
        if ($xMin < 0) $xMin = 0;
      }
      if (preg_match('/^([0-9\.]+)%$/', $xMax, $m)) $xMax = ceil($maxX + ($maxX*($m[1]*0.01)));
      if (!$xTics) $xTics = 8;
      $xDiff = $xMax - $xMin;
      $xStep = floor($xDiff/$xTics);
      if ($xStep < 1) $xStep = 1;
      
      // determine y tic settings
      $yMin = isset($settings['yMin']) ? $settings['yMin'] : NULL;
      $yMax = isset($settings['yMax']) ? $settings['yMax'] : NULL;
      $yTics = isset($settings['yTics']) ? $settings['yTics'] : NULL;
      if (!isset($yMin) && (isset($yMax) || $yTics)) $yMin = isset($yMax) && preg_match('/%/', $yMax) ? $yMax : '15%';
      if (!isset($yMax) && (isset($yMin) || $yTics)) $yMax = isset($yMin) && preg_match('/%/', $yMin) ? $yMin : '15%';
      if (isset($yMin) && preg_match('/^([0-9\.]+)%$/', $yMin, $m)) {
        $yMin = floor($minY - ($minY*($m[1]*0.01)));
        if ($yMin < 0) $yMin = 0;
      }
      if (isset($yMin)) {
        if (preg_match('/^([0-9\.]+)%$/', $yMax, $m)) $yMax = ceil($maxY + ($maxY*($m[1]*0.01)));
        if (!$yTics) $yTics = 8;
        $yDiff = $yMax - $yMin;
        $yStep = floor($yDiff/$yTics);
        if ($yStep < 1) $yStep = 1;
      }
      
      $img = sprintf('%s/%s-%s.svg', $dir, $this->test, $section);
      print_msg(sprintf('Generating line chart %s with %d data sets and %d points/set. X Label: %s; Y Label: %s; Title: %s', basename($img), count($coords), $maxPoints, $xlabel, $ylabel, $title), $this->verbose, __FILE__, __LINE__);
      
      fwrite($fp, sprintf("#!%s\n", trim(shell_exec('which gnuplot'))));
      fwrite($fp, "reset\n");
      fwrite($fp, sprintf("set terminal svg dashed size 1024,%d fontfile 'font-svg.css' font 'rfont,%d'\n", isset($settings['height']) ? $settings['height'] : 600, $this->options['font_size']+4));
      // custom settings
      if (is_array($settings)) {
        foreach($settings as $key => $setting) {
          // special settings
          if (in_array($key, array('height', 'lines', 'nogrid', 'nokey', 'nolinespoints', 'xLogscale', 'xMin', 'xMax', 'xTics', 'xFloatPrec', 'yFloatPrec', 'yLogscale', 'yMin', 'yMax', 'yTics', 'usrxTicLabel'))) continue;
          fwrite($fp, "${setting}\n");
        }
      }
      fwrite($fp, "set autoscale keepfix\n");
      fwrite($fp, "set decimal locale\n");
      fwrite($fp, "set format y \"%'10.${yFloatPrec}f\"\n");
      //fwrite($fp, "set format x \"%'10.${xFloatPrec}f\"\n");
      if ($xlabel) fwrite($fp, sprintf("set xlabel \"%s\"\n", $xlabel));
      if (isset($settings['xLogscale'])) {
        if (!isset($settings['xMin'])) $xMin = self::adjustLogScale($xMin, TRUE);
        if (!isset($settings['xMax'])) $xMax = self::adjustLogScale($xMax);
      }
      if ($xMin != $xMax) fwrite($fp, sprintf("set xrange [%d:%d]\n", $xMin, $xMax));
      if (isset($settings['xLogscale'])) fwrite($fp, "set logscale x\n");
      elseif(isset($settings['usrxTicLabel'])) fwrite($fp, sprintf("%s\n",$settings['usrxTicLabel']));
      else if ($xMin != $xMax && !$xFloatPrec) fwrite($fp, sprintf("set xtics %d, %d, %d\n", $xMin, $xStep, $xMax));
      if ($ylabel) fwrite($fp, sprintf("set ylabel \"%s\"\n", $ylabel));
      if (isset($yMin)) {
        if (isset($settings['yLogscale'])) {
          if (!isset($settings['yMin'])) $yMin = self::adjustLogScale($yMin, TRUE);
          if (!isset($settings['yMax'])) $yMax = self::adjustLogScale($yMax);
        }
        if ($yMin != $yMax) fwrite($fp, sprintf("set yrange [%d:%d]\n", $yMin, $yMax));
        if (isset($settings['yLogscale'])) fwrite($fp, "set logscale y\n");
        else if (!$yFloatPrec) fwrite($fp, sprintf("set ytics %d, %d, %d\n", $yMin, $yStep, $yMax));
      }else{
        if (isset($settings['yLogscale'])) fwrite($fp, "set logscale y\n");
      }

      if ($title) fwrite($fp, sprintf("set title \"%s\"\n", $title));
      if (!isset($settings['nokey'])) fwrite($fp, "set key outside center top horizontal reverse\n");
      fwrite($fp, "set grid\n");
      fwrite($fp, sprintf("set style data lines%s\n", !isset($settings['nolinespoints']) || !$settings['nolinespoints'] ? 'points' : ''));
      
      # line styles
      fwrite($fp, "set border linewidth 1.5\n");
      $j = 0;
      foreach(array_keys($coords) as $i => $key) {

        if (isset($settings['lines'][$i+1])) {
          fwrite($fp, sprintf("set style line %d %s\n", $i+1, $settings['lines'][$i+1]));
        } else{
            if (!isset($colors[$i])) {
              fwrite($fp, sprintf("set style line %d lc rgb '%s' lt 1 lw 3\n", $i+1, $colors[$j]));
              $j++;
            }else{
              fwrite($fp, sprintf("set style line %d lc rgb '%s' lt 1 lw 3\n", $i+1, $colors[$i]));
            }            
        }
      }
      if ($histogram) {
        fwrite($fp, "set style fill solid noborder\n");
        fwrite($fp, "set boxwidth 0.9 relative\n");
        fwrite($fp, "set style histogram cluster gap 1\n");
        fwrite($fp, "set style data histogram\n");
      }
      
      fwrite($fp, "set grid noxtics\n");
      if (!isset($settings['nogrid'])) fwrite($fp, "set grid ytics lc rgb '#dddddd' lw 1 lt 0\n");
      else fwrite($fp, "set grid noytics\n");
      fwrite($fp, "set tic scale 0\n");
      fwrite($fp, sprintf("plot \"%s\"", basename($dfile)));
      $colorPtr = 1;
      if ($histogram) {
        for($i=0; $i<$maxPoints; $i++) {
          fwrite($fp, sprintf("%s u %d:xtic(1) ls %d notitle", $i > 0 ? ", \\\n\"\"" : '', $i+2, $colorPtr));
          $colorPtr++;
          if ($colorPtr > count($colors)) $colorPtr = 1;
        }
      }
      else {
        foreach(array_keys($coords) as $i => $key) {
          fwrite($fp, sprintf("%s every ::1 u %d:%d t \"%s\" ls %d", $i > 0 ? ", \\\n\"\"" : '', ($i*2)+1, ($i*2)+2, $key, $colorPtr));
          $colorPtr++;
          if ($colorPtr > count($colors)) $colorPtr = 1;
        }
      }
      
      fclose($fp);
      exec(sprintf('chmod +x %s', $script));
      $cmd = sprintf('cd %s; ./%s > %s 2>/dev/null; echo $?', $dir, basename($script), basename($img));
      $ecode = trim(exec($cmd));
      if ($ecode > 0) {
        print_msg(sprintf('Failed to generate line chart - exit code %d', $ecode), $this->verbose, __FILE__, __LINE__, TRUE);
      }
      else {
        print_msg(sprintf('Generated line chart %s successfully', $img), $this->verbose, __FILE__, __LINE__);
        // attempt to convert to PNG using wkhtmltoimage
        if (self::wkhtmltopdfInstalled()) {
          //$cmd = sprintf('sudo %swkhtmltoimage %s %s >/dev/null 2>&1', isset($this->options['wkhtml_xvfb']) ? 'xvfb-run ' : '', $img, $png = str_replace('.svg', '.png', $img));
          $cmd = sprintf('sudo xvfb-run wkhtmltoimage %s %s >/dev/null 2>&1', $img, $png = str_replace('.svg', '.png', $img));
          $ecode = trim(exec($cmd));
          sleep(1);
          if (!file_exists($png) || !filesize($png)) print_msg(sprintf('Unable to convert SVG image %s to PNG %s (exit code %d)', $img, $png, $ecode), $this->verbose, __FILE__, __LINE__, TRUE);
          else {
            exec(sprintf('rm -f %s', $img));
            print_msg(sprintf('SVG image %s converted to PNG successfully - PNG will be used in report', basename($img)), $this->verbose, __FILE__, __LINE__);
            $img = $png;
          }
        }
        // return full image tag
        if ($html) $chart = sprintf('<img alt="%s" class="plot" src="%s" />', $this->getSubtitle($section), basename($img));
        else $chart = basename($img);
      }
    }
    // error - invalid scripts or unable to open gnuplot files
    else {
      print_msg(sprintf('Failed to generate line chart - either coordinates are invalid or script/data files %s/%s could not be opened', basename($script), basename($dfile)), $this->verbose, __FILE__, __LINE__, TRUE);
      if ($fp) {
        fclose($fp);
        exec('rm -f %s', $script);
      }
    }
    return $chart;
  }
  
    /**
   * generates a point chart based on the parameters provided. return value is 
   * the name of the image which may in turn be used in an image element for 
   * a content section. returns NULL on error
   * @param string $dir the directory where the line chart should be generated
   * @param string $section the section identifier for the line chart
   * @param array $coords either a single array of tuples representing the x/y
   * values, or a hash or tuple arrays indexed by the name of each set of data
   * points. coordinates should have the same 
   * @param string $xlabel optional x label
   * @param string $ylabel optional y label
   * @param string $title optional graph title
   * @param array $settings optional array of custom gnuplot settings. the 
   * following special settings are supported:
   *   height: the graph height
   *   pointColor:     optional point styles
   *   nogrid:    don't add y axis grid lines
   *   nokey:     don't show the plot key/legend
   *   //nolinespoints: don't use linespoints
   *   xFloatPrec: x float precision
   *   xLogscale: use logscale for the x axis
   *   xMin:      min value for the x axis tics - may be a percentage relative to 
   *              the lowest value
   *   xMax:      max value for the x axis tics - may be a percentage relative to 
   *              the highest value
   *   xTics:     the number of x tics to show (default 8)
   *   yFloatPrec: y float precision
   *   yLogscale: use logscale for the y axis
   *   yMin:      min value for the x axis tics - may be a percentage relative to 
   *              the lowest value
   *   yMax:      max value for the y axis tics - may be a percentage relative to 
   *              the highest value
   *   yTics:     the number of y tics to show (default 8)
   *   pointColor: point color (default blue)
   * 
   * xMin, xMax, yMin and yMax all default to the same value as the other for 
   * percentages and 15% otherwise if only 1 is set for a given 
   * axis. If neither are specified, gnuplot will auto assign the tics. If xMin
   * or xMax are specified, but not xTics, xTics defaults to 8
   * @param boolean $html whether or not to return the html <img element or just
   * the name of the file
   * @param boolean $histogram whether or not to render the chart using a 
   * histogram. If TRUE, $coords should represent all of the y values for a 
   * given X. The $coords hash key will be used as the X label and the value(s) 
   * rendered using a clustered histogram (grouped column chart)
   * @return string
   */
  protected final function generatePointChart($dir, $section, $coords, $xlabel=NULL, $ylabel=NULL, $title=NULL, $settings=NULL, $html=TRUE, $histogram=FALSE) {
    print_msg(sprintf('Generating point chart in %s for test %s and section %s with %d coords', $dir, $this->test, $section, count($coords)), $this->verbose, __FILE__, __LINE__);
    
    $chart = NULL;
    $script = sprintf('%s/%s-%s.pg', $dir, $this->test, $section);
    $dfile = sprintf('%s/%s-%s.dat', $dir, $this->test, $section);
    if (is_array($coords) && ($fp = fopen($script, 'w')) && ($df = fopen($dfile, 'w'))) {
      $colors = $this->getGraphColors();
      $xFloatPrec = isset($settings['xFloatPrec']) && is_numeric($settings['xFloatPrec']) ? $settings['xFloatPrec'] : 0;
      $yFloatPrec = isset($settings['yFloatPrec']) && is_numeric($settings['yFloatPrec']) ? $settings['yFloatPrec'] : 0;
      
      // just one array of tuples
      if (isset($coords[0])) $coords[''] = array($coords);
      
      // determine max points/write data file header
      $maxPoints = NULL;
      foreach(array_keys($coords) as $i => $key) {
        if ($maxPoints === NULL || count($coords[$key]) > $maxPoints) $maxPoints = count($coords[$key]);
        if (!$histogram) fwrite($df, sprintf("%s%s%s\t%s%s", $i > 0 ? "\t" : '', $key ? $key . ' ' : '', $xlabel ? $xlabel : 'X', $key ? $key . ' ' : '', $ylabel ? $ylabel : 'Y'));
      }
      if (!$histogram) fwrite($df, "\n");
      
      // determine value ranges and generate data file
      $minX = NULL;
      $maxX = NULL;
      $minY = NULL;
      $maxY = NULL;
      if ($histogram) {
        foreach($coords as $x => $points) {
          if (is_numeric($x) && ($minX === NULL || $x < $minX)) $minX = $x;
          if (is_numeric($x) && $x > $maxX) $maxX = $x;
          fwrite($df, $x);
          for($n=0; $n<$maxPoints; $n++) {
            $y = isset($points[$n]) ? $points[$n]*1 : '';
            if (is_numeric($y) && ($minY === NULL || $y < $minY)) $minY = $y;
            if (is_numeric($y) && $y > $maxY) $maxY = $y;
            fwrite($df, sprintf("\t%s", $y));
          }
          fwrite($df, "\n");
        }
      }
      else {
        for($n=0; $n<$maxPoints; $n++) {
          foreach(array_keys($coords) as $i => $key) {
            $x = isset($coords[$key][$n][0]) ? $coords[$key][$n][0] : '';
            if (is_numeric($x) && ($minX === NULL || $x < $minX)) $minX = $x;
            if (is_numeric($x) && $x > $maxX) $maxX = $x;
            $y = isset($coords[$key][$n][1]) ? $coords[$key][$n][1] : '';
            if (is_numeric($y) && ($minY === NULL || $y < $minY)) $minY = $y;
            if (is_numeric($y) && $y > $maxY) $maxY = $y;
            fwrite($df, sprintf("%s%s\t%s", $i > 0 ? "\t" : '', $x, $y));
          }
          fwrite($df, "\n");
        } 
      }
      fclose($df);
      
      // Coordinates are all 0 - cannot generate graphs
      if (!$maxY) {
        print_msg('Failed to generate line chart - Y coordinates are all 0', $this->verbose, __FILE__, __LINE__, TRUE);
        return NULL;
      }
      
      // determine x tic settings
      $xMin = isset($settings['xMin']) ? $settings['xMin'] : NULL;
      $xMax = isset($settings['xMax']) ? $settings['xMax'] : NULL;
      $xTics = isset($settings['xTics']) ? $settings['xTics'] : NULL;
      if (!isset($xMin) && (isset($xMax) || $xTics)) $xMin = isset($xMax) && preg_match('/%/', $xMax) ? $xMax : '15%';
      if (!isset($xMax) && (isset($xMin) || $xTics)) $xMax = isset($xMin) && preg_match('/%/', $xMin) ? $xMin : '15%';
      if (!isset($xMin)) $xMin = $minX;
      if (!isset($xMax)) $xMax = $maxX;
      if (preg_match('/^([0-9\.]+)%$/', $xMin, $m)) {
        $xMin = floor($minX - ($minX*($m[1]*0.01)));
        if ($xMin < 0) $xMin = 0;
      }
      if (preg_match('/^([0-9\.]+)%$/', $xMax, $m)) $xMax = ceil($maxX + ($maxX*($m[1]*0.01)));
      if (!$xTics) $xTics = 8;
      $xDiff = $xMax - $xMin;
      $xStep = floor($xDiff/$xTics);
      if ($xStep < 1) $xStep = 1;
      
      // determine y tic settings
      $yMin = isset($settings['yMin']) ? $settings['yMin'] : NULL;
      $yMax = isset($settings['yMax']) ? $settings['yMax'] : NULL;
      $yTics = isset($settings['yTics']) ? $settings['yTics'] : NULL;
      if (!isset($yMin) && (isset($yMax) || $yTics)) $yMin = isset($yMax) && preg_match('/%/', $yMax) ? $yMax : '15%';
      if (!isset($yMax) && (isset($yMin) || $yTics)) $yMax = isset($yMin) && preg_match('/%/', $yMin) ? $yMin : '15%';
      if (isset($yMin) && preg_match('/^([0-9\.]+)%$/', $yMin, $m)) {
        $yMin = floor($minY - ($minY*($m[1]*0.01)));
        if ($yMin < 0) $yMin = 0;
      }
      if (isset($yMin)) {
        if (preg_match('/^([0-9\.]+)%$/', $yMax, $m)) $yMax = ceil($maxY + ($maxY*($m[1]*0.01)));
        if (!$yTics) $yTics = 8;
        $yDiff = $yMax - $yMin;
        $yStep = floor($yDiff/$yTics);
        if ($yStep < 1) $yStep = 1;
      }
      
      $img = sprintf('%s/%s-%s.svg', $dir, $this->test, $section);
      print_msg(sprintf('Generating line chart %s with %d data sets and %d points/set. X Label: %s; Y Label: %s; Title: %s', basename($img), count($coords), $maxPoints, $xlabel, $ylabel, $title), $this->verbose, __FILE__, __LINE__);
      
      fwrite($fp, sprintf("#!%s\n", trim(shell_exec('which gnuplot'))));
      fwrite($fp, "reset\n");
      fwrite($fp, sprintf("set terminal svg dashed size 1024,%d fontfile 'font-svg.css' font 'rfont,%d'\n", isset($settings['height']) ? $settings['height'] : 600, $this->options['font_size']+4));
      // custom settings
      if (is_array($settings)) {
        foreach($settings as $key => $setting) {
          // special settings
          if (in_array($key, array('height', 'lines', 'nogrid', 'nokey', 'nolinespoints', 'xLogscale', 'xMin', 'xMax', 'xTics', 'xFloatPrec', 'yFloatPrec', 'yLogscale', 'yMin', 'yMax', 'yTics', 'pointColor'))) continue;
          fwrite($fp, "${setting}\n");
        }
      }
      fwrite($fp, "set autoscale keepfix\n");
      fwrite($fp, "set decimal locale\n");
      fwrite($fp, "set format y \"%'10.${yFloatPrec}f\"\n");
      //fwrite($fp, "set format x \"%'10.${xFloatPrec}f\"\n");
      if ($xlabel) fwrite($fp, sprintf("set xlabel \"%s\"\n", $xlabel));
      if (isset($settings['xLogscale'])) {
        if (!isset($settings['xMin'])) $xMin = self::adjustLogScale($xMin, TRUE);
        if (!isset($settings['xMax'])) $xMax = self::adjustLogScale($xMax);
      }
      if ($xMin != $xMax) fwrite($fp, sprintf("set xrange [%d:%d]\n", $xMin, $xMax));
      if (isset($settings['xLogscale'])) fwrite($fp, "set logscale x\n");
      else if ($xMin != $xMax && !$xFloatPrec) fwrite($fp, sprintf("set xtics %d, %d, %d\n", $xMin, $xStep, $xMax));
      if ($ylabel) fwrite($fp, sprintf("set ylabel \"%s\"\n", $ylabel));
      if (isset($yMin)) {
        if (isset($settings['yLogscale'])) {
          if (!isset($settings['yMin'])) $yMin = self::adjustLogScale($yMin, TRUE);
          if (!isset($settings['yMax'])) $yMax = self::adjustLogScale($yMax);
        }
        if ($yMin != $yMax) fwrite($fp, sprintf("set yrange [%d:%d]\n", $yMin, $yMax));
        if (isset($settings['yLogscale'])) fwrite($fp, "set logscale y\n");
        else if (!$yFloatPrec) fwrite($fp, sprintf("set ytics %d, %d, %d\n", $yMin, $yStep, $yMax));
      }
      if ($title) fwrite($fp, sprintf("set title \"%s\"\n", $title));
      if (!isset($settings['nokey'])) fwrite($fp, "set key outside center top horizontal reverse\n");
      fwrite($fp, "set grid\n");
      fwrite($fp, "set style data points\n");
           
      $color = isset($settings['pointColor']) ? $settings['pointColor'] : 'blue';
      
      fwrite($fp, "set grid noxtics\n");
      if (!isset($settings['nogrid'])) fwrite($fp, "set grid ytics lc rgb '#dddddd' lw 1 lt 0\n");
      else fwrite($fp, "set grid noytics\n");
      fwrite($fp, "set tic scale 0\n");
      fwrite($fp, sprintf("plot \"%s\"", basename($dfile)));

      foreach(array_keys($coords) as $i => $key) {
        fwrite($fp, sprintf("%s every ::1 u %d:%d t \"%s\" with point pointtype 5 ps 2 lc rgb \"%s\"", $i > 0 ? ", \\\n\"\"" : '', ($i*2)+1, ($i*2)+2, $key, $color));
        
      }
      
      fclose($fp);
      exec(sprintf('chmod +x %s', $script));
      $cmd = sprintf('cd %s; ./%s > %s 2>/dev/null; echo $?', $dir, basename($script), basename($img));
      $ecode = trim(exec($cmd));

      if ($ecode > 0) {
        print_msg(sprintf('Failed to generate point chart - exit code %d', $ecode), $this->verbose, __FILE__, __LINE__, TRUE);
      }
      else {
        print_msg(sprintf('Generated point chart %s successfully', $img), $this->verbose, __FILE__, __LINE__);
        // attempt to convert to PNG using wkhtmltoimage
        if (self::wkhtmltopdfInstalled()) {
          //$cmd = sprintf('sudo %swkhtmltoimage %s %s >/dev/null 2>&1', isset($this->options['wkhtml_xvfb']) ? 'xvfb-run ' : '', $img, $png = str_replace('.svg', '.png', $img));
          $cmd = sprintf('sudo xvfb-run wkhtmltoimage %s %s >/dev/null 2>&1', $img, $png = str_replace('.svg', '.png', $img));
          $ecode = trim(exec($cmd));
          sleep(1);
          if (!file_exists($png) || !filesize($png)) print_msg(sprintf('Unable to convert SVG image %s to PNG %s (exit code %d)', $img, $png, $ecode), $this->verbose, __FILE__, __LINE__, TRUE);
          else {
            exec(sprintf('rm -f %s', $img));
            print_msg(sprintf('SVG image %s converted to PNG successfully - PNG will be used in report', basename($img)), $this->verbose, __FILE__, __LINE__);
            $img = $png;
          }
        }
        // return full image tag
        if ($html) $chart = sprintf('<img alt="%s" class="plot" src="%s" />', $this->getSubtitle($section), basename($img));
        else $chart = basename($img);
      }
    }
    // error - invalid scripts or unable to open gnuplot files
    else {
      print_msg(sprintf('Failed to generate line chart - either coordinates are invalid or script/data files %s/%s could not be opened', basename($script), basename($dfile)), $this->verbose, __FILE__, __LINE__, TRUE);
      if ($fp) {
        fclose($fp);
        exec('rm -f %s', $script);
      }
    }
    return $chart;
  }

  /**
   * generates and testing reports. Returns TRUE on success, FALSE otherwise
   * @param BlockStorageTest[] $controllers the controllers to generate the 
   * reports for
   * @param string $dir optional directory where reports should be generated 
   * in. If not specified, --output will be used
   * @return boolean
   */
  public static function generateReports(&$controllers, $dir=NULL) {
    $keys = array_keys($controllers);
    $options = isset($controllers[$keys[0]]) ? $controllers[$keys[0]]->options : NULL;
    if (!$options || isset($options['noreport']) && $options['noreport']) return FALSE;
    $verbose = isset($options['verbose']) && $options['verbose'];
    
    $generated = FALSE;
    $pageNum = 0;
    if (!$dir) $dir = $options['output'];
    if (is_dir($dir) && is_writable($dir) && mkdir($tdir = sprintf('%s/%d', $dir, rand())) && ($fp = fopen($htmlFile = sprintf('%s/index.html', $tdir), 'w'))) {
      $reportsDir = dirname(dirname(__FILE__)) . '/reports';
      
      // add header
      $tests = array();
      foreach(array_keys($controllers) as $n) $tests[] = $controllers[$n]->test;
      $title = implode(', ', $tests) . ' Block Storage Performance Report';
      ob_start();
      include(sprintf('%s/_header.html', $reportsDir));
      fwrite($fp, ob_get_contents());
      ob_end_clean();
      
      // custom report controllers
      foreach(array_keys($controllers) as $n) {
        if (count($controllers[$n]->subtests)) {
          print_msg(sprintf('Replacing %s test object with %d subtests', $controllers[$n]->test, count($controllers[$n]->subtests)), $verbose, __FILE__, __LINE__);
          foreach(array_keys($controllers[$n]->subtests) as $i) {
            $controllers[] = $controllers[$n]->subtests[$i];
          }
        }
        else{
          $controllers[] = $controllers[$n];
        }
        unset($controllers[$n]);
      }

      print_msg(sprintf('Initiating report creation using temporary directory %s', $tdir), $verbose, __FILE__, __LINE__);
      // copy font files
      exec(sprintf('cp %s/font-svg.css %s/', $reportsDir, $tdir));
      exec(sprintf('cp %s/font.css %s/', $reportsDir, $tdir));
      exec(sprintf('cp %s/font.ttf %s/', $reportsDir, $tdir));
      // copy js files
      $jsDir = dirname(dirname(__FILE__)) . '/js';
      exec(sprintf('cp %s/*.js %s/', $jsDir, $tdir));

      $lastTest = NULL;
      foreach(array_keys($controllers) as $n) {
        if ($lastTest != $controllers[$n]->test) {
          $lastTest = $controllers[$n]->test;
          $testPageNum = 0;
        }
        if (count($controllers[$n]->fio) && $controllers[$n]->wdpcComplete && isset($controllers[$n]->fio['wdpc'])) {
          print_msg(sprintf('Generating %s reports in directory %s', $controllers[$n]->test, $dir), $verbose, __FILE__, __LINE__);
          $wipcJobs = isset($controllers[$n]->fio['wipc']) ? $controllers[$n]->fio['wipc'] : array();
          $wdpcJobs = $controllers[$n]->fio['wdpc'];
          $ssJobs = $controllers[$n]->getSteadyStateJobs();
          
          if (count($wdpcJobs)) {
            print_msg(sprintf('Generating %s reports for %d wipc jobs, %d wdpc jobs and %d ss jobs', $controllers[$n]->test, count($wipcJobs), count($wdpcJobs), count($ssJobs)), $verbose, __FILE__, __LINE__);
            
            // use array to represent report header table (10 rows)
            $params = array(
              'platform' => $controllers[$n]->getPlatformParameters(),
              'device' => $controllers[$n]->getDeviceParameters(),
              'setup' => array_merge(array('Data Pattern' => isset($options['norandom']) && $options['norandom'] ? 'PLAIN' : 'RND', 'AR' => $options['active_range'] . '%'), $controllers[$n]->getSetupParameters()),
              'test' => array_merge(array('Data Pattern' => isset($options['norandom']) && $options['norandom'] ? 'PLAIN' : 'RND', 'AR &amp; Amount' => $options['active_range'] . '%'), $controllers[$n]->getTestParameters())
            );
            $headers = array();
            for ($i=0; $i<100; $i++) {
              $empty = TRUE;
              $cols = array();
              foreach($params as $type => $vals) {
                if (count($vals) >= ($i + 1)) {
                  $empty = FALSE;
                  $keys = array_keys($vals);
                  $cols[] = array('class' => $type, 'label' => $keys[$i], 'value' => $vals[$keys[$i]]);
                }
                else $cols[] = array('class' => $type, 'label' => '', 'value' => '');
              }
              if (!$empty) $headers[] = $cols;
              else break;
            }
            
            $sections = $controllers[$n]->getReportSections();
            print_msg(sprintf('Rendering report sections: %s', implode(', ', array_keys($sections))), $verbose, __FILE__, __LINE__);
            foreach($sections as $section => $label) {
              $test = $controllers[$n]->test;
              if ($content = $controllers[$n]->getReportContent($section, $ssJobs, $tdir)) {
                print_msg(sprintf('Successfully generated %s content (%s) for %s report', $section, $label, $controllers[$n]->test), $verbose, __FILE__, __LINE__);
                $pageNum++;
                $testPageNum++;
                // add page
                ob_start();
                include(sprintf('%s/_page.html', $reportsDir));
                fwrite($fp, ob_get_contents());
                ob_end_clean();
              }
              else if ($content === FALSE) print_msg(sprintf('Skipping %s content for %s report', $section, $controllers[$n]->test), $verbose, __FILE__, __LINE__);
              else print_msg(sprintf('Unable to get %s content for %s report', $section, $controllers[$n]->test), $verbose, __FILE__, __LINE__, TRUE);
            }
          }
        }
        else print_msg(sprintf('Unable to generate %s reports. fio steps: %d; wdpcComplete=%d; wdpcIntervals=%d; wdpc=%d', $controllers[$n]->test, count($controllers[$n]->fio), $controllers[$n]->wdpcComplete, $controllers[$n]->wdpcIntervals, isset($controllers[$n]->fio['wdpc'])), $verbose, __FILE__, __LINE__, TRUE);
      }
      
      // add footer
      ob_start();
      include(sprintf('%s/_footer.html', $reportsDir));
      fwrite($fp, ob_get_contents());
      ob_end_clean();
      
      fclose($fp);
      
      // zip up html report
      if (filesize($htmlFile)) {
        $zip = sprintf('%s/report.zip', $tdir);
        exec(sprintf('cd %s; zip %s *; mv %s %s', $tdir, basename($zip), basename($zip), $dir));
        if (!isset($options['nopdfreport']) || !$options['nopdfreport']) {
          // generate PDF report
          $cmd = sprintf('cd %s; sudo xvfb-run wkhtmltopdf -s Letter --footer-left [date] --footer-right [page] --footer-font-name rfont --footer-font-size %d index.html report.pdf >/dev/null 2>&1; echo $?', $tdir, $options['font_size']);
          $ecode = trim(exec($cmd));
          sleep(1);
          if ($ecode > 0) print_msg(sprintf('Failed to create PDF report'), $verbose, __FILE__, __LINE__, TRUE);
          else {
            print_msg(sprintf('Successfully created PDF report'), $verbose, __FILE__, __LINE__);
            exec(sprintf('mv %s/report.pdf %s', $tdir, $dir));
          }
        }
      }
      
      // remove temporary directory
      if (is_dir($tdir) && strpos($tdir, $dir) === 0 && preg_match('/[0-9]$/', $tdir)) {
        exec(sprintf('rm -rf %s', $tdir));
        print_msg(sprintf('Removed temporary directory %s', $tdir), $verbose, __FILE__, __LINE__);
      }
    }
    else print_msg(sprintf('Unable to generate reports in directory %s - it either does not exist or is not writable', $dir), $verbose, __FILE__, __LINE__, TRUE);
    
    
    print_msg(sprintf('Reports generation complete - status %d', $generated), $verbose, __FILE__, __LINE__);
    
    return $generated;
  }
  
  /**
   * returns the number of CPUs/cores present
   * @return int
   */
  public static function getCpuCount() {
    return trim(shell_exec('nproc'))*1;
  }
  
  /**
   * returns the device path for $target
   * @param string $target the path to check
   * @param boolean $removeNumericSuffix whether or not to remove the device 
   * numeric suffix (if present)
   * @return string
   */
  public static function getDevice($target, $removeNumericSuffix=FALSE) {
    $device = NULL;
    
    if ($target) {
      if (preg_match('/^\/dev\//', $target)) $device = $target;
      else {
        $df = self::df($target);
        $device = $df && isset($df['source']) ? $df['source'] : NULL;
      }
    }
    if ($device && $removeNumericSuffix && preg_match('/[a-z]([0-9]+)$/', $device, $m)) $device = substr($device, 0, strlen($m[1])*-1);
    
    return $device;
  }
  
  /**
   * returns the platform parameters for this test. These are displayed in the 
   * Storage Platform columns
   * @return array
   */
  private function getDeviceParameters() {
    $t = $this->volumeTargets ? 'Volume' : 'Device';
    $capacities = '';
    $purge = '';
    $volInfo = isset($this->options['meta_storage_vol_info']) ? $this->options['meta_storage_vol_info'] : '';
    $attrs = array('capacity' => array(), 'purge' => array(), 'vol' => array());
    foreach($this->options['target'] as $target) {
      $capacity = self::getFreeSpace($target);
      $capacity = sprintf('%s %siB', $capacity >= 1024 ? round($capacity/1024, 2) : $capacity, $capacity >= 1024 ? 'G' : 'M');
      $attrs['capacity'][$capacity] = TRUE;
      $capacities .= sprintf('%s%s', $capacities ? ', ' : '', $capacity);     
      //$pmethod = isset($this->purgeMethods[$target]) ? self::getPurgeMethodDesc($this->purgeMethods[$target]) : 'None';
      $pmethod = isset($this->options['purge']) ? self::getPurgeMethodDesc($this->options['purge']) : 'None';
      print_msg(sprintf('purgeMethods: %s  pmethod: %s', $this->purgeMethods[$target], $pmethod), $this->verbose, __FILE__, __LINE__);
      $attrs['purge'][$pmethod] = TRUE;
      $purge .= ($purge ? ', ' : '') . $pmethod;
      if ($this->volumeTargets && !isset($this->options['meta_storage_vol_info'])) {
        $vinfo = $this->getFsType($target);
        $attrs['vol'][$vinfo] = TRUE;
        $volInfo .= ($volInfo ? ', ' : '') . $vinfo;
      }
    }
    // show only a single value if they are all the same
    if (count($attrs['capacity']) == 1) {
      $keys = array_keys($attrs['capacity']);
      $capacities = $keys[0];
    }
    if (count($attrs['purge']) == 1) {
      $keys = array_keys($attrs['purge']);
      $purge = $keys[0];
    }
    if (count($attrs['vol']) == 1) {
      $keys = array_keys($attrs['vol']);
      $volInfo = $keys[0];
    }
    $targetDesc = isset($this->options['target_base']) ? $this->options['target_base'] : $this->options['target'];
    if (is_array($targetDesc)) $targetDesc = implode(', ', count($targetDesc) > 8 ? array_merge(array_slice($targetDesc, 0, 4), array('...'), array_slice($targetDesc, -4)) : $targetDesc);
    $params = array(
      'Mfgr' => $this->options['meta_mfgr'],
      'Model No.' => $this->options['modelno'],
      'S/N' => $this->options['sn'],
      'Firmware ver' => $this->options['fwv'],
      'Capacity' => $capacities,
      'Interface' => $this->options['interface'],
      'NAND Type' => $this->options['nandtype'],
      'PCIe NVM' => '',
      'Purge Method' => $purge,
      'Write Cache' => 'WCD',
    );
    if ($this->deviceTargets) unset($params['Volume Info']);
    return $params;
  }
  
  /**
   * returns a hash of key/value pairs for the fio $job specified
   * @param array $job the job (or job sub-element in recursive calls)
   * @param string $prefix optional prefix
   * @return array
   */
  public static function getFioJobRow(&$job, $prefix=NULL) {
    $row = array();
    if (is_array($job)) {
      foreach($job as $key => $val) {
        // skip some fio metrics
        if (in_array($key, array('ctx', 'groupid', 'latency_depth', 'latency_target', 'latency_percentile', 'latency_window')) || preg_match('/trim/', $key) || preg_match('/error/', $key)) continue;

        $key = str_replace('.', '_', str_replace('0000', '', str_replace('00000', '', str_replace('.000000', '', str_replace('<', 'lt', str_replace('<=', 'lte', str_replace('>', 'gt', str_replace('>=', 'gte', $key))))))));
        if (preg_match('/0_00/', $key)) continue;
        
        if (is_array($val)) $row = array_merge($row, self::getFioJobRow($val, str_replace('__', '_', sprintf('%s%s_', $prefix ? $prefix . '_' : '', $key))));
        else $row[sprintf('%s%s', $prefix ? $prefix : '', $key)] = $val;
      }
    }
    return $row;
  }
  
  /**
   * returns the amount of free space available on $target in megabytes
   * @param string $target the directory, volume or device to return free space
   * for
   * @param boolean $bytes if TRUE, return value will be in bytes
   * @param boolean $verbose whether or not to print debug messages
   * @return int
   */
  public static function getFreeSpace($target, $bytes=FALSE, $verbose=FALSE) {
    $device = self::getDevice($target);
    
    if ($device == $target) {
      $pieces = explode("\n", trim(shell_exec($cmd = sprintf('lsblk -n -o size -b %s', $target))));
      if (isset($pieces[0]) && is_numeric($pieces[0])) {
        $freeSpace = $pieces[0]*1;
        if (!$bytes) $freeSpace /= 1048576;
      }
    }
    else {
      $df = self::df($target, array('B' => 'M'));
      if (is_numeric($freeSpace = $df && isset($df['avail']) ? substr($df['avail'], 0, -1)*1 : NULL)) {
        if (file_exists($file = sprintf('%s/%s', $target, self::BLOCK_STORAGE_TEST_FILE_NAME))) $freeSpace += round((filesize($file)/1024)/1024);
        if ($bytes) $freeSpace *= 1048576; 
      }
    }
    
    if ($freeSpace) print_msg(sprintf('Target %s has %s MB free space', $target, $bytes ? round($freeSpace/1048576) : $freeSpace), $verbose, __FILE__, __LINE__);
    else {
      $freeSpace = NULL;
      print_msg(sprintf('Unable to get free space for target %s', $target), $verbose, __FILE__, __LINE__, TRUE);
    }
    
    return $freeSpace;
  }
  
  /**
   * returns the file system type for $target
   * @param string $target the volume or device to return the file system type
   * for
   * @return string
   */
  public static function getFsType($target) {
    $fstype = NULL;
    if ($target) {
      $df = self::df($target);
      $fstype = $df && isset($df['fstype']) ? $df['fstype'] : NULL;
    }
    return $fstype;
  }
  
  /**
   * returns an array containing the hex color codes to use for graphs (as 
   * defined in graph-colors.txt)
   * @return array
   */
  protected final function getGraphColors() {
    if (!count($this->graphColors)) {
      foreach(file(dirname(__FILE__) . '/graph-colors.txt') as $line) {
        if (substr($line, 0, 1) != '#' && preg_match('/([a-zA-Z0-9]{6})/', $line, $m)) $this->graphColors[] = '#' . $m[1];
      }
    }
    return $this->graphColors;
  }
  
  /**
   * returns additional columns that may be included in test results
   * @param string $dir the directory where results exist
   * @return array
   */
  public static function getMetaCols($dir) {
    if ($cols = self::getSerializedOptions($dir)) {
      if (isset($cols['target'])) {
        $sizes = array();
        foreach($cols['target'] as $target) {
          $sizes[$target] = file_exists($file = sprintf('%s/%s', $target, self::BLOCK_STORAGE_TEST_FILE_NAME)) ? round(((filesize($file)/1024)/1024)/1024) : round(self::getFreeSpace($target)/1024);
        }
        $cols['target_count'] = count($cols['target']);
        $cols['target_size_gb'] = round(array_sum($sizes)/count($sizes));
        if ($cols['target_size_gb'] < 1) $cols['target_size_gb'] = 1;
        $cols['target_sizes'] = $sizes;
      }
      // storage range targets (e.g. /xvd[a-e])
      if (isset($cols['target_base'])) {
        $cols['target'] = $cols['target_base'];
        unset($cols['target_base']);
      }
      foreach(array_keys($cols) as $i) if (is_array($cols[$i])) $cols[$i] = implode(',', $cols[$i]);
    }
    return $cols;
  }
  
  /**
   * returns the platform parameters for this test. These are displayed in the 
   * Test Platform columns
   * @return array
   */
  private function getPlatformParameters() {
    return array(
      'Ref Test Platform' => isset($this->options['meta_ref_test_platform']) ? $this->options['meta_ref_test_platform'] : '',
      'Motherboard' => isset($this->options['meta_motherboard']) ? $this->options['meta_motherboard'] : '',
      'CPU' => isset($this->options['meta_cpu']) ? $this->options['meta_cpu'] : '',
      'Memory' => isset($this->options['meta_memory']) ? $this->options['meta_memory'] : '',
      'Operating System' => isset($this->options['meta_os']) ? $this->options['meta_os'] : '',
      'Test SW' => isset($this->options['meta_test_sw']) ? $this->options['meta_test_sw'] : '',
      'Test SW ver' => isset($this->options['meta_test_sw_info']) ? $this->options['meta_test_sw_info'] : '',
      'Test SN.' => isset($this->options['meta_test_id']) ? $this->options['meta_test_id'] : '',
      'HBA' => 'N/A',
      'PCIe' => '3.0 x16'
    );
  }
  
  /**
   * returns the description to use for a purge method (used in reports)
   * @param string $method purge method to return the description for
   * @return string
   */
  public static function getPurgeMethodDesc($method) {
    $desc = NULL;
    switch($method) {
      case 'secureerase':
        $desc = 'ATA Secure Erase';
        break;
      case 'trim':
        $desc = 'TRIM';
        break;
      case 'zero':
        $desc = 'Zero';
        break;
    }
    print_msg(sprintf('getPurgeMethodDesc %s',$desc ), $verbose, __FILE__, __LINE__);
    return $desc;
  }
  
  /**
   * this sub-class method should return the content associated with $section 
   * using the $jobs given (or all jobs in $this->fio['wdpc']). Return value 
   * should be HTML that can be imbedded into the report. The HTML may include 
   * an image reference without any directory path (e.g. <img src="iops.svg>")
   * returns NULL on error, FALSE if not content required
   * @param string $section the section identifier provided by 
   * $this->getReportSections()
   * @param array $jobs all fio job results occuring within the steady state 
   * measurement window. This is a hash indexed by job name
   * @param string $dir the directory where any images should be generated in
   * @return string
   */
  protected abstract function getReportContent($section, $jobs, $dir);
  
  /**
   * this sub-class method should return a hash identifiying the sections 
   * associated with the test report. The key in the hash should be the 
   * section identifier, and the value the section title
   * @return array
   */
  protected abstract function getReportSections();
  
  /**
   * returns run options represents as a hash
   * @return array
   */
  public static function getRunOptions() {
    // default run argument values
    $sysInfo = get_sys_info();
    $ini = get_benchmark_ini(); 
    $defaults = array(
      'active_range' => 100,
      'collectd_rrd_dir' => '/var/lib/collectd/rrd',
      'fio' => 'fio',
      'fio_options' => array(
        'direct' => TRUE,
        'ioengine' => 'libaio',
        'refill_buffers' => FALSE,
        'scramble_buffers' => TRUE
      ),
      'font_size' => 9,
      'highcharts_js_url' => 'highcharts.js',
      'highcharts3d_js_url' => 'highcharts-3d.js',
      'jquery_url' => 'jquery-2.1.0.min.js', 
      'meta_vender' => 'ADATA',
      'meta_motherboard' => $sysInfo['motherboard'],
      'meta_cpu' => $sysInfo['cpu'],
      'meta_instance_id' => 'Not Specified',
      'meta_memory' => $sysInfo['memory_gb'] > 0 ? $sysInfo['memory_gb'] . ' GB' : $sysInfo['memory_mb'] . ' MB',
      'meta_os' => $sysInfo['os_info'],
      'meta_ref_test_platform' => 'Block Storage',
      'meta_mfgr' => $sysInfo['mfgr'],
      'meta_test_sw' => 'fio',
      'meta_test_sw_info' => isset($sysInfo['fio_ver'])? $sysInfo['fio_ver']:'',
      'meta_test_id' => isset($sysInfo['test_sn'])? $sysInfo['test_sn']:'',
      'oio_per_thread' => 64,
      'output' => trim(shell_exec('pwd')),
      'precondition_passes' => 2,
      'ss_max_rounds' => 25,
      'ss_verification' => 10,
      'test' => array('iops'),
      'threads' => '{cpus}',
      'threads_per_core_max' => 2,
      'threads_per_target_max' => 8,
      'timeout' => 86400,
      'wd_test_duration' => 60,
      'modelno' => $sysInfo['modelno'],
      'sn' => $sysInfo['sn'],
      'fwv' => $sysInfo['fwv'],
      'interface' => $sysInfo['interface'],
      'nandtype' => $sysInfo['nandtype'],
    );
    $opts = array(
      'active_range:',
      'collectd_rrd',
      'collectd_rrd_dir:',
      'fio:',
      'font_size:',
      'highcharts_js_url:',
      'highcharts3d_js_url:',
      'jquery_url:',
      'meta_burst',
      'meta_vender:',
      'meta_ssd_model:',      
      'meta_motherboard:',
      'meta_compute_service_id:',
      'meta_cpu:',
      'meta_drive_interface:',
      'meta_drive_model:',
      'meta_drive_type:',
      'meta_encryption',
      'meta_host_cache:',
      'meta_instance_id:',
      'meta_memory:',
      'meta_os:',
      'meta_notes_storage:',
      'meta_notes_test:',
      'meta_piops:',
      'meta_ref_test_platform:',
      'meta_provider_id:',
      'meta_region:',
      'meta_resource_id:',
      'meta_run_id:',
      'meta_mfgr:',
      'meta_storage_vol_info:',
      'meta_test_id:',
      'meta_test_sw:',
      'meta_test_sw_info:',
      'no3dcharts',
      'nojson',
      'nopdfreport',
      'noprecondition',
      'noprecondition_rotational',
      'nopurge',
      'nopurge_ignore',
      'norandom',
      'noreport',
      'nosecureerase',
      'notrim',
      'nozerofill',
      'nozerofill_non_rotational',
      'oio_per_thread:',
      'output:',
      'precondition_once',
      'precondition_passes:',
      'precondition_time:',
      'purge_once',
      'randommap',
      'savefio',      
      'secureerase_pswd:',
      'sequential_only',
      'skip_blocksize:',
      'skip_workload:',
      'ss_max_rounds:',
      'ss_verification:',
      'target:',
      'target_skip_not_present',
      'test:',
      'threads:',
      'threads_per_core_max:',
      'threads_per_target_max:',
      'throughput_st',
      'throughput_st_rotational',
      'timeout:',
      'trim_offset_end:',
      'v' => 'verbose',
      'wd_test_duration:',
      'wd_sleep_between:',
      'wd_sleep_between_size:',
      'wkhtml_xvfb',
      'dirth_rw:',
      'dirth_bs:'
    );
    $options = parse_args($opts, array('skip_blocksize', 'skip_workload', 'target', 'test'));
    $verbose = isset($options['verbose']) && $options['verbose'];
    
    // explicit fio command
    foreach($defaults as $key => $val) {
      if (!isset($options[$key])) $options[$key] = $val;
    }
    // target/test argument (expand comma separated values)
    foreach(array('target', 'test') as $key) {
      if (isset($options[$key])) {
        if ($key == 'target') $options['target_base'] = $options[$key];
        $targets = array();
        foreach($options[$key] as $temp) {
          foreach(explode(',', $temp) as $target) {
            // target ranges (e.g. /dev/xvdb[a-b])
            if ($key == 'target' && (preg_match('/\[\s*([a-zA-Z])\s*\-\s*([a-zA-Z])\s*\]/', trim($target), $m) || preg_match('/\[\s*([0-9]+)\s*\-\s*([0-9]+)\s*\]/', trim($target), $m))) {
              for($i=$m[1]; $i<=$m[2]; $i++) {
                if (strlen($i) > 1) break;
                $targets[] = str_replace($m[0], $i, trim($target));
              }
            }
            else $targets[] = trim($target);
          }
        }
        $options[$key] = $targets;
      }
    }
    foreach(get_prefixed_params('fio_') as $key => $val) $options['fio_options'][$key] = $val;
    
    // apply throughput_st for rotational targets only
    if (isset($options['throughput_st']) && isset($options['throughput_st_rotational']) && isset($options['target']) && is_array($options['target']) && count($options['target'])) {
      $rotational = TRUE;
      foreach($options['target'] as $target) {
        if (!self::isRotational($target)) {
          $rotational = FALSE;
          break;
        }
      }
      if (!$rotational) {
        print_msg('Removed --throughput_st option because targets are not rotational', $verbose, __FILE__, __LINE__);
        unset($options['throughput_st']);
      }
      else print_msg('Kept --throughput_st option because targets are rotational', $verbose, __FILE__, __LINE__);
    }
    
    // don't use random IO
    if (isset($options['norandom']) && $options['norandom']) {
      if (isset($options['fio_options']['refill_buffers'])) unset($options['fio_options']['refill_buffers']);
      if (isset($options['fio_options']['scramble_buffers'])) unset($options['fio_options']['scramble_buffers']);
    }
    // implicit nosecureerase
    if (!isset($options['secureerase_pswd'])) $options['nosecureerase'] = TRUE;
    // implicit nopurge
    if (isset($options['nosecureerase']) && $options['nosecureerase'] && isset($options['notrim']) && $options['notrim'] && isset($options['nozerofill']) && $options['nozerofill']) $options['nopurge'] = TRUE;
    // threads is based on number of CPUs
    if (isset($options['threads']) && preg_match('/{cpus}/', $options['threads'])) {
      $options['threads'] = str_replace(' ', '', str_replace('{cpus}', self::getCpuCount(), $options['threads']));
      // expression
      if (preg_match('/[\*\+\-\/]/', $options['threads'])) {
        eval(sprintf('$options["threads"]=%s;', $options['threads']));
      }
      $options['threads'] *= 1;
      if ($options['threads'] <= 0) $options['threads'] = 1;
    }
    
    // remove targets that are not present
    if (isset($options['target_skip_not_present']) && isset($options['target']) && count($options['target']) > 1) {
      print_msg(sprintf('Checking targets %s because --target_skip_not_present argument was set', implode(', ', $options['target'])), $verbose, __FILE__, __LINE__);
      $targets = array();
      foreach($options['target'] as $i => $target) {
        if (!is_dir($target) && !file_exists($target)) print_msg(sprintf('Skipped test target %s because it does not exist and the --target_skip_not_present argument was set', $target), $verbose, __FILE__, __LINE__);
        else $targets[] = $target;
      }
      $options['target'] = $targets;
      print_msg(sprintf('Adjusted test targets is %s', implode(', ', $options['target'])), $verbose, __FILE__, __LINE__);
    }
    
    // adjust threads for number of targets
    if (isset($options['target']) && count($options['target']) > 1) {
      $options['threads'] = round($options['threads']/count($options['target']));
      if ($options['threads'] == 0) $options['threads'] = 1;
    }
    
    // adjust for threads_per_target_max
    if (isset($options['threads_per_target_max']) && $options['threads'] > $options['threads_per_target_max']) {
      $threads = $options['threads'];
      $options['threads'] = $options['threads_per_target_max'];
      print_msg(sprintf('Reduced threads from %d to %d for threads_per_target_max constraint %d', $threads, $options['threads'], $options['threads_per_target_max']), $verbose, __FILE__, __LINE__);
    }
    
    $options['threads_total'] = isset($options['target']) ? $options['threads']*count($options['target']) : NULL;
    
    // adjust for threads_per_core_max
    if (isset($options['threads_per_core_max']) && $options['threads_total'] > ($options['threads_per_core_max']*self::getCpuCount())) {
      $threads_total = $options['threads_total'];
      $threads = $options['threads'];
      $options['threads'] = round(($options['threads_per_core_max']*self::getCpuCount())/count($options['target']));
      if (!$options['threads']) $options['threads'] = 1;
      $options['threads_total'] = round($options['threads']*count($options['target']));
      if ($threads != $options['threads']) print_msg(sprintf('Reduced total threads from %d to %d [threads per target from %d to %s] for threads_per_core_max constraint %d, %d CPU cores, and %d targets', $threads_total, $options['threads_total'], $threads, $options['threads'], $options['threads_per_core_max'], self::getCpuCount(), count($options['target'])), $verbose, __FILE__, __LINE__);
      else print_msg(sprintf('Ignoring threads_per_core_max constraint %d because at least 1 thread per target is required', $options['threads_per_core_max']), $verbose, __FILE__, __LINE__);
    }
    
    // validate meta_host_cache value is read or rw
    if (isset($options['meta_host_cache'])) $options['meta_host_cache'] = trim(strtolower($options['meta_host_cache']));
    if (isset($options['meta_host_cache']) && !in_array($options['meta_host_cache'], array('read', 'rw'))) {
      print_msg(sprintf('Ignoring meta_host_cache %s (allowed values are read or rw)', $options['meta_host_cache']), $verbose, __FILE__, __LINE__);
      unset($options['meta_host_cache']);
    }
    
    return $options;
  }
  
  /**
   * returns options from the serialized file where they are written when a 
   * test completes
   * @param string $dir the directory where results were written to
   * @return array
   */
  public static function getSerializedOptions($dir) {
    return unserialize(file_get_contents(sprintf('%s/%s', $dir, self::BLOCK_STORAGE_TEST_OPTIONS_FILE_NAME)));
  }
  
  /**
   * this sub-class method should return a hash of setup parameters - these are
   * label/value pairs displayed in the bottom 8 rows of the Set Up Parameters 
   * columns in the report page headers
   * @return array
   */
  protected abstract function getSetupParameters();
  
  /**
   * returns steady state jobs for this test
   * @return array
   */
  protected function getSteadyStateJobs() {
    $ssStart = isset($this->fio['wdpc']) ? ($this->wdpcComplete - 5)*$this->wdpcIntervals : NULL;
    $ssJobs = array();
    
    foreach($this->fio['wdpc'] as $i => $job) {
      if (isset($job['jobs'][0]['jobname']) && $i >= $ssStart) $ssJobs[$job['jobs'][0]['jobname']] = $job['jobs'][0];
    }
    
    return $ssJobs;
  }
  
  /**
   * this sub-class method should return the subtitle for a given test and 
   * section
   * @param string $section the section identifier to return the subtitle for
   * @return string
   */
  protected abstract function getSubtitle($section);
  
  /**
   * returns an array containing the currently supported tests
   * @return array
   */
  public static function getSupportedTests() {
    return array('iops', 'throughput', 'latency', 'wsat', 'hir', 'xsr', 'ecw', 'dirth');
  }
  
  /**
   * returns a new instance of a BlockStorageTest object for the $test and 
   * $options specified
   * @param string $test the type of block storage test
   * @param array $options the test options
   * @return BlockStorageTest
   */
  public static function &getTestController($test, $options) {
    $controller = NULL;
    if ($test && file_exists($file = dirname(__FILE__) . '/BlockStorageTest' . strtoupper(substr($test, 0, 1)) . substr($test, 1) . '.php')) {
      require_once($file);
      $className = str_replace('.php', '', basename($file));    
      if (class_exists($className)) {
        $options['test_started'] = date('Y-m-d H:i:s');
        $controller = new $className($options);
        $controller->test = $test;
        $controller->options = $options;
        // determine target types (device or volume)
        foreach($options['target'] as $target) {
          $device = self::getDevice($target);
          $device == $target ? $controller->deviceTargets = TRUE : $controller->volumeTargets = TRUE;
        }
        $controller->verbose = isset($options['verbose']) && $options['verbose'];
      }
    }
    return $controller;
  }
  
  /**
   * this sub-class method should return a hash of test parameters - these are
   * label/value pairs displayed in the bottom 8 rows of the Test Parameters 
   * columns in the report page headers
   * @return array
   */
  protected abstract function getTestParameters();
  
  /**
   * returns the base volume path for $target. if $target is a device 
   * reference, the same reference will be returned
   * @param string $target the path to check
   * @return string
   */
  public static function getVolume($target) {
    $volume = NULL;
    if ($target) {
      if (preg_match('/^\/dev\//', $target)) $volume = $target;
      else {
        $df = self::df($target);
        $volume = $df && isset($df['target']) ? $df['target'] : NULL;
      }
    }
    return $volume;
  }
  
  /**
   * returns TRUE if $target references a rotational device, FALSE if it is not 
   * rotational and NULL if the device type cannot be queried
   * @param string $target the device or path to check
   * @return boolean
   */
  public static function isRotational($target) {
    $rotational = NULL;
    
    foreach(array(TRUE, FALSE) as $removeNumericSuffix) {
      if (($device = self::getDevice($target, $removeNumericSuffix)) && 
          file_exists($file = sprintf('/sys/block/%s/queue/rotational', basename($device)))) {
        $rotational = trim(file_get_contents($file)) == '1';
        break;
      }
    }
    if ($rotational === NULL) print_msg(sprintf('Unable to check if %s is rotational because file %s does not exist', isset($device) ? $device : $target, isset($file) ? $file : 'NA'), TRUE, __FILE__, __LINE__);
    
    return $rotational;
  }
  
  /**
   * returns TRUE if the $metrics provided constitute steady state. The 
   * --ss_verification parameter defines thresholds for steady state. Per the 
   * SNIA test specification, the default value is 10%
   * @param array $metrics the metrics to use to check for steady state (x/y 
   * coords)
   * @return boolean
   */
  protected function isSteadyState($metrics) {
    $steadyState = FALSE;
    if (is_array($metrics) && count($metrics) == 5) {
      $n = 5;
      $sum_x = array_sum(array_keys($metrics));
      $sum_y = array_sum($metrics);
      $mean_x = round($sum_x/$n, 3);
      $mean_y = round($sum_y/$n, 3);
      $sum_xy = 0;
      $sum_x_sq = 0;
      foreach($metrics as $x => $y) {
        $sum_xy += $x*$y;
        $sum_x_sq += $x*$x;
      }
      $slope = round((($n * $sum_xy) - ($sum_x * $sum_y))/(($n * $sum_x_sq) - ($sum_x * $sum_x)), 3);
      $yIntercept = round((($sum_y * $sum_x_sq) - ($sum_x * $sum_xy))/(($n * $sum_x_sq) - ($sum_x * $sum_x)), 3);
      print_msg(sprintf('Calculated slope=%s; y intercept=%s; using n=%s; sum_y=%s; sum_x_sq=%s; sum_x=%s; sum_xy=%s; least squares formula: %s', $slope, $yIntercept, $n, $sum_y, $sum_x_sq, $sum_x, $sum_xy, sprintf('%s * R + %s', $slope, $yIntercept)), $this->verbose, __FILE__, __LINE__);
      
      $ratio = $this->options['ss_verification']*0.01;
      $maxSlopeExcursion = $mean_y * $ratio;
      $maxDataExcursion = $maxSlopeExcursion * 2;
      $first = NULL;
      $last = NULL;
      $min = NULL;
      $max = NULL;
      $largestDataExcursion = NULL;
      $largestSlopeExcursion = round(abs($slope*4), 1);
      $squares = array();
      foreach($metrics as $i => $metric) {
        if ($min === NULL || $metric < $min) $min = $metric;
        if ($max === NULL || $metric > $max) $max = $metric;
        if ($first === NULL) $first = $metric;
        $last = $metric;
      }
      $largestDataExcursion = $max - $min;
      
      if ($steadyState = $largestDataExcursion <= $maxDataExcursion && $largestSlopeExcursion <= $maxSlopeExcursion) {
        $this->ssData['metrics'] = $metrics;
        $this->ssData['average'] = $mean_y;
        $this->ssData['maxDataExcursion'] = $maxDataExcursion;
        $this->ssData['maxSlopeExcursion'] = $maxSlopeExcursion;
        $this->ssData['largestDataExcursion'] = $largestDataExcursion;
        $this->ssData['largestSlopeExcursion'] = $largestSlopeExcursion;
        $this->ssData['slope'] = $slope;
        $this->ssData['yIntercept'] = $yIntercept;
        $this->ssData['linearFitFormula'] = sprintf('%s * R + %s', $slope, $yIntercept);
      }
      print_msg(sprintf('Steady state check complete: ratio=%s; average=%s; allowed max data excursion=%s; allowed max slope excursion=%s; actual max data excursion=%s; actual max slope excursion=%s; steady state=%s', $ratio, $mean_y, $maxDataExcursion, $maxSlopeExcursion, $largestDataExcursion, $largestSlopeExcursion, $steadyState ? 'YES' : 'NO'), $this->verbose, __FILE__, __LINE__);
    }
    return $steadyState;
  }
  
  /**
   * This method should return job specific metrics as a single level hash of
   * key/value pairs
   * @return array
   */
  protected abstract function jobMetrics();
  
  /**
   * Purges test devices/volumes prior to testing. Purge methods are determined 
   * by the type and capabilities of the target devices/volumes and the runtime 
   * arguments --nosecureerase, --notrim and --nozerofill. Returns TRUE on 
   * success, FALSE otherwise. Purge methods are tracked on a per device basis 
   * using the instance variable $purgeMethods
   * @return boolean
   */
  public final function purge() {
    global $_purgePerformed;
    if (!isset($_purgePerformed)) $_purgePerformed = FALSE;
    
    $purgeCount = 0;
    $nopurge = isset($this->options['nopurge']) && $this->options['nopurge'];
    $nopurgeIgnore = isset($this->options['nopurge_ignore']) && $this->options['nopurge_ignore'];
    $nosecureerase = isset($this->options['nosecureerase']) && $this->options['nosecureerase'];
    $notrim = isset($this->options['notrim']) && $this->options['notrim'];
    $nozerofill = isset($this->options['nozerofill']) && $this->options['nozerofill'];
    $nozerofillNonRotational = isset($this->options['nozerofill_non_rotational']) && $this->options['nozerofill_non_rotational'];
    $purgeOnce = isset($this->options['purge_once']) && $this->options['purge_once'];
    if (!$nopurge && $purgeOnce && $_purgePerformed) {
      print_msg('Skipping purge because --purge_once was set and targets have already been purged', $this->verbose, __FILE__, __LINE__);
      $purgeCount = count($this->options['target']);
      $nopurge = TRUE;
    }
    if (!$nopurge) {
      foreach($this->options['target'] as $target) {
        $purged = FALSE;
        $volume = self::getVolume($target);
        $rotational = self::isRotational($target);
        print_msg(sprintf('Attempting to purge %srotational target %s with --nosecureerase=%d; --notrim=%d; --nozerofill=%d', $rotational ? '' : 'non-', $target, $nosecureerase ? '1' : '0', $notrim ? '1' : '0', $nozerofill ? '1' : '0'), $this->verbose, __FILE__, __LINE__);
        // try ATA secure erase
        if ($this->deviceTargets && !$nosecureerase) {
          print_msg(sprintf('Attempting ATA secure erase for target %s', $target), $this->verbose, __FILE__, __LINE__);
          $cmd = sprintf('hdparm --user-master u --security-erase "%s" %s >/dev/null 2>&1; echo $?', $this->options['secureerase_pswd'], $target);
          $ecode = trim(exec($cmd));
          if ($ecode > 0) print_msg(sprintf('ATA secure erase not supported or failed for target %s', $target), $this->verbose, __FILE__, __LINE__);
          else {
            print_msg(sprintf('ATA secure erase successful for target %s', $target), $this->verbose, __FILE__, __LINE__);
            $this->purgeMethods[$target] = 'secureerase';
            $purged = TRUE;
          }
        }
        else print_msg(sprintf('ATA secure erase not be attempted for %s because %s', $target, $nosecureerase ? '--nosecureerase argument was specified (or implied due to lack of --secureerase_pswd argument)' : 'it is not a device'), $this->verbose, __FILE__, __LINE__);

        // next try TRIM
        // if (!$purged && !$rotational && !$notrim) {
        if (!$purged && !$notrim) {
          $cmd = sprintf(($this->deviceTargets ? 'blkdiscard' : 'fstrim') . '%s %s >/dev/null 2>&1; echo $?', $this->deviceTargets && isset($this->options['trim_offset_end']) && $this->options['trim_offset_end'] > 0 ? sprintf(' -o 0 -l %d', self::getFreeSpace($target, TRUE) - $this->options['trim_offset_end']) : '', $this->deviceTargets ? $target : $volume);
          print_msg(sprintf('Attempting TRIM for volume %s using command %s', $volume, $cmd), $this->verbose, __FILE__, __LINE__);
          $ecode = trim(exec($cmd));
          if ($ecode > 0) print_msg(sprintf('TRIM not supported or failed for target %s (exit code %d)', $target, $ecode), $this->verbose, __FILE__, __LINE__);
          else {
            print_msg(sprintf('TRIM successful for target %s', $target), $this->verbose, __FILE__, __LINE__);
            $this->purgeMethods[$target] = 'trim';
            $purged = TRUE;
          }
        }
        else if (!$purged) print_msg(sprintf('TRIM not attempted for target %s because %s', $target, $notrim ? '--notrim argument was specified' : 'device is rotational'), $this->verbose, __FILE__, __LINE__);
        
        // finally try zero filling
        if (!$purged && !$nozerofill && (!$nozerofillNonRotational || $rotational)) {
          $size = self::getFreeSpace($target);
          
          // adjust for active range and volume target free space buffer
          if ($this->options['active_range'] < 100) $size *= ($this->options['active_range'] * 0.01);
          else if ($this->volumeTargets) $size -= self::BLOCK_STORAGE_TEST_FREE_SPACE_BUFFER;
          $size = round($size);
          
          if ($size < 1) print_msg(sprintf('Target %s does not have sufficient space (%d MB) to accomodate free space buffer (%d MB)', $target, $size + self::BLOCK_STORAGE_TEST_FREE_SPACE_BUFFER, self::BLOCK_STORAGE_TEST_FREE_SPACE_BUFFER), $this->verbose, __FILE__, __LINE__, TRUE);
          else {
            print_msg(sprintf('Attempting to zero fill target %s with %d MB. This may take a while...', $target, $size), $this->verbose, __FILE__, __LINE__);
            $cmd = sprintf('dd if=/dev/zero of=%s bs=1M count=%d >/dev/null 2>&1; echo $?', $file = $target . ($this->volumeTargets ? '/'. self::BLOCK_STORAGE_TEST_FILE_NAME : ''), $size);
            $ecode = trim(exec($cmd));
            // delete zero file from volume type targets
            if ($this->volumeTargets) {
              print_msg(sprintf('Removing temporary zero fill file %s', $file), $this->verbose, __FILE__, __LINE__);
              exec(sprintf('rm -f %s', $file));
            }
            if ($ecode > 0) print_msg(sprintf('Zero fill failed for target %s (exit code %d)', $target, $ecode), $this->verbose, __FILE__, __LINE__);
            else {
              print_msg(sprintf('Zero fill successful for target %s', $target), $this->verbose, __FILE__, __LINE__);
              $this->purgeMethods[$target] = 'zero';
              $purged = TRUE;
            } 
          }
        }
        else if (!$purged) print_msg(sprintf('Zero fill not attempted for target %s because %s', $target, $nozerofill ? '--nozerofill argument was specified' : ($nozerofillNonRotational && !$rotational ? '--nozerofill_non_rotational argument specified and target is non-rotational' : 'it is not a device')), $this->verbose, __FILE__, __LINE__);
        
        if ($purged) {
          print_msg(sprintf('Target %s purged successfully using %s', $target, $this->purgeMethods[$target]), $this->verbose, __FILE__, __LINE__);
          $purgeCount++;
        }
        else print_msg(sprintf('Target %s could not be purged', $target), $this->verbose, __FILE__, __LINE__);
      } 
    }
    if ($purgeCount == count($this->options['target'])) {
      $_purgePerformed = TRUE;
      return TRUE;
    }
    else if ($nopurgeIgnore) {
      print_msg('Purge could not be performed, but --nopurge_ignore set - testing will continue', $this->verbose, __FILE__, __LINE__);
      return TRUE;
    }
    else return FALSE;
  }
  
  /**
   * invoked before starting tests
   * @return void
   */
  public function start() {
    if (isset($this->options['collectd_rrd'])) ch_collectd_rrd_start($this->options['collectd_rrd_dir'], isset($this->options['verbose']));
  }
  
  /**
   * invoked after testing ends
   * @return void
   */
  public function stop() {
    if (isset($this->options['collectd_rrd'])) {
      ch_collectd_rrd_stop($this->options['collectd_rrd_dir'], $this->options['output'], isset($this->options['verbose']));
      if (is_file($archive = sprintf('%s/collectd-rrd.zip', $this->options['output']))) {
        $narchive = str_replace('.zip', '-' . $this->test . '.zip', $archive);
        exec(sprintf('mv %s %s', $archive, $narchive));
        print_msg(sprintf('Renamed collectd rrd archive from %s to %s', basename($archive), basename($narchive)), $this->verbose, __FILE__, __LINE__);
      }
    }
  }
  
  /**
   * validates test dependencies including:
   *   fio         Performs actual testing - version 2.0+ required
   *   gnuplot     Generates graphs per the SNIA test specification. These graphs
   *               are used in the PDF report
   *   hdparm      Used for ATA secure erase (when supported)
   *   util-linux  For TRIM operations using `blkdiscard` and `fstrim` (when 
   *               supported). Not required if test targets are rotational
   *   wkhtmltopdf Generates PDF version of report - download from 
   *               http://wkhtmltopdf.org
   *   xvfb-run    Allows wkhtmltopdf to be run in headless mode (required if 
   *               --nopdfreport is not set and --wkhtml_xvfb is set)
   *   zip         Archives HTML test report into a single zip file
   * returns an array containing the missing dependencies (array is empty if 
   * all dependencies are valid)
   * @param array $options the run options (see self::getRunOptions)
   * @return array
   */
  public static function validateDependencies($options) {
    $dependencies = array('fio' => 'fio');
    // reporting dependencies
    if (!isset($options['noreport']) || !$options['noreport']) {
      $dependencies['gnuplot'] = 'gnuplot';
      $dependencies['zip'] = 'zip';
      if (!isset($options['nopdfreport']) || !$options['nopdfreport']) {
        $dependencies['wkhtmltopdf'] = 'wkhtmltopdf';
        if (isset($options['wkhtml_xvfb'])) $dependencies['xvfb-run'] = 'xvfb';
      }
    }
    // ATA secure erase requires hdparm
    if ((!isset($options['nosecureerase']) || !$options['nosecureerase']) && isset($options['secureerase_pswd'])) $dependencies['hdparm'] = 'hdparm';
    // non-rotational devices require trim
    if (!isset($options['notrim']) || !$options['notrim']) {
      $nonrotational = FALSE;
      foreach($options['target'] as $target) {
        if (!self::isRotational($target)) {
          $nonrotational = TRUE;
          break;
        }
      }
      if (!$nonrotational) $dependencies['fstrim'] = 'util-linux';
    }
    return validate_dependencies($dependencies);
  }
  
  /**
   * validates fio version and settings. Returns TRUE if it is valid, FALSE 
   * otherwise
   * @param array $options the run options (see self::getRunOptions)
   * @return boolean
   */
  public static function validateFio($options) {
    $fio = trim(shell_exec($options['fio'] . ' --version 2>&1'));
    preg_match('/^fio\-([2-3].[0-9]{1,})/', $fio, $data);
    return $data[1] >= 2.16 ? TRUE : FALSE;
  }
  
  /**
   * validate run options. returns an array populated with error messages 
   * indexed by the argument name. If options are valid, the array returned
   * will be empty
   * @param array $options the run options (see self::getRunOptions)
   * @return array
   */
  public static function validateRunOptions($options) {
    $validate = array(
      'active_range' => array('min' => 1, 'max' => 100),
      'font_size' => array('min' => 6, 'max' => 64),
      'meta_piops' => array('min' => 1),
      'oio_per_thread' => array('min' => 1, 'max' => 256),
      'output' => array('write' => TRUE),
      'precondition_passes' => array('min' => 1, 'max' => 5),
      'precondition_time' => array('min' => 0, 'max' => 86400),
      'skip_blocksize' => array('option' => array('1m', '128k', '64k', '32k', '16k', '8k', '512b')),
      'skip_workload' => array('option' => array('100/0', '95/5', '65/35', '50/50', '35/65', '5/95')),
      'ss_max_rounds' => array('min' => 5, 'max' => 100),
      'ss_verification' => array('min' => 1, 'max' => 100),
      'target' => array('required' => TRUE, 'write' => TRUE),
      'test' => array('option' => self::getSupportedTests(), 'required' => TRUE),
      'threads' => array('min' => 1),
      'threads_per_core_max' => array('min' => 1),
      'threads_per_target_max' => array('min' => 1),
      'timeout' => array('min' => 3600),
      'trim_offset_end' => array('min' => 1),
      'wd_test_duration' => array('min' => 10)
    );
    if (!($valid = validate_options($options, $validate))) {
      $devices = 0;
      $volumes = 0;
      // device and volume type targets cannot be mixed
      foreach($options['target'] as $target) {
        $device = self::getDevice($target);
        $device == $target ? $devices++ : $volumes++;
      }
      if ($devices && $volumes) $valid = array('target' => 'Device and volume type targets cannot be mixed');
          
      // validate collectd rrd options
      if (isset($options['collectd_rrd'])) {
        if (!ch_check_sudo()) $valid['collectd_rrd'] = 'sudo privilege is required to use this option';
        else if (!is_dir($options['collectd_rrd_dir'])) $valid['collectd_rrd_dir'] = sprintf('The directory %s does not exist', $options['collectd_rrd_dir']);
        else if ((shell_exec('ps aux | grep collectd | wc -l')*1 < 2)) $valid['collectd_rrd'] = 'collectd is not running';
        else if ((shell_exec(sprintf('find %s -maxdepth 1 -type d 2>/dev/null | wc -l', $options['collectd_rrd_dir']))*1 < 2)) $valid['collectd_rrd_dir'] = sprintf('The directory %s is empty', $options['collectd_rrd_dir']);
      }
    }
    
    return $valid;
  }
  
  /**
   * Performs workload independent preconditioning for test devices/volumes 
   * prior to testing. This consists of a 2X 128K sequential write across test
   * device targets. This step is skipped if the target is not a device. 
   * Returns TRUE on success, FALSE otherwise. Preconditioned state is tracked
   * with the $wipc instance variable
   * @param string $bs the block size to use for preconditioning. defaults to 
   * 128k
   * @return boolean
   */
  public final function wipc($bs='128k') {
    global $_wipcPerformed;
    if (!isset($_wipcPerformed)) $_wipcPerformed = FALSE;
    
    $noprecondition = $this->skipWipc || (isset($this->options['noprecondition']) && $this->options['noprecondition']);
    $nopreconditionRotational = isset($this->options['noprecondition_rotational']) && $this->options['noprecondition_rotational'];
    $preconditionOnce = isset($this->options['precondition_once']) && $this->options['precondition_once'];
    if (!$noprecondition && $nopreconditionRotational) {
      $rotational = TRUE;
      foreach($this->options['target'] as $target) {
        if (!self::isRotational($target)) {
          $rotational = FALSE;
          break;
        }
      }
      if ($rotational) {
        print_msg('Skipping workload independent preconditioning because --noprecondition_rotational is set and test targets are rotational', $this->verbose, __FILE__, __LINE__);
        $noprecondition = TRUE;
        $this->skipWipc = TRUE;
      }
    }
    if (!$noprecondition && $preconditionOnce && $_wipcPerformed) {
      print_msg('Skipping workload independent preconditioning because --precondition_once is set and preconditioning was already performed', $this->verbose, __FILE__, __LINE__);
      $noprecondition = TRUE;
      $this->skipWipc = TRUE;
    }
    if (!$noprecondition) {
      print_msg(sprintf('Attempting workload independent preconditioning (%dX 128k sequential writes on entire device). %s', $this->options['precondition_passes'], $this->options['precondition_time'] ? 'Preconditioning passes will be fixed ruation of ' . $this->options['precondition_time'] . ' secs' : 'This may take a while...'), $this->verbose, __FILE__, __LINE__);
      for($i=1; $i<=$this->options['precondition_passes']; $i++) {
        
        $opts = array('blocksize' => $bs, 'rw' => 'write', 'numjobs' => 1);
        if ($this->options['precondition_time']) {
          $opts['runtime'] = $this->options['precondition_time'];
          $opts['time_based'] = FALSE;
        }
        print_msg(sprintf('Attempting workload independent precondition pass %d of %d', $i, $this->options['precondition_passes']), $this->verbose, __FILE__, __LINE__);
        if ($this->fio($opts, 'wipc')) {
          $this->wipc = TRUE;
          $_wipcPerformed = TRUE;
          print_msg(sprintf('Workload independent precondition pass %d of %d successful', $i, $this->options['precondition_passes']), $this->verbose, __FILE__, __LINE__);
        }
        else {
          print_msg(sprintf('Workload independent precondition pass %d of %d failed. Preconditioning will stop', $i, $this->options['precondition_passes']), $this->verbose, __FILE__, __LINE__, TRUE);
          break;
        }
      }
    }
    else print_msg(sprintf('Skipping workload independent preconditioning for test %s', $this->test), $this->verbose, __FILE__, __LINE__);
    
    return $this->wipc || $this->skipWipc;
  }
  
  /**
   * returns TRUE if wkhtmltopdf is installed, FALSE otherwise
   * @return boolean
   */
  public final static function wkhtmltopdfInstalled() {
    $ecode = trim(exec('which wkhtmltopdf; echo $?'));
    return $ecode == 0;
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
  public abstract function wdpc();
   

  /**
   * generates a histogram based on the parameters provided. return value is 
   * the name of the image which may in turn be used in an image element for 
   * a content section. returns NULL on error
   * @param string $dir the directory where the line chart should be generated
   * @param string $section the section identifier for the line chart
   * @param array $coords either a single array of tuples representing the x/y
   * values, or a hash or tuple arrays indexed by the name of each set of data
   * points. coordinates should have the same 
   * @param string $title optional graph title
   * @param array $settings optional array of custom gnuplot settings. the 
   * following special settings are supported:   
   *   xMax:      max value for the x axis tics - may be a percentage relative to 
   *              the highest value
   * 
   * @param boolean $html whether or not to return the html <img element or just
   * the name of the file   *
   * @return string
   */
  protected final function generateHistogram($dir, $section, $coords, $title=NULL, $settings=NULL) {
    print_msg(sprintf('Generating Histogram in %s for test %s and section %s with %d coords', $dir, $this->test, $section, count($coords)), $this->verbose, __FILE__, __LINE__);
    
    $chart = NULL;
    $script = sprintf('%s/%s-%s.pg', $dir, $this->test, $section);
    $dfile = sprintf('%s/%s-%s.dat', $dir, $this->test, $section);
    if (is_array($coords) && ($fp = fopen($script, 'w')) && ($df = fopen($dfile, 'w'))) {
      
      // determine value ranges and generate data file
      $maxPoints = count($coords);

      foreach($coords as $time=>$value){
        fwrite($df, sprintf("%d\t%d\n", $time, $value));
      }
      fclose($df);   
     
      $img = sprintf('%s/%s-%s.svg', $dir, $this->test, $section);
      $xScale = round($settings['xMax'] / 10);

      $histogram = 
      "#!/usr/bin/gnuplot
      reset
      set terminal svg dashed size 1024,600 fontfile 'font-svg.css' font 'rfont,13'
      set autoscale keepfix
      set decimal locale
      set xlabel \"Time (ms)\"
      set ylabel \"Count (A.U.)\"
      set xrange [0:%d]
      set xtics 0, %d, %d
      set logscale y
      set key outside center top horizontal reverse
      set grid
      set border linewidth 1.5
      set style line 1 lc rgb \"red\" lt 1 lw 3
      set style fill solid noborder
      set boxwidth 0.9 relative
      set style histogram cluster gap 0
      set style data histogram
      set grid ytics lc rgb '#000000' lw 1 lt 0
      set tic scale 0
      plot \"%s\" u 2 ls 1 notitle";

      $str = sprintf("$histogram", $settings['xMax'], $xScale, $settings['xMax'], basename($dfile));
      fwrite($fp, $str);
      print_msg(sprintf('Generating histogram %s with %d data sets. Title: %s', basename($img), $maxPoints, $title), $this->verbose, __FILE__, __LINE__);     
      fclose($fp);

      exec(sprintf('chmod +x %s', $script));
      $cmd = sprintf('cd %s; ./%s > %s 2>/dev/null; echo $?', $dir, basename($script), basename($img));
      $ecode = trim(exec($cmd));
      if ($ecode > 0) {
        print_msg(sprintf('Failed to generate histogram - exit code %d', $ecode), $this->verbose, __FILE__, __LINE__, TRUE);
      }
      else {
        print_msg(sprintf('Generated histogram %s successfully', $img), $this->verbose, __FILE__, __LINE__);
        // attempt to convert to PNG using wkhtmltoimage
        if (self::wkhtmltopdfInstalled()) {
          //$cmd = sprintf('sudo %swkhtmltoimage %s %s >/dev/null 2>&1', isset($this->options['wkhtml_xvfb']) ? 'xvfb-run ' : '', $img, $png = str_replace('.svg', '.png', $img));
          $cmd = sprintf('sudo xvfb-run wkhtmltoimage %s %s >/dev/null 2>&1', $img, $png = str_replace('.svg', '.png', $img));
          $ecode = trim(exec($cmd));
          sleep(1);
          if (!file_exists($png) || !filesize($png)) print_msg(sprintf('Unable to convert SVG image %s to PNG %s (exit code %d)', $img, $png, $ecode), $this->verbose, __FILE__, __LINE__, TRUE);
          else {
            exec(sprintf('rm -f %s', $img));
            print_msg(sprintf('SVG image %s converted to PNG successfully - PNG will be used in report', basename($img)), $this->verbose, __FILE__, __LINE__);
            $img = $png;
          }
        }
        // return full image tag
        $chart = sprintf('<img alt="%s" class="plot" src="%s" />', $this->getSubtitle($section), basename($img));
        
      }
    }
    // error - invalid scripts or unable to open gnuplot files
    else {
      print_msg(sprintf('Failed to generate histogram - either coordinates are invalid or script/data files %s/%s could not be opened', basename($script), basename($dfile)), $this->verbose, __FILE__, __LINE__, TRUE);
      if ($fp) {
        fclose($fp);
        exec('rm -f %s', $script);
      }
    }
    return $chart;
  }

  /**
   * Insert data into DB
   * @param object $dbLink
   * @param string $table table name
   */
  public function DBInsert($dbLink, $table){
    try{
      $sql_query = sprintf("INSERT INTO `%s_report`(`info_no`) VALUES (?)", $table);
      $stmt = $dbLink->prepare($sql_query);
      $stmt->execute(array($this->options['meta_test_id']));
    }
    catch(PDOException $e){
      print_msg(sprintf('Fail to write to DB, information：%s', $e->getMessage()), $this->verbose, __FILE__, __LINE__, TRUE);
    }

  }

  /**
   * Update DB data
   * @param object $dbLink
   * @param string $table table name
   * @param string $field field
   * @param float  $value data
   */
  public function DBUpdate($dbLink, $table, $field, $value){
    try{                
      $sql_query = sprintf("UPDATE `%s_report` SET `%s`=? WHERE `%s_report`.`info_no`=?", $table, $field, $table);
      $stmt = $dbLink->prepare($sql_query);
      $stmt->execute(array($value, $this->options['meta_test_id']));
    }
    catch(PDOException $e){
      print_msg(sprintf('Fail to write to DB, information：%s', $e->getMessage()), $this->verbose, __FILE__, __LINE__, TRUE);
    }
  }
   
}
?>

