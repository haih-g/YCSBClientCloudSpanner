<?php
namespace Google\Cloud\Samples\Spanner;
use Google\Cloud\Spanner\SpannerClient;
/*
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputDefinition;
*/
# Includes the autoloader for libraries installed with composer
require __DIR__ . '/vendor/autoload.php';
/*
Uasge:
php ycsb.php --instance=ycsb-bb9e6936 --database=ycsb table=usertable [--key=user1100197033673136279] --operationcount=1 --perform=[LoadKeys|PerformRead|Update]
*/

$msg = "";
$arrKEYS = [];
$arrOPERATIONS = ['readproportion', 'updateproportion', 'scanproportion', 'insertproportion'];


// Was going to try to multi thread, but Thread class is considered very dangerous in a CLI
// environment.  To multi-thread, please incorporate class into a PHP web page and make multiple
// calls to the same page.
//class WorkloadThread extends Thread {
class WorkloadThread {    
    // It is assumed that all calls are going out the same GRPC connection.
    // Please clarify if each thread should spawn its own GRPC.
    
    public $_database;
    public $_arrParameters;
    public $_fltTotalWeight;
    public $_arrWeights;
    public $_arrOperations;
    
    public function __construct($database, $arrParameters, $fltTotalWeight, $arrWeights, $arrOperations) {
        // Sorry, single threaded only
        $this->_database = $database;
        $this->_arrParameters = $arrParameters;
        $this->_fltTotalWeight = $fltTotalWeight;
        $this->_arrWeights = $arrWeights;
        $this->_arrOperations = $arrOperations;
        }

    public function run() {
        // Run a single thread of the workload
        $i = 0;
		$intOperationCount = (int)$this->_arrParameters['operationcount'];
        while ($i < $intOperationCount) {
            $i += 1;
            $fltWeight = rand(0, $this->_fltTotalWeight);
            for ($j=0;$j<count($this->_arrWeights);$j++) {
                if ($fltWeight <= $this->_arrWeights[j]) {
                    $this->DoOperation();
					break;
                    }
                }
            }
        }

    public function LoadKeys($database, $arrParameters) {
        global $arrKEYS;
        $arrKEYS = array();
	$time_start = microtime(true);
        $snapshot = $database->snapshot();
        // Kind of assuming that id is ubiquitous...
        $results = $snapshot->execute('SELECT id FROM ' . $arrParameters['table']);
         foreach ($results as $row) {
            $arrKEYS[] = $row['id'];
            }
	return microtime(true) - $time_start;
        }

    public function PerformRead($database, $table, $key) {
        //Changed named to PerformRead because Read is a reserved keyword.
        global $arrKEYS;
        $arrKEYS = array();
	$time_start = microtime(true);
        $snapshot = $database->snapshot();
        // Kind of assuming that id is ubiquitous...
        $results = $snapshot->execute("SELECT * FROM $table where id = '$key'");
        /*
        foreach ($results as $row) {
            // Not sure why the original Python script does this.
            // We don't really need to parse results.
            $key = $row[0];
            }
	*/
	return microtime(true) - $time_start;
        }

    public function Update($database, $table, $key) {
        // Does a single update operation.
        $field = rand(0,9);
        $time_start = microtime(true);
        $operation = $database->transaction(['singleUse' => true])
            ->updateBatch($table, [
                ['id' => $key, "field".$field => $this->randString(false, 100)],
                ])
            ->commit();
        return microtime(true) - $time_start;
        }

    public function Insert($database, $table, $incount) {
        $arrBatch = [];  //array of $arrFields
        $arrFields = [];
        for ($rCount = 0; $rCount < $incount; $rCount++) {
            $arrFields["id"] = "user4" . $this->randString(true, 17);
            for ($f = 0; $f < 10; $f++) {
                $arrFields["field".$f] = $this->randString(false, 100);
                }
            $arrbatch[] = $arrFields;
            }
        array_multisort($arrBatch);
        $time_start = microtime(true);
        $operation = $database->transaction(['singleUse' => true])->insertBatch($table, $arrBatch)->commit();
        return microtime(true) - $time_start;
        }

    public function DoOperation($database, $table, $operation) {
    
    
            
        }

