#!/usr/bin/php -q
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
 * run script for block storage testing
 */
require_once('BlockStorageTest.php');
// parse cli arguments
$options = BlockStorageTest::getRunOptions();
// print_r($argv);
// print_r($options);
// exit;
$verbose = isset($options['verbose']) && $options['verbose'];

// invalid run argument
if ($invalid = BlockStorageTest::validateRunOptions($options)) {
  foreach($invalid as $arg => $err) print_msg(sprintf('argument --%s is invalid - %s', $arg, $err), $verbose, __FILE__, __LINE__, TRUE);
  exit(1);
}
// missing dependencies
else if ($dependencies = BlockStorageTest::validateDependencies($options)) {
  foreach($dependencies as $dependency) print_msg(sprintf('missing dependency %s', $dependency), $verbose, __FILE__, __LINE__, TRUE);
  exit(1);
}
// fio version and settings
else if (!BlockStorageTest::validateFio($options)) {
  print_msg(sprintf('fio version must be greater than or equal to 2.16'), $verbose, __FILE__, __LINE__, TRUE);
  exit(1);
}
print_msg(sprintf('Starting block storage tests [%s] using targets [%s] and %ds timeout', implode(', ', $options['test']), implode(', ', $options['target']), $options['timeout']), $verbose, __FILE__, __LINE__);
ini_set('max_execution_time', $options['timeout']);

$exitCode = 0;
$controllers = array();
foreach($options['test'] as $test) {
  if ($controller =& BlockStorageTest::getTestController($test, $options)) {
    print_msg(sprintf('Starting %s block storage test', strtoupper($test)), $verbose, __FILE__, __LINE__);
    
    // purge targets
    if (isset($options['nopurge']) && $options['nopurge']) print_msg(sprintf('Target purge skipped due to use of --nopurge'), $verbose, __FILE__, __LINE__);
    else if (!$controller->purge()) {
      $exitCode = 1;
      print_msg(sprintf('Testing aborted because test targets could not be purged and --nopurge argument was not specified'), $verbose, __FILE__, __LINE__, TRUE);
      break;
    }
    else print_msg(sprintf('Target purge successful - continuing testing'), $verbose, __FILE__, __LINE__);
    
    // workload independent pre-conditioning
    $controller->start();
    if (isset($options['noprecondition']) && $options['noprecondition']) print_msg(sprintf('Workload independent precondition skipped due to use of --noprecondition'), $verbose, __FILE__, __LINE__);
    else if (!$controller->wipc()) {
      $exitCode = 1;
      print_msg(sprintf('Testing aborted because workload independent preconditioning failed and --noprecondition argument was not specified'), $verbose, __FILE__, __LINE__, TRUE);
      break;
    }
    else print_msg(sprintf('Workload independent preconditioning successful - continuing testing'), $verbose, __FILE__, __LINE__);
    
    // workload dependent pre-conditioning & testing
    $status = $controller->wdpc();
    if ($status !== NULL) {
      print_msg(sprintf('Workload dependent preconditioning for test %s successful%s. wdpcComplete=%d; wdpcIntervals=%d. Generating test artifacts...', $test, $status ? '' : ' - but steady state was not achieved', $controller->wdpcComplete, $controller->wdpcIntervals), $verbose, __FILE__, __LINE__);
      // generate fio JSON output
      $controller->generateJson();
      $controllers[$test] =& $controller;
    }
    else print_msg(sprintf('Workload dependent preconditioning for test %s failed', strtoupper($test)), $verbose, __FILE__, __LINE__, TRUE);
    $controller->stop();
  }
  else print_msg(sprintf('Unable to get %s test controller', $test), $verbose, __FILE__, __LINE__, TRUE);
}
// generate report
if (!$exitCode && count($controllers)) {
  print_msg(sprintf('Generating reports...'), $verbose, __FILE__, __LINE__);
  BlockStorageTest::generateReports($controllers);
  print_msg(sprintf('Report generation complete'), $verbose, __FILE__, __LINE__);
}

print_msg(sprintf('Exiting with status code %d', $exitCode), $verbose, __FILE__, __LINE__);

exit($exitCode);
?>