    public function randString($num, $len) {
        $strRand = "";
        if ($num == true)
            $characters = '0123456789';
        else
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charlen = strlen($characters);
        for ($i = 0; $i < $len; $i++) {
            $strRand .= $characters[rand(0, $charlen - 1)];
            }
        return $strRand;
        }

    /* If we were going to use Threads, Threads would require us to have run() function
    public function run() {
        }
    */
    }


//Lives outside the class because it will only potentially be called once.
function parseCliOptions() {
    $longopts = array(
        "recordcount::",
        "operationcount:",
        "clienttype::",
        "numworker::",
        "instance:",
        "database:",
        "table:",
        "perform:",
        "noskip_spanner_setup::",
        "skip_spanner_teardown::",
        "key::",
		"workload",
        );
    $arrParameters = getopt("", $longopts);
    // Now we have things like $arrParameters["num_worker"]

	$myfile = fopen($arrParameters['workload'], "r") or die("Unable to open file!");
    while ($line = fgets($myfile)) {
        $parts = explode("=", $line);
	    $key = trim($parts[0]);
		if (in_array($key, $arrOPERATIONS)) {
            $option[$key] = trim($parts[1]);
            }
        }
	fclose($myfile);
	
    return $arrParameters;
    }

function OpenDatabase($arrParameters) {
    //global $database;
    $spanner = new SpannerClient();
    $instance = $spanner->instance($arrParameters['instance']);
    $database = $instance->database($arrParameters['database']);
    return $database;
    }

function ReportSwitch($strMsg) {
    global $msg;
    if (php_sapi_name() == 'cli') {
        print $strMsg;
        }
    else {
        // Otherwise, if it is being called from a browser, aggregate into a message.
        $msg .= $strMsg;
        }
}

function RunWorkload($database, $parameters) {
    $fltTotalWeight = 0.0;
    $arrWeights = [];
    $arrOperations = [];
    $latencies_ms = [];
    foreach($arrOPERATIONS as $operation) {
        $weight = (float)$parameters[$operation];
        if ($weight <= 0.0) continue;
        $fltTotalWeight += $weight;
        $op_code = explode('proportion', $operation);
        $arrOperations[] = $op_code[0];
        $arrWeights[] = $fltTotalWeight;
        $latencies_ms[$op_code] = [];
        }
	$time_start = microtime(true);
    Workload($database, $parameters, $fltTotalWeight, $arrWeights, $arrOperations);
    $time_end = microtime(true) - $time_start;
    // Unfortunately, latencies not stored and reported like in the original script.
    // AggregateMetrics(latencies_ms, (end - start) * 1000.0, parameters['num_bucket']);
}



// Allow for calling from a webserver
if (php_sapi_name() == 'cli') {
    $arrParameters = parseCliOptions();
    reportSwitch("Called from command line.\n");
    }
else {
    $arrParameters = parseQueryStringOptions();
    reportSwitch("Called from web browser.\n");
    }

foreach ($arrParameters as $opKey => $opVal) {
    reportSwitch("$opKey value is $opVal.\n");
    }

$testOp = new WorkloadThread();

reportSwitch("Connecting to " . $arrParameters['database'] . "\n");

// Initial connection
$time_start = microtime(true);
$database = OpenDatabase($arrParameters);
$time_exec = microtime(true) - $time_start;
reportSwitch("Connected to " . $arrParameters['database'] . " in $time_exec seconds.\n");


for ($cntYCSB = 0; $cntYCSB < $arrParameters['operationcount']; $cntYCSB++) {
    switch ($arrParameters['perform']) {
        case "LoadKeys":
            reportSwitch("Loaded keys in ".$testOp->LoadKeys($database, $arrParameters)." seconds. \n");
            break;
        case "PerformRead":
            reportSwitch("Performed Read in ".$testOp->PerformRead($database, $arrParameters['table'],"user1100197033673136279")." seconds.\n");
            break;
        case "Update":
            reportSwitch("Updated Key Val in ".$testOp->Update($database, $arrParameters['table'],"user1100197033673136279")." seconds.\n");
            break;
        case "Insert":
            reportSwitch("Inserted {$arrParameters['recordcount']} records into {$arrParameters['table']} in ".$testOp->Insert($database, $arrParameters['table'],$arrParameters['recordcount'])." seconds.\n");
            break;
        default:
            break;
        }
    }

if ($msg !="") print $msg;

?>
